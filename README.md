# Zephyrus Application Template

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

The official application template for the [Zephyrus](https://github.com/zephyrus-framework/core) PHP framework. Start a new project with a working structure: controllers, Latte views, database, sessions, mailer, and Docker, all preconfigured.

---

## Quick Start

You need Docker with Compose. Create the project one of two ways.

With PHP 8.4 (`intl`, `mbstring` and `sodium` extensions) and Composer on your machine:

```bash
composer create-project zephyrus-framework/project my-app
```

With Docker only, run the same command in the Zephyrus PHP image, as your own user:

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app ghcr.io/zephyrus-framework/php:8.4-dev composer create-project zephyrus-framework/project my-app
```

Either way, Composer installs the dependencies and creates `.env` with a fresh `ENCRYPTION_KEY`. Then start it:

```bash
cd my-app
docker compose up
```

Then open [http://localhost:8080](http://localhost:8080).

Port 8080 or 1080 already taken? Run `APP_PORT=8081 MAIL_PORT=1081 docker compose up`, or set `APP_PORT` and `MAIL_PORT` in `.env`.

---

## Project Structure

```
app/
  Controllers/     Route controllers (auto-discovered via attributes)
  Models/          Domain models, services, brokers
  Views/           Latte templates
    layouts/       Layout templates
bin/
  create-env.php   Creates .env and its ENCRYPTION_KEY
cache/
  latte/           Compiled template cache (auto-generated)
config.yml         Application configuration (YAML with !env tag support)
locale/            Translation files (JSON)
public/            Web root (Apache document root)
  index.php        Application entry point
  assets/          Static assets (CSS, JS, images, fonts)
sql/               Database initialization scripts
temp/              Temporary files
```

---

## Configuration

All configuration lives in `config.yml`. Sensitive values use the `!env` tag to reference environment variables from `.env`:

```yaml
database:
  host: !env DB_HOST, localhost
  password: !env DB_PASSWORD
```

`.env` is not committed. On a fresh clone of your project, run `composer install`, then `php bin/create-env.php`: it copies `.env.example` to `.env` and generates the key. The example ships with working defaults for the Docker setup.

`security.max_body_size` (10 MB) is also set in `public/.htaccess`: change both together.

---

## Docker Environment

The included `docker-compose.yml` runs the official `postgres:17` image and `ghcr.io/zephyrus-framework/php:8.4-dev` (PHP 8.4 with Apache and MailCatcher), and provides:

| Service | Address | Purpose |
|---|---|---|
| **PHP/Apache** | `127.0.0.1:8080` | Application server (PHP 8.4) |
| **MailCatcher** | `127.0.0.1:1080` | Email testing UI (SMTP on `localhost:1025` inside the container) |
| **PostgreSQL** | `database:5432` | Database (auto-initialized from `sql/init.sql`), reachable from the application only |

Apache runs as `www-data`, which cannot write your files, so `cache/latte/` and `temp/` live in memory inside the container: they are emptied whenever it restarts.

To reach the database from a client on your machine, add a port under `database` in `docker-compose.yml`, then run `docker compose up` again:

```yaml
    ports:
      - "127.0.0.1:5432:5432"
```

### Common Commands

```bash
# Start the environment (Ctrl+C stops it)
docker compose up

# Run Composer inside the container, as your own user
docker compose exec -u "$(id -u):$(id -g)" webserver composer update

# Open a PostgreSQL shell
docker compose exec database psql -U dev zephyrus

# Reset the database (removes its volume, re-runs init.sql)
docker compose down -v
docker compose up
```

---

## Running Without Docker

You need PHP 8.4+ with the `intl`, `mbstring`, `pdo_pgsql` and `sodium` extensions, and a PostgreSQL server. Set `DB_HOST` and the other `DB_` values in `.env` to reach it, then:

```bash
php -d post_max_size=10M -d upload_max_filesize=10M -S 127.0.0.1:8080 -t public
```

The built-in server ignores `public/.htaccess`, so the two `-d` options set its body size limit. Under it, the debugger shows itself to your browser while `DEBUG_CLIENTS` is unset.

---

## Controllers

Controllers use PHP 8 attributes for routing and are auto-discovered from `app/Controllers/`:

`app/Controllers/ProductController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Zephyrus\Controller\Controller;
use Zephyrus\Http\Request;
use Zephyrus\Http\Response;
use Zephyrus\Rendering\RenderResponses;
use Zephyrus\Routing\Attribute\Get;
use Zephyrus\Routing\Attribute\Post;

final class ProductController extends Controller
{
    use RenderResponses;

    #[Get('/products')]
    public function index(): Response
    {
        return $this->render('products/index', [
            'products' => [],
        ]);
    }

    #[Post('/products')]
    public function store(Request $request): Response
    {
        $data = $request->body()->all();
        // ...
        return Response::redirect('/products');
    }
}
```

`app/Views/products/index.latte`, the view it renders:

```latte
{layout '../layouts/main.latte'}

{block title}Products{/block}

{block content}
<form method="post" action="/products">
    <input type="hidden" name="_csrf_token" value="{csrfToken()}">
    <button>Save</button>
</form>
{/block}
```

No manual route registration needed: create a controller in `app/Controllers/` and add route attributes.

Every request but `GET`, `HEAD` and `OPTIONS` must carry the CSRF token, in the `_csrf_token` field (as in the form above) or the `X-CSRF-Token` header, or it gets a 403.

### When a Page Fails

Under Docker, an error answers a bare `500 Internal Server Error`. Its message is in the output of `docker compose up` (or `docker compose logs webserver`).

To see the debugger in your browser instead:

1. Find the address your browser comes from: it starts each request line of that output, like `172.23.0.1` in `webserver-1  | 172.23.0.1 - - [...] "GET / HTTP/1.1" 200`. It can change after `docker compose down`.
2. Make a secret with `docker compose exec webserver php -r 'echo bin2hex(random_bytes(16)), "\n";'`.
3. Set `DEBUG_CLIENTS=<secret>@<address>` in `.env`. In the browser console, run `document.cookie = "tracy-debug=<secret>; path=/"`, then reload the page.

---

## Documentation

Full framework documentation is available on the docs site (coming soon). See the [core framework](https://github.com/zephyrus-framework/core) README for API details.

---

## License

MIT, see [LICENSE](LICENSE).
