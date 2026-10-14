# AG Shop

Admin-first webshop platform (Livewire admin), with planned Vue-based front templates (desktop + mobile/PWA).

## Requirements

- PHP `8.4`
- Composer `2.x`
- Node.js `22.x` and npm
- MySQL/MariaDB

## Local Development Setup

1. Install dependencies:
```bash
composer install
npm install
```

2. Create environment file:
```bash
cp .env.example .env
```

3. Configure `.env`:
- `APP_URL` (for Herd/local domain)
- `DB_CONNECTION=mysql`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

4. Generate app key:
```bash
php artisan key:generate
```

5. After `.env` DB credentials are valid and the target database already exists, run migrations:
```bash
php artisan migrate
```

6. Seed data:
```bash
php artisan db:seed
```
Expected prompt:
```text
Seed large dummy webshop dataset (100 categories, 1000 products, 3000 orders, 500 users, etc.)? (yes/no) [no]:
```
- Answer `no` for standard local baseline data.
- Answer `yes` when you need a large dummy dataset for performance/testing.
- You can also force this non-interactively with `SEED_DUMMY_DATA=true`.

7. Link storage:
```bash
php artisan storage:link
```

8. Build/start frontend assets:
```bash
npm run dev
```

9. Start app/runtime processes (if needed):
```bash
php artisan serve
php artisan queue:work
```

## Default Seeded Users (Non-Superadmin)

These are default local users for quick testing:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@agshop.local` | `admin` |
| Editor | `editor@agshop.local` | `editor` |
| Customer | `customer@agshop.local` | `customer` |

Super-admin users are intentionally not listed in this table.

## API Access Notes

- Wholesale API endpoints are under `/api/v1/wholesale`.
- API access is controlled per user (`api_access_enabled`) and via token abilities.
- CLI token creation:
```bash
php artisan wholesale:token user@example.com client-name
```

## Klaviyo orders and welcome flow

In **Store Settings → Newsletter**, select Klaviyo and configure the private API key
and list ID. The private key needs `events:write` in addition to the existing
newsletter subscription permissions. The public Site ID defaults to `TYXFZd`
from the supplied Bali tracking snippet and can be changed or cleared in settings.

When Klaviyo is selected, the webshop skips its own BALI10 newsletter coupon
email so the Klaviyo welcome flow handles that message. Newsletter subscriptions
still sync to Klaviyo, and order and contact emails keep their existing settings.

Web orders send a server-side **Placed Order** event on the default queue after
the order transaction commits. Bank transfer and cash orders send on placement;
WSPay, CorvusPay and KEKS Pay orders wait for confirmed payment. Cancelled orders,
unpaid gateway attempts and invalid email addresses are skipped. A running queue
worker is required; restart long-running workers when deploying this change.
Failures retry up to five times and remain available in the failed jobs list.
Events match profiles by email and do not subscribe buyers to marketing.

The onsite script loads after marketing cookie consent in both frontend layouts.
It uses the public Site ID only. Footer newsletter consent links point to the
terms and newsletter privacy pages.

After deployment, place one valid order and verify the **API** metric named
**Placed Order** in Klaviyo. Set the welcome flow split/filter to that metric,
for example “Placed Order at least once over all time” for existing buyers and
“zero times over all time” for non-buyers. The Shopify metric is separate.

To include existing web orders, preview then queue the historical sync:

```bash
php artisan klaviyo:sync-orders --dry-run
php artisan klaviyo:sync-orders
# Optional inclusive order-date range:
php artisan klaviyo:sync-orders --from=2026-01-01 --to=2026-10-12
```

The sync preserves original order dates and uses the same stable event IDs as
live events, so rerunning it does not duplicate Klaviyo order events. Review
post-purchase flows before importing recent historical orders, since imported
events may qualify for those flows. No historical events are sent automatically.

References: [Klaviyo custom ecommerce integration](https://developers.klaviyo.com/en/docs/guide_to_integrating_a_platform_without_a_pre_built_klaviyo_integration),
[Create Event and required scope](https://developers.klaviyo.com/en/reference/create_event).

## Useful Commands

```bash
php artisan optimize:clear
php artisan test
php artisan route:list
```
