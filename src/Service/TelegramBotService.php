<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Link;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramBotService
{
    private string $token;
    private string $apiUrl = 'https://api.telegram.org/bot';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $telegramBotToken
    ) {
        $this->token = $telegramBotToken;
    }

    public function handleUpdate(array $update): void
    {
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }
    }

    private function handleMessage(array $message): void
    {
        $telegramId = $message['from']['id'];
        $text = $message['text'] ?? '';

        $user = $this->getOrCreateUser($telegramId, $message['from']['username'] ?? null);

        if (str_starts_with($text, '/start')) {
            $this->sendWelcomeMessage($telegramId);
        } elseif (str_starts_with($text, '/add')) {
            $this->handleAddLink($user, $text);
        } elseif (str_starts_with($text, '/settime')) {
            $this->handleSetTime($user, $text);
        }
    }

    private function getOrCreateUser(string $telegramId, ?string $username): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['telegramId' => $telegramId]);

        if (!$user) {
            $user = new User();
            $user->setTelegramId($telegramId);
            $user->setUsername($username);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        return $user;
    }

    public function sendMessage(string $chatId, string $text): void
    {
        $this->httpClient->request('POST', $this->apiUrl . $this->token . '/sendMessage', [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ],
        ]);
    }

    private function handleAddLink(User $user, string $text): void
    {
        // Парсинг URL и тегов из текста
        preg_match('/\/add\s+(\S+)\s*(.*)/', $text, $matches);
        
        if (count($matches) < 2) {
            $this->sendMessage($user->getTelegramId(), 'Пожалуйста, укажите URL и теги в формате: /add URL #тег1 #тег2');
            return;
        }

        $url = $matches[1];
        $tagStrings = [];
        preg_match_all('/#(\w+)/', $matches[2], $tagMatches);
        
        if (isset($tagMatches[1])) {
            $tagStrings = $tagMatches[1];
        }

        // Создание ссылки
        $link = new Link();
        $link->setUrl($url);
        $link->setUser($user);

        // Добавление тегов
        foreach ($tagStrings as $tagString) {
            $tag = $this->entityManager->getRepository(Tag::class)->findOneBy([
                'name' => $tagString,
                'user' => $user,
            ]) ?? new Tag();

            if (!$tag->getId()) {
                $tag->setName($tagString);
                $tag->setUser($user);
                $this->entityManager->persist($tag);
            }

            $link->addTag($tag);
        }

        $this->entityManager->persist($link);
        $this->entityManager->flush();

        $this->sendMessage($user->getTelegramId(), 'Ссылка успешно сохранена!');
    }

    private function handleSetTime(User $user, string $text): void
    {
        preg_match('/\/settime\s+(\d{1,2}):(\d{2})/', $text, $matches);

        if (count($matches) !== 3) {
            $this->sendMessage($user->getTelegramId(), 'Пожалуйста, укажите время в формате: /settime ЧЧ:ММ');
            return;
        }

        $time = new \DateTime();
        $time->setTime((int)$matches[1], (int)$matches[2]);

        $user->setNotificationTime($time);
        $this->entityManager->flush();

        $this->sendMessage(
            $user->getTelegramId(),
            sprintf('Время уведомлений установлено на %s', $time->format('H:i'))
        );
    }

    private function sendWelcomeMessage(string $chatId): void
    {
        $message = <<<TEXT
        👋 Добро пожаловать в LinkKeeper Bot!

        Я помогу вам сохранять и организовывать ссылки на статьи и видео.

        Основные команды:
        /add URL #тег1 #тег2 - Добавить новую ссылку
        /list - Показать все ваши ссылки
        /settime ЧЧ:ММ - Установить время для ежедневных напоминаний
        /tags - Показать все ваши теги
        /recommendations - Получить персональные рекомендации

        Начните с добавления первой ссылки!
        TEXT;

        $this->sendMessage($chatId, $message);
    }
} 