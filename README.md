# YL Laravel Modular Project

A Laravel monorepo demonstrating a modular architecture where independent
Composer packages (modules) can be installed selectively on different servers,
all running in Docker.

---

## Architecture Overview

```
yl-project/
├── docker-compose.yml           # All 9 services
├── docker/
│   ├── Dockerfile               # Single image, ARG SERVER switches composer.json
│   ├── entrypoint.sh            # Boot sequence: key → wait DB → migrate → serve
│   ├── nginx/default.conf       # Nginx → PHP-FPM fastcgi
│   ├── supervisor/supervisord.conf
│   ├── server1/                 # composer.json + .env for Server 1
│   ├── server2/                 # composer.json + .env for Server 2
│   └── server3/                 # composer.json + .env for Server 3
└── packages/
    └── yl/
        ├── helper/              # Shared utilities (no module deps)
        ├── products/            # Products CRUD + RabbitMQ job
        └── posts/               # Posts CRUD + RabbitMQ job
```

### Module dependency graph

```
yl/products ──┐
              ├──► yl/helper
yl/posts    ──┘
```

`yl/helper` has no module dependencies — it only requires `laravel/framework`.
`yl/products` and `yl/posts` are completely independent of each other.

---

## Servers & Modules

| Container  | Port  | Modules installed          |
|------------|-------|----------------------------|
| `server1`  | 8001  | `yl/helper` + `yl/products` |
| `server2`  | 8002  | `yl/helper` + `yl/posts`   |
| `server3`  | 8003  | `yl/helper` + `yl/products` + `yl/posts` |
| `worker1`  | —     | Queue worker for server1   |
| `worker2`  | —     | Queue worker for server2   |
| `worker3`  | —     | Queue worker for server3   |
| `mysql`    | 3306  | Shared MySQL 8 database    |
| `rabbitmq` | 5672 / 15672 | RabbitMQ + Management UI |

Module isolation is enforced by Composer — each server has a different
`composer.json` that declares only the packages it needs.

---

## Quick Start

### Prerequisites
- Docker Desktop (or Docker Engine + Compose v2)
- Nothing else — no local PHP or Composer needed.

### 1. Clone / download the project

```bash
git clone <repo-url> yl-project
cd yl-project
```

### 2. Start everything

```bash
make build
```

First build takes ~5–8 minutes (downloads PHP image, runs `composer create-project`
three times). Subsequent starts are fast.

### 3. Verify all servers are healthy

```bash
# Server 1 — Products only
curl http://localhost:8001/api/products

# Server 2 — Posts only
curl http://localhost:8002/api/posts

# Server 3 — Both
curl http://localhost:8003/api/products
curl http://localhost:8003/api/posts

# RabbitMQ management UI
open http://localhost:15672   # guest / guest
```

---

## API Documentation

Interactive Swagger UI is available on every server once running:

| Server | URL |
|--------|-----|
| Server 1 (Products) | http://localhost:8001/api/documentation |
| Server 2 (Posts)    | http://localhost:8002/api/documentation |
| Server 3 (Both)     | http://localhost:8003/api/documentation |

Each server only documents the modules it has installed.

---

## Asynchronous Jobs (RabbitMQ)

### ProcessProductExportJob
Dispatched: when a Product is **created**.
Actions:
1. HTTP POST to `PRODUCTS_WEBHOOK_URL` (default: `https://httpbin.org/post`)
2. Shell command: appends to `storage/logs/sitemap.log`

### ProcessPostPublishedJob
Dispatched: when a Post transitions to **published** status.
Actions:
1. HTTP POST to `POSTS_INDEXER_URL` (default: `https://httpbin.org/post`)
2. Shell command: appends to `storage/logs/cache.log`

Workers run with `--tries=3 --timeout=90`. Monitor the queue via
RabbitMQ Management UI at http://localhost:15672.

---

## Running Tests

Run tests inside any container. Server 3 has all modules so it's the
most complete environment:

```bash
make test               # Full test suite
make test-products      # ProductCrudTest only
make test-posts         # PostCrudTest only
make test-helper        # ApiResponseTest only
make test-arch          # Architecture boundary tests
make test-all           # Everything — feature, unit and architecture
```

### Test coverage

| Test class         | Module       | Tests |
|--------------------|--------------|-------|
| `ApiResponseTest`  | yl/helper    | 8 unit tests — all response types and envelope shape |
| `ProductCrudTest`  | yl/products  | 9 feature tests — CRUD + validation + job dispatch |
| `PostCrudTest`     | yl/posts     | 12 feature tests — CRUD + slug generation + job transitions |

---

## Package Structure (each module)

```
packages/yl/<module>/
├── composer.json                 # Package definition + path repo deps
├── src/
│   ├── <Module>ServiceProvider.php   # Registers routes + migrations
│   ├── Models/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Resources/
│   └── Jobs/
├── database/
│   ├── migrations/
│   └── factories/
├── routes/
│   └── api.php
└── tests/
    └── Feature/
```

Migrations are loaded via `loadMigrationsFrom()` in each ServiceProvider —
the host app runs `php artisan migrate` and all installed module tables
are created automatically.

---

## Useful Commands

All common tasks are available via `make`:
```bash
make build          # Build and start everything
make test           # Run full test suite
make test-arch      # Verify module boundaries and naming conventions
make logs           # Tail all container logs
make sh-server3  # Open a shell in server3
make reset          # Stop and wipe all volumes
```

See the `Makefile` for all available targets.

For implementation rationale and design decisions see `COMMENTARIES.md`.
