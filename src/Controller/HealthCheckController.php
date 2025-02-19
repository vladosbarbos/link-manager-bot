<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\DBAL\Connection;

class HealthCheckController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection
    ) {}

    #[Route('/health', name: 'app_health_check', methods: ['GET'])]
    public function check(): JsonResponse
    {
        $status = [
            'status' => 'ok',
            'timestamp' => time(),
            'database' => 'ok',
            'php_version' => PHP_VERSION,
            'environment' => $this->getParameter('kernel.environment'),
        ];

        try {
            // Проверяем подключение к базе данных
            $this->connection->executeQuery('SELECT 1');
        } catch (\Exception $e) {
            $status['status'] = 'error';
            $status['database'] = 'error: ' . $e->getMessage();
        }

        return new JsonResponse($status);
    }

    #[Route('/', name: 'app_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse([
            'app' => 'Link Manager Bot',
            'version' => '1.0.0',
            'docs' => '/api/docs', // Если будете добавлять API документацию
            'health' => '/health',
            'endpoints' => [
                'webhook' => '/webhook/{secret}',
                'health' => '/health'
            ]
        ]);
    }
} 