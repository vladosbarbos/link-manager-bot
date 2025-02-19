<?php

namespace App\Service;

use App\Entity\Link;
use App\Entity\Tag;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use Psr\Log\LoggerInterface;

class TelegramBotService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $token,
        private readonly string $botUsername,
        private readonly LoggerInterface $telegramLogger,
        private readonly LoggerInterface $databaseLogger,
        private readonly LoggerInterface $securityLogger,
    ) {
        // Инициализируем Telegram API при создании сервиса
        $this->initTelegram();
        $this->telegramLogger->info('TelegramBotService initialized', [
            'botUsername' => $this->botUsername,
        ]);
    }

    private function initTelegram(): void
    {
        try {
            new Telegram($this->token, $this->botUsername);
        } catch (TelegramException $e) {
            $this->telegramLogger->error('Failed to initialize Telegram', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function handleUpdate(array $updateData): void
    {
        try {
            $this->telegramLogger->debug('Received update', ['update' => $updateData]);

            $update = new Update($updateData);
            $message = $update->getMessage();

            if (!$message) {
                $this->telegramLogger->warning('Received update without message');

                return;
            }

            $telegramId = (string) $message->getFrom()->getId();
            $text = $message->getText() ?? '';

            $this->telegramLogger->info('Processing message', [
                'telegramId' => $telegramId,
                'text' => $text,
            ]);

            $user = $this->getOrCreateUser(
                $telegramId,
                $message->getFrom()->getUsername()
            );

            if ('/start' === $text) {
                $this->sendWelcomeMessage($telegramId);
            } elseif (str_starts_with($text, '/add')) {
                $this->handleAddLink($user, $text);
            } elseif (str_starts_with($text, '/settime')) {
                $this->handleSetTime($user, $text);
            }
        } catch (TelegramException $e) {
            $this->telegramLogger->error('Error processing update', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function getOrCreateUser(string $telegramId, ?string $username): User
    {
        $this->databaseLogger->debug('Looking for user', [
            'telegramId' => $telegramId,
            'username' => $username,
        ]);

        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['telegramId' => $telegramId]);

        if (!$user) {
            $this->databaseLogger->info('Creating new user', [
                'telegramId' => $telegramId,
                'username' => $username,
            ]);

            $user = new User();
            $user->setTelegramId($telegramId);
            $user->setUsername($username);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->securityLogger->info('New user registered', [
                'telegramId' => $telegramId,
                'username' => $username,
            ]);
        }

        return $user;
    }

    public function sendMessage(string $chatId, string $text): void
    {
        try {
            $this->telegramLogger->debug('Sending message', [
                'chatId' => $chatId,
                'text' => $text,
            ]);

            Request::sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);

            $this->telegramLogger->info('Message sent successfully', [
                'chatId' => $chatId,
            ]);
        } catch (TelegramException $e) {
            $this->telegramLogger->error('Error sending message', [
                'chatId' => $chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function handleAddLink(User $user, string $text): void
    {
        if (!preg_match('/\/add\s+(\S+)\s*(.*)/', $text, $matches)) {
            $this->telegramLogger->warning('Invalid add link format', [
                'text' => $text,
                'userId' => $user->getId(),
            ]);

            $this->sendMessage(
                $user->getTelegramId(),
                'Пожалуйста, укажите URL и теги в формате: /add URL #тег1 #тег2'
            );

            return;
        }

        $url = $matches[1];
        preg_match_all('/#(\w+)/', $matches[2], $tagMatches);
        $tagStrings = $tagMatches[1];

        $this->telegramLogger->info('Adding new link', [
            'url' => $url,
            'tags' => $tagStrings,
            'userId' => $user->getId(),
        ]);

        $link = new Link();
        $link->setUrl($url);
        $link->setUser($user);

        foreach ($tagStrings as $tagString) {
            $this->databaseLogger->debug('Processing tag', [
                'tag' => $tagString,
                'userId' => $user->getId(),
            ]);

            $tag = $this->entityManager->getRepository(Tag::class)->findOneBy([
                'name' => $tagString,
                'user' => $user,
            ]) ?? new Tag();

            if (!$tag->getId()) {
                $this->databaseLogger->info('Creating new tag', [
                    'tag' => $tagString,
                    'userId' => $user->getId(),
                ]);

                $tag->setName($tagString);
                $tag->setUser($user);
                $this->entityManager->persist($tag);
            }

            $link->addTag($tag);
        }

        $this->entityManager->persist($link);
        $this->entityManager->flush();

        $this->databaseLogger->info('Link saved successfully', [
            'linkId' => $link->getId(),
            'url' => $url,
            'userId' => $user->getId(),
        ]);

        $this->sendMessage($user->getTelegramId(), 'Ссылка успешно сохранена!');
    }

    private function handleSetTime(User $user, string $text): void
    {
        if (!preg_match('/\/settime\s+(\d{1,2}):(\d{2})/', $text, $matches)) {
            $this->telegramLogger->warning('Invalid time format', [
                'text' => $text,
                'userId' => $user->getId(),
            ]);

            $this->sendMessage(
                $user->getTelegramId(),
                'Пожалуйста, укажите время в формате: /settime ЧЧ:ММ'
            );

            return;
        }

        $time = new DateTime();
        $time->setTime((int) $matches[1], (int) $matches[2]);

        $this->databaseLogger->info('Setting notification time', [
            'userId' => $user->getId(),
            'time' => $time->format('H:i'),
        ]);

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
