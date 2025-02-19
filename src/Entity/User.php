<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'bigint', unique: true)]
    private string $telegramId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?DateTimeInterface $notificationTime = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Link::class)]
    private Collection $links;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Tag::class)]
    private Collection $tags;

    public function __construct()
    {
        $this->links = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    // Геттеры и сеттеры
}
