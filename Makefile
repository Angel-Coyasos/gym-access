# ─── Gym Access System — Comandos de desarrollo ──────────────────────────────

.PHONY: help up down build restart logs shell artisan migrate fresh test

help: ## Muestra esta ayuda
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

# ─── Docker ───────────────────────────────────────────────────────────────────

up: ## Levanta todos los contenedores
	docker compose up -d

up-logs: ## Levanta y muestra logs en tiempo real
	docker compose up

down: ## Detiene y elimina contenedores
	docker compose down

build: ## Reconstruye las imágenes
	docker compose build --no-cache

restart: ## Reinicia los contenedores
	docker compose restart

logs: ## Muestra los logs de la app
	docker compose logs -f app

logs-worker: ## Muestra los logs del queue worker
	docker compose exec app tail -f /var/log/supervisor/queue-worker.log

logs-outbox: ## Muestra los logs del outbox worker
	docker compose exec app tail -f /var/log/supervisor/outbox-worker.log

# ─── Laravel ──────────────────────────────────────────────────────────────────

shell: ## Abre una terminal dentro del contenedor app
	docker compose exec app bash

artisan: ## Ejecutar comando artisan. Uso: make artisan CMD="route:list"
	docker compose exec app php artisan $(CMD)

migrate: ## Corre las migraciones
	docker compose exec app php artisan migrate

fresh: ## Resetea la DB y corre migraciones + seeders
	docker compose exec app php artisan migrate:fresh --seed

# ─── Tests ────────────────────────────────────────────────────────────────────

test: ## Corre todos los tests
	docker compose exec app php artisan test

test-unit: ## Corre solo los tests unitarios
	docker compose exec app php artisan test --testsuite=Unit

test-integration: ## Corre solo los tests de integración
	docker compose exec app php artisan test --testsuite=Integration