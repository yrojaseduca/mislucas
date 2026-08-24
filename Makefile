.PHONY: up down install migrate seed test lint build shell
up:
	docker compose up -d --build
down:
	docker compose down
install:
	docker compose run --rm php composer install
	docker compose run --rm node npm install
migrate:
	docker compose exec php php artisan migrate
seed:
	docker compose exec php php artisan db:seed
test:
	docker compose exec php php artisan test
lint:
	docker compose exec php ./vendor/bin/pint --test
build:
	docker compose run --rm node npm run build
shell:
	docker compose exec php sh
