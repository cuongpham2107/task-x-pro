APP_CONTAINER ?= taskxpro-app

.PHONY: help build up deps key migrate seed refresh-progress deploy
.DEFAULT_GOAL := help

help: ## Show available targets
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

build: ## Build Docker images
	docker compose build

up: ## Start containers (detached)
	docker compose up -d

deps: ## Install PHP/Node deps + build assets (bind-mount overlays image's vendor/)
	docker exec $(APP_CONTAINER) composer install --no-interaction --optimize-autoloader
	docker exec $(APP_CONTAINER) npm install
	docker exec $(APP_CONTAINER) npm run build

key: ## Generate APP_KEY (no-op if already set)
	docker exec $(APP_CONTAINER) php artisan key:generate

migrate: ## Run database migrations
	docker exec $(APP_CONTAINER) php artisan migrate --force

seed: ## Seed the database
	docker exec $(APP_CONTAINER) php artisan db:seed --force

refresh-progress: ## Refresh all progress data
	docker exec $(APP_CONTAINER) php artisan progress:refresh-all

deploy: build up deps key migrate seed refresh-progress ## Full deploy pipeline
	@echo "✓ Deploy complete"
