.PHONY: up down restart build status logs test

up:
	docker compose up -d --build

down:
	docker compose down

restart:
	docker compose down
	docker compose up -d --build

build:
	docker compose build

status:
	docker compose ps

logs:
	docker compose logs -f

test:
	docker run --rm -v "$(CURDIR):/app" -w /app php:8.3-cli php testes/test_algoritmos.php
