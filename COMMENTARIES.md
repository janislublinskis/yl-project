# Implementation Documentation — YL Laravel Modular Project

This document outlines the sequence in which each part of the project was planned, built, and configured. It covers architectural decisions, the reasoning behind each step, and known areas (Tests, Arkitect) that require further tuning before they are fully operational.

---

## 1. Project Scaffold — Laravel Skeleton

The starting point was an empty directory. A fresh Laravel 11.5 application was initialised using the official installer, which created the standard skeleton: `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, and the root `composer.json`.

The root `composer.json` was then stripped back so it only declares the Laravel framework itself and shared tooling (PHPUnit, Swagger, Arkitect). It intentionally does **not** reference any of the domain packages — those are injected per-server at build time.

`bootstrap/cache/services.php` and `bootstrap/cache/packages.php` are excluded from version control via `.gitignore`. These cached service-provider manifests caused container startup failures when they were built from one Composer configuration and then a different one was overlaid at runtime.

---

## 2. Defining the Modular Architecture

The assignment required three independent Composer packages living under `packages/yl/`:

| Package | Namespace | Responsibility |
|---|---|---|
| `yl/helper` | `Yl\Helper` | Shared base classes and traits |
| `yl/products` | `Yl\Products` | Product domain (CRUD + queue job) |
| `yl/posts` | `Yl\Posts` | Post domain (CRUD + queue job) |

Each package has its own `composer.json`, `src/` tree, `routes/api.php`, `database/migrations/`, and `tests/`. A `ServiceProvider` in each domain package bootstraps its routes and migrations so that Laravel discovers them automatically through the `extra.laravel.providers` key — no manual registration in `config/app.php` is required.

The `yl/helper` package is declared as a dependency of both `yl/products` and `yl/posts`, enforcing that shared infrastructure flows in one direction only.

---

## 3. The `yl/helper` Package

**`ApiResponse`** — a static utility class that wraps every outgoing response in a consistent JSON envelope:

```php
{
  "success": true|false,
  "message": "...",
  "data": {...},
  "errors": [...]
}
```

All controllers return responses through this class, which means the API surface is uniform regardless of which domain is handling the request.

**`BaseApiController`** — an abstract controller that extends Laravel's base `Controller` and exposes convenience methods (`success()`, `error()`, `notFound()`, etc.) backed by `ApiResponse`. Every domain controller extends this class instead of the framework controller directly. This is also where the top-level `@OA\Info` Swagger annotation lives, because Swagger requires exactly one info block and the helper is always present on every server.

**`LogsActivity`** — a model trait that hooks into Eloquent's `created`, `updated`, and `deleted` events and writes a structured log entry. Models opt in by using the trait; no separate observer registration is needed.

**`HasTimestampScopes`** — a model trait providing reusable query scopes: `scopeRecent()`, `scopeCreatedAfter($date)`, `scopeUpdatedAfter($date)`. These avoid repeating the same `where` clauses across controllers and keep the query intent readable.

**`HelperServiceProvider`** — registered via `composer.json` so Laravel auto-discovers it. It performs no heavy bootstrapping; its main job is to confirm the package is loaded and provide a hook point for future shared config publishing.

---

## 4. The `yl/products` Package

**`Product` model** — Eloquent model with fillable fields: `name`, `description`, `price`, `stock`, `status` (enum: `active`, `inactive`, `archived`). It uses both `LogsActivity` and `HasTimestampScopes` from the helper package.

**Migration** — filename `2024_01_01_000001_create_products_table.php`. The explicit early timestamp ensures the products table is always created before any table that might reference it.

**`ProductController`** — extends `BaseApiController`. Implements the full REST surface:

- `GET /api/products` — paginated index
- `POST /api/products` — store with validation
- `GET /api/products/{id}` — show or 404
- `PUT /api/products/{id}` — update with validation
- `DELETE /api/products/{id}` — soft or hard delete

Each method is annotated with `@OA\Get`, `@OA\Post`, etc. for Swagger generation.

**`ProcessProductExportJob`** — implements `ShouldQueue`. When dispatched it makes an HTTP POST to a configurable webhook URL (simulating an export notification) and runs a shell command to append a line to a sitemap log file (simulating a static-file rebuild). Both side-effects are intentional demonstrations of a job doing I/O beyond just database writes.

**Routes** — declared in `packages/yl/products/routes/api.php` and loaded by `ProductsServiceProvider` using `$this->loadRoutesFrom()`.

---

## 5. The `yl/posts` Package

**`Post` model** — fields: `title`, `slug` (auto-generated from the title in a `creating` observer on the model), `body`, `status` (enum: `draft`, `published`, `archived`), `published_at` (nullable timestamp set automatically when status transitions to `published`). Uses both helper traits.

**Migration** — `2024_01_01_000002_create_posts_table.php`. Sequenced after the products migration.

**`PostController`** — same REST structure as `ProductController`. The `store` and `update` methods contain the slug-generation logic as a fallback if the model observer has not yet fired (defensive programming).

**`ProcessPostPublishedJob`** — implements `ShouldQueue`. Posts to a search-indexer endpoint and runs a cache-flush shell command. This mirrors the product job structure, making both domains symmetrical in their async behaviour.

**Routes** — loaded by `PostsServiceProvider` via `$this->loadRoutesFrom()`.

---

## 6. Docker Multi-Server Setup

### Single Dockerfile, Multiple Configurations

A single `Dockerfile` builds all server images. A build argument `ARG SERVER=server3` controls which per-server `composer.json` is overlaid onto the root skeleton before `composer install` runs:

```dockerfile
ARG SERVER=server3
COPY docker/${SERVER}/composer.json ./composer.json
```

This means:

- **server1** installs `yl/helper` + `yl/products` only — Posts routes and models are not present.
- **server2** installs `yl/helper` + `yl/posts` only — Products routes and models are not present.
- **server3** installs all three packages — the full API surface.

Composer enforces the boundary: if a class from an uninstalled package is referenced, PHP will throw a fatal error rather than silently returning empty results.

### PHP Extensions

The image installs the `amqp` extension via PECL (required for RabbitMQ) alongside the standard Laravel extensions: `pdo_mysql`, `mbstring`, `bcmath`, `gd`, `zip`, `sockets`, `pcntl`.

### `entrypoint.sh`

The entrypoint runs in this sequence:

1. Fix `storage/` ownership and permissions at runtime (Docker volume mounts can reset these).
2. Touch `storage/logs/laravel.log` and set correct permissions so Monolog can write immediately.
3. Generate `APP_KEY` if `APP_KEY` is empty in the environment.
4. Poll MySQL with a PDO connection attempt in a loop until the database is healthy before proceeding.
5. Unless `SKIP_MIGRATE=true`, run `php artisan migrate --force --graceful`. The `--graceful` flag prevents the process from failing if the migration table already exists or another container is mid-migration.
6. Unless `SKIP_MIGRATE=true`, publish Swagger assets (without `--force` so a customised `config/l5-swagger.php` is not overwritten) and generate the Swagger JSON.
7. Hand off to `supervisord`, which manages both `php-fpm` and `nginx` as supervised processes.

### Worker Containers

Each domain has a corresponding worker container (`worker1`, `worker2`, `worker3`). Workers share the same Docker image as their server counterpart but override the default command:

```yaml
command: ["php", "artisan", "queue:work", "rabbitmq", "--queue=default", "--sleep=3", "--tries=3", "--timeout=90"]
```

Workers also set `SKIP_MIGRATE: "true"` so they skip the migration and Swagger steps — those are the server's responsibility. Workers start only after the server container's healthcheck passes.

For a summary of all containers, ports, and installed modules see the **Servers & Modules** table in `README.md`.

---

## 7. RabbitMQ Queue Integration

The `vladimir-yuldashev/laravel-queue-rabbitmq` package (v14, supporting up to Laravel 12) is declared in every server's `composer.json`. The connection is configured in `config/queue.php` and driven by environment variables:

```
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_QUEUE=default
RABBITMQ_QUEUE_DURABLE=true
```

`RABBITMQ_QUEUE_DURABLE=true` ensures the queue survives a RabbitMQ restart. On first boot, before any job is dispatched, RabbitMQ will report "no queue named default" — this is expected and normal. The queue is declared lazily on first use.

Jobs (`ProcessProductExportJob`, `ProcessPostPublishedJob`) are dispatched with `dispatch()` from their respective controllers after a successful write. They implement Laravel's `ShouldQueue` contract and are routed to the `rabbitmq` connection automatically.

---

## 8. Swagger / OpenAPI Documentation

The `darkaonline/l5-swagger` package (v8.6) generates an OpenAPI 3.0 specification from PHP docblock annotations.

**Annotation scanning** is configured in `config/l5-swagger.php` to scan `vendor/yl/` — the path where Composer installs the packages at runtime — rather than `packages/yl/`. This is the critical distinction: each server only has its own modules in `vendor/yl/`, so Swagger will not try to document classes that are not installed. When scanning was pointed at `packages/yl/` (the monorepo source), server1 would fail trying to parse `PostController` (which is not installed), and vice versa.

Test and database directories are excluded from the scan:

```php
'exclude' => [
    base_path('vendor/yl/helper/tests'),
    base_path('vendor/yl/products/tests'),
    base_path('vendor/yl/posts/tests'),
    base_path('vendor/yl/helper/database'),
    base_path('vendor/yl/products/database'),
    base_path('vendor/yl/posts/database'),
],
```

The `@OA\Info` block lives in `BaseApiController` (always present). Each controller method carries its own `@OA\Get`/`@OA\Post`/`@OA\Put`/`@OA\Delete` annotation with request bodies and response schemas.

Swagger UI is accessible at `http://localhost:800{1,2,3}/api/documentation`.

---

## 9. Makefile

A `Makefile` was added to reduce the cognitive overhead of common Docker and development commands. The full list of targets is documented in `README.md` under **Useful Commands**, and the targets themselves are readable directly in the `Makefile`.

---

## 10. phparkitect Architecture Tests

`phparkitect/phparkitect` (v0.8) is used to enforce architectural boundaries as automated checks. The rules are defined in `phparkitect.php` at the project root:

- `Yl\Products` must not depend on `Yl\Posts` (and vice versa) — domain isolation.
- `Yl\Helper` must not depend on `Yl\Products` or `Yl\Posts` — the helper is a leaf dependency, not a consumer.
- All classes in `*\Http\Controllers` must extend `BaseApiController`.
- All classes in `*\Jobs` must implement `ShouldQueue`.
- Controller class names must end in `Controller`, job class names must end in `Job`.

### ⚠️ Tuning Required

The Arkitect checks are written and structurally correct but **have not been run to a passing state yet**. Known issues to resolve before `make arch-test` goes green:

- The path configuration in `phparkitect.php` needs to point at the correct source directories (either `packages/yl/` for monorepo source or `vendor/yl/` for installed packages — the two differ between local dev and container).
- Arkitect's namespace resolver can struggle with PSR-4 autoloading when classes are installed as Composer path-repositories. The `phparkitect.php` config may need a custom loader or an explicit namespace-to-path map.
- Rule violations from transitional code (e.g. any controller that was scaffolded before `BaseApiController` existed) need to be cleaned up first.

---

## 11. PHPUnit Tests

Each package contains a `tests/` directory with a PHPUnit test class:

- `packages/yl/helper/tests/Unit/ApiResponseTest.php` — unit tests for the JSON envelope shape.
- `packages/yl/products/tests/Feature/ProductCrudTest.php` — 9 feature tests covering all CRUD endpoints, validation rules, and 404 handling.
- `packages/yl/posts/tests/Feature/PostCrudTest.php` — 12 feature tests including slug auto-generation, status transition to `published`, and `published_at` population.

Tests use Laravel's `RefreshDatabase` trait and an in-memory SQLite database configured in `phpunit.xml`.

### ⚠️ Tuning Required

The tests are written but **require configuration tuning before they pass in the containerised environment**:

- `phpunit.xml` must be updated with the correct `bootstrap` path and the `<testsuites>` entries pointing at `packages/yl/*/tests/`.
- When running inside Docker, the SQLite in-memory database config (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) must be active. The current `.env` files point at MySQL, which is correct for runtime but causes test isolation issues unless the test environment overrides it.
- Packages installed as Composer path-repositories (using `"type": "path"`) are symlinked into `vendor/`. PHPUnit's autoloader needs to resolve classes from those symlinked paths, which can occasionally require a `composer dump-autoload` inside the container before tests run.
- The `ProcessProductExportJob` and `ProcessPostPublishedJob` tests should use `Queue::fake()` to avoid making real HTTP calls or shell executions during CI.

Once the above are resolved, `make test` should produce a green suite.

---

## Dependency Version Notes

Several version conflicts were encountered and resolved during the build process:

| Package | Constraint Used | Reason |
|---|---|--|
| `laravel/framework` | `^12.0` | `laravel-queue-rabbitmq ^14` |
| `vladimir-yuldashev/laravel-queue-rabbitmq` | `^14.0` |
| `darkaonline/l5-swagger` | `^8.6` | Stable with Laravel 11.5 and PHP 8.3 |
| `phparkitect/phparkitect` | `^0.8` | v0.8 is the current stable |
| `phpunit/phpunit` | `^12.0` | Required for PHP 8.3 compatibility |
| PHP base image | `php:8.3-fpm` | Laravel 11.5 requires PHP ≥ 8.2; 8.3 chosen for longevity |

---

## 12. Design Decisions

### Why path repositories instead of a private Packagist?
Path repos resolve locally — no need to publish packages publicly during
development. In production you would switch to a private registry
(e.g. Private Packagist, Satis) and the `composer.json` would simply change
the repository URL.

### Why one Dockerfile with an ARG instead of three separate Dockerfiles?
DRY — a single Dockerfile is easier to maintain. The `ARG SERVER` build
argument selects which `composer.json` gets copied into the image at build time.
Docker layer caching means only the changed `composer.json` layer rebuilds.

### Why soft deletes?
Soft deletes protect against accidental data loss and provide a natural
audit trail. Permanently deleted records are gone — soft deletes allow
recovery. Both modules use `SoftDeletes`.

### Why is the job retry count set in both the job class and the worker CLI?
The job class (`$tries = 3`) defines the *default*. The worker CLI flag
(`--tries=3`) acts as a *ceiling*. Having both set explicitly makes the
retry behaviour clear regardless of how the worker is invoked.

---

## Summary

The project was built in this sequence:

1. Laravel skeleton initialised and stripped to a minimal root `composer.json`.
2. `yl/helper` package created with shared response, controller, and model utilities.
3. `yl/products` and `yl/posts` packages created with full CRUD, migrations, and queue jobs.
4. Per-server `composer.json` files written to enforce module isolation.
5. Single `Dockerfile` with `ARG SERVER` build argument created.
6. `entrypoint.sh` written to handle key generation, DB waiting, migrations, and Swagger generation.
7. `docker-compose.yml` assembled with six application containers, MySQL, and RabbitMQ.
8. Swagger scanning reconfigured to point at `vendor/yl/` with test/database exclusions.
9. `phparkitect.php` written with boundary rules — **requires path tuning to run**.
10. PHPUnit test classes written for all three packages — **requires environment tuning to pass**.
11. `Makefile` added to wrap all common operations.
