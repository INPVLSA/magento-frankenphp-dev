# FrankenPHP dev kit for Magento 2

FrankenPHP development setup for local Magento 2 development. Allows running Magento 2 in worker mode, fast magento with no cache enabled and HMR 
 bootstrap), plus a file watcher that reloads workers and refreshes the browser tab when you save a file.

## Requirements

- Docker (compose v2+)
- `fswatch` on the host (`brew install fswatch` on macOS) - for HMR
- Magento 2 project. You bring your own DB, Redis, OpenSearch — this kit only ships the PHP/web tier. See **Connecting to other services** below.

## Install

From the Magento project root:

```bash
git clone git@github.com:INPVLSA/magento-frankenphp-dev.git dev/franken
./dev/franken/install.sh
```

`install.sh` creates relative symlinks in the project:

| Project path                    | Linked to kit                           |
|---------------------------------|-----------------------------------------|
| `bin/mage-worker`               | `dev/franken/bin/mage-worker`           |
| `bin/dev-watch`                 | `dev/franken/bin/dev-watch`             |
| `pub/frankenphp-worker.php`     | `dev/franken/pub/frankenphp-worker.php` |
| `docker-compose.frankenphp.yml` | `dev/franken/docker-compose.yml`        |
| `docker/`                       | `dev/franken/docker/`                   |

It refuses to overwrite existing real files (only updates existing symlinks), so it's safe to re-run after a `git pull` on the kit.

## Run

At this point you have 2 options running

### Running FrankenPHP

#### Option 1. Run as separate compose, connect to your docker network with other services

```bash
docker compose -f docker-compose.frankenphp.yml up -d
```

#### Option 2. Integrate it to your docker compose

### Running HMR

```bash
./bin/dev-watch
```

> Edits to `app/code/**/*.php` reload the worker (~80 ms). Edits to `.phtml` / `.css` / `.js` just pushing a Mercure event that refreshes any open tab.

## How the watcher works

- `fswatch` monitors `app/code/` and `app/design/`.
- File extensions are classified:
  - `.php` / `.xml` - FrankenPHP gracefully restarts workers, picking up the new code
  - `.phtml` / `.css` / `.less` / `.js` - publish to the `dev/reload` Mercure topic. Browser tabs subscribed via `/dev-reload.js` reload themselves
- 300ms debounce per kind so editor save-bursts don't fire multiple reloads

The reload snippet is injected into HTML responses automatically when `MAGE_MODE=developer` in `app/etc/env.php`

## Connecting to other services

This kit only ships FrankenPHP. Your DB/Redis/OpenSearch/RabbitMQ run in a separate compose stack (or natively on the host).

The kit joins a shared external Docker network named `franken-magento` by default. Create it once on the host before bringing the stack up:

```bash
docker network create franken-magento
```

In your DB stack's compose file:
```yaml
services:
  mysql:
    networks: [franken-magento]
networks:
  franken-magento:
    external: true
```

## Host setup

Add to `/etc/hosts`:
```
127.0.0.1 magento.loc
```

## Classic mode (no worker)

If you want FrankenPHP without worker mode (one PHP process per request, like FPM), swap the Caddyfile mount:

```yaml
# docker-compose.yml
- ./docker/docker-files/caddy/Caddyfile.classic:/etc/caddy/Caddyfile:ro
```

## Uninstall

```bash
./dev/franken/install.sh --uninstall
rm -rf dev/franken
```
