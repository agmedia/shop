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


## DE/AT storefront completion (20 October 2026)

Implemented in the local project:

- German checkout button: `Zahlungspflichtig bestellen`.
- Destination VAT for Bali apparel: DE 19%, AT 20%. Stored gross product prices are first converted using the source tax class, then destination VAT is applied. Destination overrides for other product tax classes belong in `tax_rates.settings.destination_rates`. Unknown classes stop checkout rather than silently charge the wrong VAT. Shipping and payment method fees are configured gross; for DE/AT their VAT is separated and included in the order tax total. The original gross fees are recorded in the order payload for ERP export.
- Guest withdrawal is available through the existing returns portal with `?withdrawal=1`, linked visibly on every page. The buyer reviews the customer name, email, order reference and optional partial withdrawal before selecting `Widerruf bestätigen`. Confirmation persists the declaration, exact receipt time and a database mail task together. Repeating confirmation does not create another declaration. A new message also appears in the existing admin support inbox.
- Automatic withdrawal receipts include the declaration, affected items, name, contract reference, date, time and timezone. Order dispatch confirmation runs when a web order changes to `sent`, `shipped`, `dispatched` or a status with `settings.is_shipped=true`. German order receipts are translated and distinguish receipt from acceptance on dispatch.
- Manufacturer information appears after Material without an extra dropdown, including Bali d.o.o., Hrupine 19, HR-40323 Prelog, Kroatien, webshop@balidoo.com and product SKU/code. Applicable localized warnings can be supplied in `products.payload.safety_warnings.de` (and `hr`/`en`); warnings are never invented.
- Official unchanged EU guarantee notices are bundled for German, Croatian and English and shown immediately before the final checkout button. Source: [European Commission / Your Europe](https://europa.eu/youreurope/business/dealing-with-customers/consumer-contracts-guarantees/eu-legal-guarantee-notice-and-garan-label/index_de.htm).
- GA4 uses `G-DT810P4TK9`; existing view_item, add_to_cart, begin_checkout and purchase tracking is retained. Google scripts wait for analytics consent. Meta/Klaviyo remain consent gated even when the CMP is disabled. Consent withdrawal clears the configured analytics/marketing cookies. The newsletter loads reCAPTCHA on submission instead of every page view. German CMP copy is configured without overwriting an existing German translation.
- Category and collection banners now declare the actual pixel dimensions of each source, deduplicate equal widths, and use the original image as the largest candidate when suitable. The six current category originals are 1350px wide; their nominal 1920/2560 conversions are also only 1350px because Fit::Max preserves the source limit. Corrected sizes for the right category tiles avoid selecting an undersized 1200px lossy WebP. Existing manual crops stay intact and no regeneration or new image upload is needed for the production originals.
- Accessibility improvements: skip link, main landmark in both templates, visible keyboard focus, reduced motion preference, labels for search/newsletter fields, and a withdrawal form with native labels and a JavaScript independent review/confirmation flow.

### Applying on the target environment

Nakon `git pull origin kozo` na serveru pokrenuti:

```sh
php artisan optimize:clear
php artisan migrate --force
php artisan storefront:configure-de-at
php artisan klaviyo:refresh-catalogs
php artisan view:cache
php artisan queue:restart
```

Konfiguracijska naredba uključuje njemački jezik, zadani GA4 ID, purchase događaj i CMP. Migracija kreira evidenciju izjava o odustanku. Frontend se gradi uobičajenim postupkom projekta (`npm ci` i `npm run build`) ako se build radi na serveru. Izmjene nisu objavljene samo zato što je izvršen Git push; server mora povući ovu granu i izvršiti navedene naredbe.

A running **database** queue worker is required for immediate withdrawal and shipment emails: `php artisan queue:work database --queue=default --tries=5`. The existing Redis Horizon workers do not process this database queue. Use the database queue's configured queue name if `DB_QUEUE` differs from `default`. Monitor failed jobs and retry failed transactional mail after resolving delivery errors. No real customer email was sent during verification.

### Dinamički Klaviyo katalog

Nakon povlačenja izmjena javni linkovi su:

- Njemačka: `https://www.bali.hr/feeds/klaviyo-katalog-de.json`
- Austrija: `https://www.bali.hr/feeds/klaviyo-katalog-at.json`

Katalog se automatski generira iz baze webshopa na serveru. Sadrži aktivne proizvode s njemačkim nazivom, važećim slugom i zapisom slike, aktualnu javnu akcijsku cijenu s odredišnim PDV-om, zalihe aktivnih varijanti, kategorije i javne poveznice. Product ID odgovara postojećem Klaviyo event ProductID. Poveznice na proizvod uključuju `lang=de` tako da rade i posjetitelju koji nema njemačku sesiju. Podaci kupaca nisu dio kataloga.

Osvježavanje je postavljeno **svaka 3 sata** preko postojećeg Laravel raspoređivača (`0 */3 * * *`). Ako cache još ne postoji ili je stariji od tri sata, prvi zahtjev ga ponovno generira. Za redovito osvježavanje mora raditi postojeći cron koji poziva `php artisan schedule:run` svake minute. Nakon deploya može se odmah pokrenuti `php artisan klaviyo:refresh-catalogs`. Nova javna ruta ne traži ručni upload JSON datoteke i ne ovisi o lokalnim kopijama produkcijskih slika.

U Klaviyo se jednom upisuje odgovarajući link pod prilagođenim katalogom. Mapiranje: id, title, link, image_link, description, price, inventory_quantity, inventory_policy i categories. [Klaviyova dokumentacija](https://developers.klaviyo.com/en/docs/guide_to_syncing_a_custom_catalog_feed_to_klaviyo) opisuje učitavanje izvora. Naša obnova svaka tri sata ne mijenja interval kojim sam Klaviyo dohvaća izvor. Povezivanje u Klaviyo računu još nije izvršeno.

Za jednokratno preuzimanje i dalje postoji `php artisan klaviyo:export-catalog --base-url=https://bali.hr --locale=de --country=DE`; za Austriju `--country=AT` i druga izlazna putanja. Javne feedove treba koristiti za automatsko održavanje.

Provjera javnih slika 20. listopada 2026. potvrdila je dostupnost svih 189 slikovnih poveznica iz lokalnog uzorka. To nije fiksan broj proizvoda produkcijskog feeda: on čita aktualnu produkcijsku bazu. U lokalnom uzorku izostavljeno je 397 aktivnih zapisa bez medija i tri bez njemačkog prijevoda. Korisnik ne treba slati slike koje već postoje na serveru.

### Information for Tea's AGB accessibility section

The following factual information is ready to use when preparing the German text:

> Anbieter: Bali d.o.o., Hrupine 19, HR-40323 Prelog, Kroatien. Kontakt für Fragen und Hinweise zur Barrierefreiheit: webshop@balidoo.com.
>
> Beschreibung der Dienstleistung: Unser Online-Shop ermöglicht die Suche und Auswahl von Bekleidung, das Hinzufügen von Produkten zum Warenkorb und die Bestellung mit Auswahl der Lieferadresse, Versandart und Zahlungsart. Bestellungen sind ohne Kundenkonto möglich. Ein Kundenkonto ist optional. Ein elektronischer Widerruf kann ohne Anmeldung über die im Shop verlinkte Funktion „Vertrag widerrufen“ erklärt und vor dem Absenden geprüft werden.
>
> Technische Maßnahmen: Die Website stellt einen Sprunglink zum Hauptinhalt und sichtbare Tastaturfokusmarkierungen bereit. Die Eingabefelder der Widerrufsfunktion besitzen zugeordnete Beschriftungen. Bewegungen und Übergänge berücksichtigen die Systemeinstellung für reduzierte Bewegung.

This is factual material, **not a completed conformity statement**. Before publishing a statement, finish the complete manual keyboard, screen reader, zoom/reflow and contrast review of navigation, PDP, cart, checkout, errors and all selected payment provider screens; record the actual results, any remaining barriers, maintenance/review process and the responsible authority. Do not assert WCAG/EN 301 549/BFSG/BaFG conformity based only on these changes or automated tests. [Germany's official guidance](https://www.bundesfachstelle-barrierefreiheit.de/DE/Barrierefreiheitsstaerkungsgesetz/FAQ-Dienstleistungen/faq-dienstleistungen_node) distinguishes the BFSG service information from a public-sector accessibility declaration. Austria's required content is in [BaFG Anlage 3](https://ris.bka.gv.at/eli/bgbl/i/2023/76/ANL3/NOR40254407). Relevant authorities: [MLBF Germany](https://www.mlbf-barrierefrei.de/Startseite/?La=1), [Sozialministeriumservice Austria](https://sozialministeriumservice.gv.at/Marktueberwachung_digitale_Barrierefreiheit/Informationen_fuer_Unternehmen/Konformitaet/Konformitaet.de.html).

The currently stored German AGB also need a content review: one paragraph says the buyer's checkout actions conclude the contract while a later paragraph says acceptance occurs only on shipment. The technical implementation now distinguishes order receipt and shipment acceptance. Align the first paragraph accordingly, add the electronic withdrawal link, remove any requirement to use only a PDF form, and verify delivery destinations/costs and hygiene exceptions with the legal text owner. No unsupported legal conformity claim was inserted into the live AGB.

Primary references checked: [German electronic withdrawal provision](https://www.gesetze-im-internet.de/bgb/__356a.html), [EU standard VAT rates](https://europa.eu/youreurope/business/finance-and-tax/vat/vat-rules-rates/index_en.htm), and the official guarantee/accessibility sources linked above.

### Verification results

77 focused PHP feature tests passed (601 assertions), covering guest/full/partial withdrawal, persisted mail tasks, receipt and dispatch emails, destination tax, gross shipping fee extraction, quoted-total protection, both device templates, catalog export, dynamic feed refresh every three hours, localized feed links, actual banner source widths, locales, existing orders and Klaviyo integration. Two JavaScript tests verify no tracking before consent, separate GA/Meta consent, purchase deduplication and revocation. The frontend build and Blade compilation pass.

The broader pre-existing storefront suite has 53 passing tests and seven failing tests. The same seven failures are reproducible on the original HEAD snapshot (product route fixture, contact consent fixture, configured navigation copy, material card copy, missing-main-image fallback, option filters and hidden category filters). They were not introduced by this change. The baseline snapshot additionally had an environment-sensitive newsletter script URL assertion; the updated test now checks the intended lazy loading behavior.
