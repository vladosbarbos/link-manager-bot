<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:setup-webhook',
    description: 'Устанавливает вебхук для Telegram бота'
)]
class SetupWebhookCommand extends Command
{
    public function __construct(
        private readonly string $telegramBotToken,
        private readonly string $webhookSecret,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('url', InputArgument::REQUIRED, 'URL вашего сервера');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $input->getArgument('url');
        $webhookUrl = rtrim($url, '/').'/webhook/'.$this->webhookSecret;

        $response = $this->httpClient->request(
            'POST',
            sprintf('https://api.telegram.org/bot%s/setWebhook', $this->telegramBotToken),
            [
                'json' => [
                    'url' => $webhookUrl,
                    'allowed_updates' => ['message'],
                ],
            ]
        );

        $result = json_decode($response->getContent(), true);

        if ($result['ok']) {
            $output->writeln('<info>Вебхук успешно установлен!</info>');

            return Command::SUCCESS;
        }

        $output->writeln('<error>Ошибка при установке вебхука: '.($result['description'] ?? 'Неизвестная ошибка').'</error>');

        return Command::FAILURE;
    }
}
