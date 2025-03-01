.PHONY: up down build install test cs-fix cs-check phpstan ci help

# Цвета для вывода
GREEN  := $(shell tput -Txterm setaf 2)
YELLOW := $(shell tput -Txterm setaf 3)
WHITE  := $(shell tput -Txterm setaf 7)
RESET  := $(shell tput -Txterm sgr0)

TARGET_MAX_CHAR_NUM=20

## Показать помощь
help:
	@echo ''
	@echo 'Доступные команды:'
	@echo ''
	@awk '/^[a-zA-Z\-\_0-9]+:/ { \
		helpMessage = match(lastLine, /^## (.*)/); \
		if (helpMessage) { \
			helpCommand = substr($$1, 0, index($$1, ":")-1); \
			helpMessage = substr(lastLine, RSTART + 3, RLENGTH); \
			printf "  ${YELLOW}%-$(TARGET_MAX_CHAR_NUM)s${RESET} ${GREEN}%s${RESET}\n", helpCommand, helpMessage; \
		} \
	} \
	{ lastLine = $$0 }' $(MAKEFILE_LIST)
	@echo ''

## Запустить контейнеры
up:
	docker-compose up -d

## Остановить контейнеры
down:
	docker-compose down

## Пересобрать контейнеры
build:
	docker-compose build
	docker-compose up -d

## Установить зависимости
install:
	docker-compose exec php composer install

## Запустить тесты
test:
	docker-compose exec php bin/phpunit

## Исправить стиль кода
cs-fix:
	docker-compose exec php vendor/bin/php-cs-fixer fix

## Проверить стиль кода
cs-check:
	docker-compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff

## Запустить статический анализ
phpstan:
	docker-compose exec php vendor/bin/phpstan analyse -c phpstan.neon

## Запустить все проверки (CI)
ci: validate-composer cs-check phpstan test

## Проверить composer.json
validate-composer:
	docker-compose exec php composer validate

## Запустить полную проверку CI (как в GitHub Actions)
ci-full: validate-composer prepare-test-env cs-check phpstan test

## Подготовить окружение для тестов
prepare-test-env:
	docker-compose exec php rm -rf var/cache/* var/test.db
	docker-compose exec php mkdir -p var
	docker-compose exec php touch var/test.db
	docker-compose exec php bin/console --env=test doctrine:schema:create
	docker-compose exec php bin/console cache:clear --env=test
	docker-compose exec php bin/console cache:warmup --env=test

## Очистить кеш
cache-clear:
	docker-compose exec php bin/console cache:clear
	docker-compose exec php bin/console cache:warmup

## Создать и применить миграции
db-migrate:
	docker-compose exec php bin/console doctrine:migrations:diff
	docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction

## Установить вебхук для бота (требуется указать домен)
setup-webhook:
	@read -p "Введите домен (например, https://example.com): " domain; \
	docker-compose exec php bin/console app:setup-webhook $$domain

## Полная переустановка проекта
reset: down
	docker-compose up -d --build
	docker-compose exec php composer install
	docker-compose exec php bin/console doctrine:database:drop --force --if-exists
	docker-compose exec php bin/console doctrine:database:create
	docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction
	docker-compose exec php bin/console cache:clear
	@echo "${GREEN}Проект успешно переустановлен${RESET}" 