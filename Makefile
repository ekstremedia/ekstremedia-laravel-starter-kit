ifneq (,$(wildcard ./.env))
include .env
export
endif

APP_SERVICE ?= app
DB_SERVICE ?= postgres

# Build display URL: append port only when set and not 80
ifeq ($(APP_HOST_PORT),)
DISPLAY_URL := $(APP_URL)
else ifeq ($(APP_HOST_PORT),80)
DISPLAY_URL := $(APP_URL)
else
DISPLAY_URL := $(APP_URL):$(APP_HOST_PORT)
endif

.PHONY: help init build up down restart shell test migrate seed fresh logs tinker pint queue vite npm-install composer-install cache-clear reverb-restart

# Default target
help: ## Show this help
	@echo "Laravel Starter Kit - Available commands:"
	@echo ""
	@grep -hE '^[a-zA-Z_-]+:.*## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":[^#]*## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

init: ## Initialize .env for a new project
	@[ -f .env ] || cp .env.example .env
	@bash scripts/init-starter.sh

# Docker commands
build: ## Build and start all containers
	docker compose up -d --build --renew-anon-volumes
	@echo ""
	@echo "  App:     $(DISPLAY_URL)"
	@echo "  Mailpit: http://localhost:$(MAILPIT_HOST_PORT)"
	@echo "  Reverb:  ws://$(VITE_REVERB_HOST):$(REVERB_PORT)"
	@echo ""

up: ## Start all containers
	docker compose up -d
	@echo ""
	@echo "  App:     $(DISPLAY_URL)"
	@echo "  Mailpit: http://localhost:$(MAILPIT_HOST_PORT)"
	@echo "  Reverb:  ws://$(VITE_REVERB_HOST):$(REVERB_PORT)"
	@echo ""

down: ## Stop all containers
	docker compose down

restart: ## Restart all containers
	docker compose restart

destroy: ## Stop containers and remove volumes
	docker compose down -v

# App commands
shell: ## Open a shell in the app container
	docker compose exec $(APP_SERVICE) bash

test: ## Run Pest tests
	docker compose exec $(APP_SERVICE) php artisan test

test-js: ## Run Vitest frontend tests
	docker compose exec $(APP_SERVICE) npm test

test-all: ## Run full CI locally (Pint, Larastan, Pest, tsc, Vitest)
	docker compose exec $(APP_SERVICE) vendor/bin/pint --test
	docker compose exec $(APP_SERVICE) vendor/bin/phpstan analyse --memory-limit=1G --no-progress
	docker compose exec $(APP_SERVICE) php artisan test --compact
	docker compose exec $(APP_SERVICE) npm run typecheck
	docker compose exec $(APP_SERVICE) npm test

migrate: ## Run database migrations
	docker compose exec $(APP_SERVICE) php artisan migrate

seed: ## Run database seeders
	docker compose exec $(APP_SERVICE) php artisan db:seed

fresh: ## Fresh migrate and seed
	docker compose exec $(APP_SERVICE) php artisan migrate:fresh --seed

rollback: ## Rollback last migration
	docker compose exec $(APP_SERVICE) php artisan migrate:rollback

# Development commands
tinker: ## Open Laravel Tinker
	docker compose exec $(APP_SERVICE) php artisan tinker

pint: ## Run Laravel Pint (code formatter)
	docker compose exec $(APP_SERVICE) ./vendor/bin/pint

stan: ## Run Larastan static analysis
	docker compose exec $(APP_SERVICE) ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress

ide-helper: ## Regenerate IDE helper files (facades, models, meta) for PhpStorm
	docker compose exec $(APP_SERVICE) php artisan ide-helper:generate
	docker compose exec $(APP_SERVICE) php artisan ide-helper:meta
	docker compose exec $(APP_SERVICE) php artisan ide-helper:models --nowrite --reset

backup: ## Run a manual backup
	docker compose exec $(APP_SERVICE) php artisan backup:run

backup-clean: ## Clean old backups
	docker compose exec $(APP_SERVICE) php artisan backup:clean

logs: ## Show app container logs
	docker compose logs -f $(APP_SERVICE)

logs-all: ## Show all container logs
	docker compose logs -f

queue: ## Start queue worker
	docker compose exec $(APP_SERVICE) php artisan queue:work

reverb-restart: ## Restart Laravel Reverb
	docker compose exec $(APP_SERVICE) php artisan reverb:restart

cache-clear: ## Clear all caches
	docker compose exec $(APP_SERVICE) php artisan optimize:clear

# Install commands
composer-install: ## Run composer install in container
	docker compose exec $(APP_SERVICE) composer install

npm-install: ## Run npm install in container
	docker compose exec $(APP_SERVICE) npm install

npm-build: ## Build frontend assets
	docker compose exec $(APP_SERVICE) npm run build

vite: ## Run Vite dev server
	docker compose exec $(APP_SERVICE) npm run dev

# Database commands
db-shell: ## Open PostgreSQL shell
	docker compose exec $(DB_SERVICE) psql -U $(DB_USERNAME) -d $(DB_DATABASE)
