# Basecamp Automation

Laravel modular monolith untuk audit harian modul bisnis pertama: `KPUS GA HW`.

Flow produksi:

```text
Basecamp dated to-do list -> objective photo checks -> OpenAI vision review -> PostgreSQL final result -> Notion output
```

Notion hanya output. PostgreSQL adalah source of truth.

## Requirements

- PHP 8.3+
- Composer
- PostgreSQL
- Laravel scheduler via cron or process manager

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Required `.env` keys:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

BASECAMP_ACCOUNT_ID=
BASECAMP_PROJECT_ID=
BASECAMP_CLIENT_ID=
BASECAMP_CLIENT_SECRET=
BASECAMP_ACCESS_TOKEN=
BASECAMP_REFRESH_TOKEN=
BASECAMP_TOKEN_EXPIRES_AT=
BASECAMP_USER_AGENT="LMP Basecamp Audit (your-email@example.com)"

KPUS_GA_HW_TIMEZONE=Asia/Jakarta
KPUS_GA_HW_RUN_TIME=09:00
KPUS_GA_HW_MIN_PHOTOS=2
KPUS_GA_HW_AI_MAX_IMAGES=4

OPENAI_API_KEY=
OPENAI_VISION_MODEL=gpt-4.1-mini
OPENAI_VISION_MAX_ATTEMPTS=2

NOTION_TOKEN=
NOTION_DATABASE_ID=
NOTION_DATA_SOURCE_ID=
NOTION_VERSION=
```

Never commit real credentials.

## Commands

Read normalized Basecamp input only:

```bash
php artisan kpus-ga-hw:basecamp-input --report-date=2026-06-23
```

Run objective audit only:

```bash
php artisan kpus-ga-hw:objective-audit --report-date=2026-06-23
```

Run objective audit plus AI review:

```bash
php artisan kpus-ga-hw:ai-review --report-date=2026-06-23
```

Publish pending or failed Notion deliveries:

```bash
php artisan kpus-ga-hw:publish-notion --report-date=2026-06-23
```

Run the full daily process manually:

```bash
php artisan kpus-ga-hw:daily-audit --report-date=2026-06-23
```

Run the full process for the latest previous business day:

```bash
php artisan kpus-ga-hw:daily-audit
```

## Scheduler

The Laravel scheduler registers:

```text
kpus-ga-hw:daily-audit
09:00 Asia/Jakarta
```

Production cron entry:

```cron
* * * * * cd /path/to/basecamp-automation && php artisan schedule:run >> /dev/null 2>&1
```

Verify scheduled tasks:

```bash
php artisan schedule:list
```

## Operations

Audit results are stored in `daily_area_audits`.

Important delivery fields:

- `status`: final business status
- `reason`: final business reason
- `notion_delivery_status`: `pending`, `delivered`, or `failed`
- `notion_page_id`: Notion page ID after success
- `notion_attempts`: delivery attempt count
- `last_notion_error`: sanitized delivery failure

Retry failed Notion deliveries:

```bash
php artisan kpus-ga-hw:publish-notion
```

The system logs run summaries and retry outcomes without tokens or full external payloads.

## Verification

```bash
vendor/bin/pint --test
php artisan test
php artisan migrate:status
php artisan schedule:list
```

Automated tests use fakes for Basecamp, OpenAI, and Notion. They must not call live external APIs.
