.PHONY: help up down restart build logs status clean migrate seed shell php-shell db-shell

# Colors
BLUE := \033[0;34m
GREEN := \033[0;32m
YELLOW := \033[0;33m
NC := \033[0m # No Color

help: ## Mostra questo messaggio di aiuto
	@echo "$(BLUE)BookForEveryone - Comandi Disponibili$(NC)"
	@echo "======================================"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "$(GREEN)%-20s$(NC) %s\n", $$1, $$2}'

up: ## Avvia tutti i servizi Docker
	@echo "$(YELLOW)Avvio servizi...$(NC)"
	docker-compose up -d
	@echo "$(GREEN)✓ Servizi avviati!$(NC)"
	@echo "  → Applicazione: http://localhost:8000"
	@echo "  → Mercure: http://localhost:3000"
	@echo "  → MailPit: http://localhost:8025"

build: ## Costruisce e avvia tutti i servizi
	@echo "$(YELLOW)Build e avvio servizi...$(NC)"
	docker-compose up -d --build
	@echo "$(GREEN)✓ Build completato e servizi avviati!$(NC)"

down: ## Ferma tutti i servizi
	@echo "$(YELLOW)Arresto servizi...$(NC)"
	docker-compose down
	@echo "$(GREEN)✓ Servizi arrestati!$(NC)"

restart: ## Riavvia tutti i servizi
	@echo "$(YELLOW)Riavvio servizi...$(NC)"
	docker-compose restart
	@echo "$(GREEN)✓ Servizi riavviati!$(NC)"

logs: ## Mostra i logs di tutti i servizi
	docker-compose logs -f

logs-php: ## Mostra i logs del container PHP
	docker-compose logs -f php

logs-nginx: ## Mostra i logs di Nginx
	docker-compose logs -f nginx

logs-db: ## Mostra i logs del database
	docker-compose logs -f database

status: ## Mostra lo stato dei servizi
	docker-compose ps

clean: ## Rimuove tutti i container, volumi e immagini
	@echo "$(YELLOW)Pulizia completa...$(NC)"
	docker-compose down -v --remove-orphans
	@echo "$(GREEN)✓ Pulizia completata!$(NC)"

shell: ## Apre una shell nel container PHP
	docker-compose exec php bash

php-shell: ## Alias per shell
	@make shell

db-shell: ## Apre una shell MySQL nel database
	docker-compose exec database mysql -u bookforeveryone -pbookforeveryone bookforeveryone

migrate: ## Esegue le migrazioni del database
	@echo "$(YELLOW)Esecuzione migrazioni...$(NC)"
	docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction
	@echo "$(GREEN)✓ Migrazioni completate!$(NC)"

seed: ## Popola il database con dati di test
	@echo "$(YELLOW)Popolamento database...$(NC)"
	docker-compose exec php php bin/console app:populate-users
	docker-compose exec php php bin/console app:populate-books
	docker-compose exec php php bin/console app:populate-likes
	docker-compose exec php php bin/console app:set-user-locations
	@echo "$(GREEN)✓ Database popolato!$(NC)"

cache-clear: ## Pulisce la cache di Symfony
	docker-compose exec php php bin/console cache:clear

cache-warmup: ## Riscalda la cache di Symfony
	docker-compose exec php php bin/console cache:warmup

composer-install: ## Installa le dipendenze Composer
	docker-compose exec php composer install

composer-update: ## Aggiorna le dipendenze Composer
	docker-compose exec php composer update

test: ## Esegue i test
	docker-compose exec php php bin/phpunit

setup: build migrate seed ## Setup completo (build + migrate + seed)
	@echo "$(GREEN)✓ Setup completo!$(NC)"
	@echo "  → Applicazione: http://localhost:8000"

fresh: clean setup ## Reset completo e setup da zero

# Default target
.DEFAULT_GOAL := help
