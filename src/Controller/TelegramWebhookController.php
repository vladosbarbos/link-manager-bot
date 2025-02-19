<?php

namespace App\Controller;

use App\Service\TelegramBotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TelegramWebhookController extends AbstractController
{
    public function __construct(
        private readonly TelegramBotService $telegramBotService
    ) {}

    #[Route('/webhook/{secret}', name: 'telegram_webhook', methods: ['POST'])]
    public function webhook(Request $request, string $secret): Response
    {
        // Проверка секретного ключа
        if ($secret !== $this->getParameter('app.telegram_webhook_secret')) {
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $update = json_decode($request->getContent(), true);
        
        if (!$update) {
            return new Response('Invalid request', Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->telegramBotService->handleUpdate($update);
        } catch (\Exception $e) {
            // Логирование ошибки
            return new Response('Error processing update', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response('OK');
    }
} 