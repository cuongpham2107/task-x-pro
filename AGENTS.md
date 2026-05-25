# TaskXPro — Agent Guide

## App Overview

Task/ project management system (Vietnamese UI). Custom Livewire 4 + Alpine.js + Tailwind v4 frontend (no Filament pages despite having filament packages in composer.json). Uses `spatie/laravel-permission` for RBAC.

## Key Architecture

- **No controllers** — single `GlobalSearchController` in `app/Http/Controllers/Api/`, everything else is Livewire pages registered in `routes/web.php` via `Route::livewire('/path', 'pages::name.here')`.
- **Service layer pattern** per domain: `{Domain}QueryService`, `{Domain}MutationService`, `{Domain}Service`. Bind to interfaces in `AppServiceProvider::register()` (e.g. `DocumentServiceInterface`).
- **UI theme defined in `resources/css/app.css`** — custom `@theme` block with `--color-primary`, `--font-display` etc. Component classes: `.section-card`, `.input-field`, `.kanban-column`, `.calendar-grid`, `.custom-scrollbar`. All Tailwind v4 (CSS config, no `tailwind.config.*`).
- **Blaze** optimizes UI components: `Blaze::optimize()->in(resource_path('views/components/ui'))` in `AppServiceProvider::boot()`.
- **Progress cascade**: Task → Phase → Project. Models use `booted()` hooks with `saving`/`saved`/`deleted` events, `refreshProgressFromTasks()`, `refreshProgressFromPhases()`, `KpiScore::syncForUser()`.

## Conventions

- **Locale**: Vietnamese (`APP_LOCALE=vi`). Labels, comments, notifications, error messages all in Vietnamese.
- **Livewire page naming**: Route key matches directory/Blade file. `Route::livewire('/activity-logs', 'pages::activity-logs.index')` → `resources/views/pages/activity-logs/index.blade.php`.
- **Enum pattern**: All enums in `app/Enums/` use `HasEnumOptions` trait + `label()` method. Most have `icon()`, `color()`, `dotClass()`, `textColor()`, `badgeClass()` for UI rendering.
- **Model conventions**: `casts()` method (not `$casts` property), `HasFactory` + `SoftDeletes`, `$fillable` array (not `$guarded`).
- **Route prefixes**: kebab-case (`phase-templates`, `sla-configs`, `activity-logs`, `kpi-scores`).
- **Activity logging**: Task model logs created/status_updated/progress_updated changes automatically via `booted()`.
- **Soft deletes**: Most models use `SoftDeletes`. Respect when querying.

## Notification Channels

- **Telegram** via `laravel-notification-channels/telegram` + `TELEGRAM_BOT_NAME` / `TELEGRAM_TOKEN` env vars.
- Social auth via `socialiteproviders/telegram` (Telegram OAuth).
- Notifications: `TaskAssignedNotification`, `TaskDeadlineReminderNotification`, `TaskApprovalPendingReminderNotification`, `WeeklySummaryNotification`, `MonthlyKpiSummaryNotification`, `PicOverdueTasksNotification`.

## Console Commands & Schedule

Commands in `routes/console.php` (all auto-discovered, no manual registration):

| Command | Schedule | Purpose |
|---|---|---|
| `tasks:mark-late` | daily 07:00 | Mark overdue tasks as late |
| `projects:mark-overdue` | daily 07:00 | Auto-set overdue projects |
| `tasks:daily-reminders` | daily 07:00 | Deadline reminders, pending approval reminders, PIC overdue warnings |
| `reports:weekly` | Fridays 17:00 | Weekly summary to leader/ceo roles |
| `kpi:daily-sync` | daily 01:00 | Recalculate KPI scores for active users |
| `kpi:monthly-sync` | 1st + 2nd of month 02:00/03:00 | Monthly KPI sync + notification to leader/ceo |
| `kpi:backfill-missing-months` | 1st of month 04:00 | Backfill missing months |
| `progress:refresh-all` | manual | Recalculate progress for all phases + projects |

Additional commands: `app/Console/Commands/KpiBackfillMissingMonths.php`, `RegisterDompdfFonts.php`.

## Docker / Deploy

- `docker-compose.yml` with `app` (PHP-FPM), `scheduler`, `web` (Nginx), `db` (MySQL 8.0).
- `make deploy` = `build up deps key migrate seed refresh-progress`.
- `make deps` installs PHP + Node deps inside container. `make seed` runs `db:seed`.
- Local dev uses SQLite (`DB_CONNECTION=sqlite`); Docker uses MySQL.

## Testing

- **Pest** tests in `tests/Feature/`. Run: `composer test` or `php artisan test --compact`.
- Filter: `php artisan test --compact --filter=testName`.
- Create test: `php artisan make:test --pest {Name}`.
- DB defaults to `:memory:` SQLite in testing env (configured in `phpunit.xml`).
- Named test groups exist for Services, Policies, Notifications (see subdirectories in `tests/Feature/`).

## Verification

- After editing PHP: `vendor/bin/pint --format agent` (auto-fixes styling).
- After editing JS/CSS: `npm run build` to rebuild Vite manifest.

## Other Stack Details

- `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database` — all use the same MySQL/SQLite DB.
- **spatie/laravel-medialibrary** for document uploads (DocumentVersion model uses `->getFirstMedia('version_file')`).
- **spatie/laravel-pdf** + **dompdf** for PDF generation (see `app/Helpers/PdfHelper.php`).
- **maatwebsite/excel** for exports (see `app/Exports/`).
- **Flowbite** for JS components (datepicker, etc). Alpine.js configured in `resources/js/app.js`.
- Google Material Symbols for icons (loaded from Google Fonts CSS).
- `TELEGRAM_REDIRECT_URI=/auth/telegram/callback` for Telegram OAuth.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v12
- laravel/mcp (MCP) - v0
- laravel/prompts (PROMPTS) - v0
- laravel/socialite (SOCIALITE) - v5
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `mcp-development` — Use this skill for Laravel MCP development only. Trigger when creating or editing MCP tools, resources, prompts, or servers in Laravel projects. Covers: artisan make:mcp-* generators, mcp:inspector, routes/ai.php, Tool/Resource/Prompt classes, schema validation, shouldRegister(), OAuth setup, URI templates, read-only attributes, and MCP debugging. Do not use for non-Laravel MCP projects or generic AI features without MCP.
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit, tests/Browser), or needs browser testing, smoke testing multiple pages for JS errors, or architecture tests. Covers: it()/expect() syntax, datasets, mocking, browser testing (visit/click/fill), smoke testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audit, build tool configuration, and vanilla CSS.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan Commands

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`, `php artisan tinker --execute "..."`).
- Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Debugging

- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.
- To execute PHP code for debugging, run `php artisan tinker --execute "your code here"` directly.
- To read configuration values, read the config files directly or run `php artisan config:show [key]`.
- To inspect routes, run `php artisan route:list` directly.
- To check environment variables, read the `.env` file directly.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
</laravel-boost-guidelines>
