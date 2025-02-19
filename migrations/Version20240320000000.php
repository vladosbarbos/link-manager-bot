<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240320000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создание основных таблиц';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (
            id INT AUTO_INCREMENT NOT NULL,
            telegram_id BIGINT NOT NULL,
            username VARCHAR(255) DEFAULT NULL,
            notification_time TIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_1483A5E9CC0B3066 (telegram_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE links (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            url VARCHAR(2048) NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            INDEX IDX_2CF8D8B7A76ED395 (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE tags (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_6FBC9426A76ED395 (user_id),
            UNIQUE INDEX unique_tag_per_user (name, user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE link_tags (
            link_id INT NOT NULL,
            tag_id INT NOT NULL,
            INDEX IDX_D194F33EADA40271 (link_id),
            INDEX IDX_D194F33EBAD26311 (tag_id),
            PRIMARY KEY(link_id, tag_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE links ADD CONSTRAINT FK_2CF8D8B7A76ED395 
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        
        $this->addSql('ALTER TABLE tags ADD CONSTRAINT FK_6FBC9426A76ED395 
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        
        $this->addSql('ALTER TABLE link_tags ADD CONSTRAINT FK_D194F33EADA40271 
            FOREIGN KEY (link_id) REFERENCES links (id) ON DELETE CASCADE');
        
        $this->addSql('ALTER TABLE link_tags ADD CONSTRAINT FK_D194F33EBAD26311 
            FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE link_tags DROP FOREIGN KEY FK_D194F33EADA40271');
        $this->addSql('ALTER TABLE link_tags DROP FOREIGN KEY FK_D194F33EBAD26311');
        $this->addSql('ALTER TABLE links DROP FOREIGN KEY FK_2CF8D8B7A76ED395');
        $this->addSql('ALTER TABLE tags DROP FOREIGN KEY FK_6FBC9426A76ED395');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE links');
        $this->addSql('DROP TABLE tags');
        $this->addSql('DROP TABLE link_tags');
    }
} 