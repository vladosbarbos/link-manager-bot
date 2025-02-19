<?php

namespace App\Command;

use App\Entity\User;
use App\Service\RecommendationService;
use App\Service\TelegramBotService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:send-daily-notifications',
    description: 'Отправляет ежедневные уведомления пользователям о непрочитанных ссылках'
)]
class SendDailyNotificationsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RecommendationService $recommendationService,
        private readonly TelegramBotService $telegramBotService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $currentTime = new DateTime();
        $currentHour = (int) $currentTime->format('H');
        $currentMinute = (int) $currentTime->format('i');

        // Получаем пользователей, у которых установлено время уведомлений на текущий час
        $users = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('TIME_FORMAT(u.notificationTime, "%H") = :hour')
            ->andWhere('TIME_FORMAT(u.notificationTime, "%i") BETWEEN :minStart AND :minEnd')
            ->setParameter('hour', $currentHour)
            ->setParameter('minStart', $currentMinute - 2)
            ->setParameter('minEnd', $currentMinute + 2)
            ->getQuery()
            ->getResult();

        foreach ($users as $user) {
            $this->sendDailyNotification($user, $output);
        }

        return Command::SUCCESS;
    }

    private function sendDailyNotification(User $user, OutputInterface $output): void
    {
        $recommendations = $this->recommendationService->getPersonalizedDailyLinks($user);

        if (empty($recommendations)) {
            return;
        }

        $message = "🎯 Ваши рекомендации на сегодня:\n\n";
        foreach ($recommendations as $link) {
            $tags = array_map(
                fn ($tag) => '#'.$tag->getName(),
                $link->getTags()->toArray()
            );

            $message .= sprintf(
                "🔗 %s\nТеги: %s\n\n",
                $link->getUrl(),
                implode(' ', $tags)
            );
        }

        try {
            $this->telegramBotService->sendMessage($user->getTelegramId(), $message);
            $output->writeln(sprintf('Уведомление отправлено пользователю %s', $user->getUsername() ?? $user->getTelegramId()));
        } catch (Exception $e) {
            $output->writeln(sprintf('Ошибка отправки уведомления пользователю %s: %s',
                $user->getUsername() ?? $user->getTelegramId(),
                $e->getMessage()
            ));
        }
    }
}
