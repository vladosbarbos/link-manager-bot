<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Link;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;

class RecommendationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function getRecommendations(User $user, int $limit = 5): array
    {
        // Получаем непрочитанные ссылки пользователя
        $unreadLinks = $this->getUnreadLinks($user);
        
        if (empty($unreadLinks)) {
            return [];
        }

        // Получаем популярные теги пользователя
        $popularTags = $this->getPopularUserTags($user);
        
        // Сортируем ссылки по релевантности
        return $this->sortLinksByRelevance($unreadLinks, $popularTags, $limit);
    }

    private function getUnreadLinks(User $user): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('l', 't')
            ->from(Link::class, 'l')
            ->join('l.tags', 't')
            ->where('l.user = :user')
            ->andWhere('l.isRead = :isRead')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getResult();
    }

    private function getPopularUserTags(User $user): array
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('t.name, COUNT(l.id) as usage_count')
            ->from(Link::class, 'l')
            ->join('l.tags', 't')
            ->where('l.user = :user')
            ->andWhere('l.isRead = :isRead')
            ->setParameter('user', $user)
            ->setParameter('isRead', true)
            ->groupBy('t.name')
            ->orderBy('usage_count', 'DESC')
            ->getQuery()
            ->getResult();

        $tags = [];
        foreach ($result as $row) {
            $tags[$row['name']] = (int)$row['usage_count'];
        }

        return $tags;
    }

    private function sortLinksByRelevance(array $links, array $popularTags, int $limit): array
    {
        $scoredLinks = [];

        foreach ($links as $link) {
            $score = 0;
            
            /** @var Tag $tag */
            foreach ($link->getTags() as $tag) {
                // Если тег есть в популярных, увеличиваем счет
                if (isset($popularTags[$tag->getName()])) {
                    $score += $popularTags[$tag->getName()];
                }
            }

            // Учитываем время создания (более новые ссылки получают преимущество)
            $age = time() - $link->getCreatedAt()->getTimestamp();
            $score += (1 / ($age + 1)) * 100;

            $scoredLinks[] = [
                'link' => $link,
                'score' => $score
            ];
        }

        // Сортируем по счету
        usort($scoredLinks, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Возвращаем только ссылки, без счета
        return array_slice(
            array_map(fn($item) => $item['link'], $scoredLinks),
            0,
            $limit
        );
    }

    public function markAsRead(Link $link): void
    {
        $link->setIsRead(true);
        $this->entityManager->flush();
    }

    public function getPersonalizedDailyLinks(User $user, int $limit = 3): array
    {
        // Получаем рекомендации
        $recommendations = $this->getRecommendations($user, $limit);
        
        // Если рекомендаций недостаточно, добавляем самые старые непрочитанные ссылки
        if (count($recommendations) < $limit) {
            $oldestLinks = $this->entityManager->createQueryBuilder()
                ->select('l')
                ->from(Link::class, 'l')
                ->where('l.user = :user')
                ->andWhere('l.isRead = :isRead')
                ->andWhere('l NOT IN (:recommendations)')
                ->setParameter('user', $user)
                ->setParameter('isRead', false)
                ->setParameter('recommendations', $recommendations)
                ->orderBy('l.createdAt', 'ASC')
                ->setMaxResults($limit - count($recommendations))
                ->getQuery()
                ->getResult();

            $recommendations = array_merge($recommendations, $oldestLinks);
        }

        return $recommendations;
    }
} 