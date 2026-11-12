COMPOSE = docker compose

# ── Application ─────────────────────────────────────────────────────────
up:      ## Build and start (http://localhost:8080)
	$(COMPOSE) up -d --build

down:    ## Stop
	$(COMPOSE) down

logs:    ## Application logs
	$(COMPOSE) logs -f app

ps:      ## Status
	$(COMPOSE) ps

# ── Tools (everything in containers, no local PHP needed) ──────────────
composer: ## make composer CMD="require ..." / "install"
	$(COMPOSE) run --rm composer $(CMD)

npm:     ## make npm CMD="install" / "run build"
	$(COMPOSE) run --rm npm $(CMD)

artisan: ## make artisan CMD="migrate" / "make:model ..."
	$(COMPOSE) run --rm artisan $(CMD)

test:    ## Tests (PHPUnit)
	$(COMPOSE) run --rm test

lint:    ## Static analysis and style check (phpstan + pint --test)
	$(COMPOSE) run --rm --entrypoint '' composer vendor/bin/phpstan analyse --no-progress
	$(COMPOSE) run --rm --entrypoint '' composer vendor/bin/pint --test

fmt:     ## Fix code style (pint)
	$(COMPOSE) run --rm --entrypoint '' composer vendor/bin/pint

migrate: ## Migrations
	$(COMPOSE) run --rm artisan migrate --force

fresh:   ## Recreate the DB (wipes everything! run with CONFIRM=1)
	@if [ "$${CONFIRM:-0}" != "1" ]; then \
		echo "make fresh wipes the DB beyond recovery."; \
		echo "Confirm with: make fresh CONFIRM=1"; \
		exit 1; \
	fi
	$(COMPOSE) run --rm artisan migrate:fresh --force

.PHONY: up down logs ps composer npm artisan test lint fmt migrate fresh
