# ═══════════════════════════════════════════════════════════════════
#  YL Laravel — Makefile
#  Usage: make <target>
# ═══════════════════════════════════════════════════════════════════

# ── Build & run ───────────────────────────────────────────────────

build:
	docker-compose up --build

up:
	docker-compose up

down:
	docker-compose down

reset:
	docker-compose down -v

rebuild-server1:
	docker-compose up --build server1 worker1

rebuild-server2:
	docker-compose up --build server2 worker2

rebuild-server3:
	docker-compose up --build server3 worker3

autoload:
	docker exec yl_server3 composer dump-autoload

# ── Logs ──────────────────────────────────────────────────────────

logs:
	docker-compose logs -f

logs-server1:
	docker-compose logs -f server1

logs-server2:
	docker-compose logs -f server2

logs-server3:
	docker-compose logs -f server3

# ── Shell access ──────────────────────────────────────────────────

sh-server1:
	docker exec -it yl_server1 bash

sh-server2:
	docker exec -it yl_server2 bash

sh-server3:
	docker exec -it yl_server3 bash

# ── Tests ─────────────────────────────────────────────────────────

test:
	docker exec yl_server3 php artisan test

test-products:
	docker exec yl_server3 php artisan test --filter ProductCrudTest

test-posts:
	docker exec yl_server3 php artisan test --filter PostCrudTest

test-helper:
	docker exec yl_server3 php artisan test --filter ApiResponseTest

test-arch:
	docker exec yl_server3 vendor/bin/phparkitect check --config=/var/www/html/phparkitect.php

test-all: test test-arch

# ── Artisan shortcuts ─────────────────────────────────────────────

migrate:
	docker exec yl_server3 php artisan migrate

tinker:
	docker exec -it yl_server3 php artisan tinker

swagger:
	docker exec yl_server3 php artisan l5-swagger:generate

init-laravel:
	docker run --rm -v $(PWD):/app composer:2.7 \
		create-project laravel/laravel /app/laravel-tmp --prefer-dist --no-interaction
	cp -rn laravel-tmp/. .
	rm -rf laravel-tmp
