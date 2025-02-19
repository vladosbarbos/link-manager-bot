# Link Manager Bot

[![CI](https://github.com/vladosbarbos/link-manager-bot/actions/workflows/ci.yml/badge.svg)](https://github.com/vladosbarbos/link-manager-bot/actions/workflows/ci.yml)
[![Static Analysis](https://github.com/vladosbarbos/link-manager-bot/actions/workflows/ci.yml/badge.svg?workflow=static-analysis)](https://github.com/vladosbarbos/link-manager-bot/actions/workflows/ci.yml)
[![Coding Standards](https://github.com/vladosbarbos/link-manager-bot/actions/workflows/ci.yml/badge.svg?workflow=coding-standards)](https://github.com/vladosbarbos/link-manager-bot/actions/workflows/ci.yml)

Telegram-бот для сохранения и организации ссылок на статьи и видео с системой тегов и умными рекомендациями.

## Возможности

- Сохранение ссылок на статьи и видео
- Система тегов для категоризации контента
- Персональные ежедневные напоминания
- Умная система рекомендаций на основе предпочтений пользователя
- Мультипользовательская поддержка

## Требования

- Docker и Docker Compose
- Git
- Зарегистрированный Telegram бот (получите токен у @BotFather)
- Домен с SSL-сертификатом (для работы вебхуков Telegram)

## Установка и настройка

### 1. Клонирование репозитория

```bash
git clone https://github.com/vladosbarbos/link-manager-bot.git
cd link-manager-bot
```

### 2. Настройка окружения

1. Создайте файл `.env.local`:

```env
APP_ENV=prod
APP_SECRET=your-secret-here # Измените на случайную строку
DATABASE_URL="mysql://user:password@database:3306/linkkeeper?serverVersion=8.0"
TELEGRAM_BOT_TOKEN=your-bot-token-here # Токен от @BotFather
TELEGRAM_WEBHOOK_SECRET=your-webhook-secret # Случайная строка для защиты вебхука
MYSQL_ROOT_PASSWORD=root-password-here
MYSQL_DATABASE=linkkeeper
MYSQL_USER=user
MYSQL_PASSWORD=password
```

### 3. Запуск проекта

1. Соберите и запустите контейнеры:

```bash
docker-compose build
docker-compose up -d
```

2. Установите зависимости:

```bash
docker-compose exec php composer install --no-dev --optimize-autoloader
```

3. Создайте базу данных и выполните миграции:

```bash
docker-compose exec php bin/console doctrine:database:create
docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

### 4. Настройка вебхука

Установите вебхук для бота:

```bash
docker-compose exec php bin/console app:setup-webhook https://your-domain.com
```

### 5. Настройка планировщика

Добавьте в crontab следующие задачи:

```cron
# Проверка и отправка уведомлений каждые 5 минут
*/5 * * * * /usr/bin/docker-compose -f /path/to/project/docker-compose.yml exec -T php bin/console app:send-daily-notifications
```

## Развертывание

### Настройка Nginx на сервере

Пример конфигурации Nginx для прокси-сервера:

```nginx
server {
    listen 443 ssl;
    server_name your-domain.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Обновление проекта

1. Получите последние изменения:

```bash
git pull origin main
```

2. Обновите зависимости:

```bash
docker-compose exec php composer install --no-dev --optimize-autoloader
```

3. Примените миграции:

```bash
docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

4. Очистите кеш:

```bash
docker-compose exec php bin/console cache:clear
docker-compose exec php bin/console cache:warmup
```

5. Перезапустите контейнеры:

```bash
docker-compose down
docker-compose up -d
```

### Устранение неполадок

#### Проблемы с правами доступа

Если возникают проблемы с правами доступа к файлам:

```bash
sudo chown -R www-data:www-data var/
sudo chmod -R 777 var/
```

#### Проблемы с подключением к базе данных

1. Проверьте доступность базы данных:

```bash
docker-compose exec database mysql -u user -p
```

2. Проверьте логи:

```bash
docker-compose logs database
docker-compose logs php
```

#### Проблемы с вебхуком

1. Проверьте SSL-сертификат:

```bash
curl -vI https://your-domain.com
```

2. Проверьте статус вебхука:

```bash
curl "https://api.telegram.org/bot{YOUR_BOT_TOKEN}/getWebhookInfo"
```

## Использование

### Основные команды бота

- `/start` - Начало работы с ботом
- `/add URL #тег1 #тег2` - Добавить новую ссылку с тегами
- `/list` - Показать все сохраненные ссылки
- `/tags` - Показать все ваши теги
- `/settime ЧЧ:ММ` - Установить время для ежедневных напоминаний
- `/recommendations` - Получить персональные рекомендации

### Примеры использования

```
/add https://symfony.com/doc/current/index.html #symfony #php #docs
/settime 10:00
/list #symfony
```

## Структура проекта

```
link-manager-bot/
├── docker/
│   ├── nginx/
│   │   └── default.conf
│   └── php/
│       └── Dockerfile
├── src/
│   ├── Command/
│   ├── Controller/
│   ├── Entity/
│   ├── Repository/
│   ├── Service/
│   └── EventSubscriber/
├── migrations/
├── .env
├── .env.local.example
├── .gitignore
├── composer.json
└── docker-compose.yml
```

## Разработка

### Локальное окружение

1. Используйте dev-окружение:

```bash
APP_ENV=dev
```

2. Установите dev-зависимости:

```bash
docker-compose exec php composer install
```

### Статический анализ и стиль кода

#### PHPStan (Статический анализ)

Проект использует PHPStan для статического анализа кода. 

#### Запуск проверок

```bash
# Базовая проверка
docker-compose exec php vendor/bin/phpstan analyse

# Подробный вывод с описанием ошибок
docker-compose exec php vendor/bin/phpstan analyse --error-format=table

# Вывод ошибок с указанием строк кода
docker-compose exec php vendor/bin/phpstan analyse --error-format=prettyJson

# Проверка с максимальным уровнем подробностей
docker-compose exec php vendor/bin/phpstan analyse -v

# Проверка с выводом прогресса
docker-compose exec php vendor/bin/phpstan analyse --debug

# Проверка конкретного файла или директории
docker-compose exec php vendor/bin/phpstan analyse src/Controller/HealthCheckController.php

# Генерация отчета в формате HTML
docker-compose exec php vendor/bin/phpstan analyse --error-format=html > phpstan-report.html
```

#### Уровни проверки

Можно указать уровень строгости проверки (0-9):

```bash
# Минимальная проверка
docker-compose exec php vendor/bin/phpstan analyse --level=0

# Максимальная строгость
docker-compose exec php vendor/bin/phpstan analyse --level=9
```

Текущий уровень проверки указан в `phpstan.neon` (сейчас уровень 8).

#### Игнорирование ошибок

Для игнорирования определенных ошибок можно использовать аннотации в коде:

```php
/** @phpstan-ignore-next-line */
$someCode = $this->doSomething();

/** @phpstan-ignore-line */
$result = $this->riskyOperation();
```

Или добавить правила исключения в `phpstan.neon`:

```yaml
parameters:
    ignoreErrors:
        - '#Parameter \#1 \$value of method .* expects string, mixed given.#'
```

#### Рекомендуемый рабочий процесс

1. Запустите PHP CS Fixer для автоматического исправления стиля кода:
```bash
docker-compose exec php vendor/bin/php-cs-fixer fix
```

2. Запустите PHPStan для проверки потенциальных проблем:
```bash
docker-compose exec php vendor/bin/phpstan analyse
```

3. Исправьте найденные PHPStan проблемы вручную
4. Повторите процесс при необходимости

### Запуск тестов

```bash
docker-compose exec php bin/phpunit
```

## Вклад в проект

1. Создайте форк проекта
2. Создайте ветку для новой функциональности
3. Отправьте пулл-реквест

## Лицензия

MIT License

## Поддержка

Если у вас возникли проблемы или есть предложения по улучшению:
1. Создайте Issue в репозитории
2. Опишите проблему или предложение
3. Приложите логи или скриншоты, если это необходимо

## Автор

[vladosbarbos](https://github.com/vladosbarbos)