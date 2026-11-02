<p align="center">
  <img src="public/brand/bengkelsync-icon.svg" alt="BengkelSync logo" width="96" height="96">
</p>

<h1 align="center">BengkelSync</h1>

<p align="center">
  Workshop management for Indonesian auto repair shops (bengkel): from the car arriving at the counter to the money in the drawer.
</p>

---

BengkelSync handles the daily work of a bengkel in one place: customers and their vehicles, work orders (SPK), invoices and payments, parts and stock, purchases and supplier debt, cash accounts and expenses. It is mobile-first, so staff can use it on a phone at the counter or in the workshop, and Indonesian-first (with English).

It is built as a multi-tenant SaaS: one account (a shop) can run several branches, and every shop's data is isolated from the others.

> **Status:** the MVP is complete and being prepared for a pilot in one shop (Bengkel Kalimas Lumas). See [docs/SPEC.md](docs/SPEC.md) section 9 for the roadmap and [docs/BUILD_SUMMARY.md](docs/BUILD_SUMMARY.md) for what is built.

## Features

**Front desk**
- Vehicle check-in by plate number, with new customers and vehicles added on the spot
- Work orders (SPK) with a status flow, checklist, mechanics, jobs and parts; printable job sheet
- Service history of every vehicle across branches, and ownership history when a car changes hands

**Billing**
- Invoices from a work order or as a counter sale ("Pelanggan umum"); job and part names, prices and discounts can be changed on the invoice
- Optional tax (PPN) per branch, included or on top; A5 print for 2-ply paper
- Payments with several methods in one go (cash, transfer, QRIS, cards with surcharge), part payments with a due date, kwitansi with the amount in words
- Send an invoice or kwitansi to the customer via WhatsApp, with a secure link to view and save the invoice
- Invoice corrections with a reason and a full change history; an optional lock period for older invoices

**Stock and purchasing**
- Parts catalog with stock per branch (decimal quantities for oil and other liquids), low-stock alerts and stock counts
- Purchases from suppliers with weighted average cost, supplier debt and payments over time, due-date reminders

**Money**
- Cash accounts per branch (cash drawer, petty cash, bank accounts), transfers and cash counts, all from an append-only ledger
- Expenses with receipt photos; restricted categories such as salaries
- Dashboard with sales, money received, expenses, gross profit (owner only), a 12-month chart and workshop status
- Daily summary ("Rekap harian") for closing the cash drawer, printable on A4

**Shop management**
- Branches, staff accounts, roles and a permission matrix per shop
- Branch details and logo printed on every document
- Type-to-search dropdowns and an audit log of every change

## Tech stack

- PHP 8.3, Laravel 13, MySQL 8
- Inertia.js 2 + Vue 3 (`<script setup>`, TypeScript) + Tailwind CSS
- spatie/laravel-permission (teams = tenants), laravel-vue-i18n (Indonesian first, English)
- Pest, Larastan (level 6), Laravel Pint, vue-tsc

## Getting started

Requirements: PHP 8.3+, Composer, Node 22+, MySQL 8.

1. Install dependencies:
   ```bash
   composer install
   ```
   ```bash
   npm install
   ```
2. Create `.env` and an app key, then set the `DB_*` values for your MySQL server:
   ```bash
   cp .env.example .env
   ```
   ```bash
   php artisan key:generate
   ```
3. Create two databases: `bengkelsync` (app) and `bengkelsync_test` (tests).
4. Create the tables, permissions and the demo shop:
   ```bash
   php artisan migrate --seed
   ```
5. Run the app (PHP server, queue, logs and Vite together):
   ```bash
   composer dev
   ```
6. Scheduled jobs (daily reminders for customer and supplier due dates) need Laravel's scheduler. On a server, add this cron entry; locally, run `php artisan schedule:work` when you need it:
   ```
   * * * * * cd /path/to/bengkelsync && php artisan schedule:run >> /dev/null 2>&1
   ```

### Demo accounts (local only)

`php artisan db:seed` creates **Bengkel Demo** with two branches. All demo accounts use the password `password1`. They are fictional `.test` addresses and the seeder refuses to run in production.

| Email | Role |
|---|---|
| owner@demo.test | Owner (Pemilik), all branches |
| manager@demo.test | Branch Manager (Kepala Cabang), both branches |
| frontdesk@demo.test | Front Desk (Admin Depan) |
| finance@demo.test | Finance (Keuangan) |
| warehouse@demo.test | Warehouse (Gudang), both branches |

### Creating a real shop

There is no public sign-up yet. Create a shop and its owner account with:

```bash
php artisan tenant:create
```

It asks for the shop name, first branch and code (used in document numbers, e.g. `KLM/INV/2026/00001`), and the owner's name, email and password. Leave the password empty to generate one; it is shown once.

## Deployment

BengkelSync runs on ordinary PHP hosting, including cPanel: sessions, cache and queue use the database (no Redis needed), nothing runs in the background except the scheduler cron above, and the document root must point to the `public` folder. Build for production with:

```bash
composer install --no-dev --optimize-autoloader
```
```bash
npm ci
```
```bash
npm run build
```

Then upload everything except `node_modules`, `tests` and your local `.env`, create a production `.env` (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `DB_*`, `MAIL_*`), and run `php artisan migrate --force` and `php artisan db:seed --force` (permissions only; the demo data never runs in production).

## User guide

An illustrated user guide in Indonesian, with a clickable table of contents, is in [docs/Panduan-BengkelSync.pdf](docs/Panduan-BengkelSync.pdf) (text version: [docs/PANDUAN.md](docs/PANDUAN.md)). Its sources and build script are in `docs/panduan/`.

## Quality checks

All of these must pass before a change is done (they also run in GitHub Actions):

```bash
php artisan test
```
```bash
./vendor/bin/phpstan analyse
```
```bash
./vendor/bin/pint --test
```
```bash
npm run type-check
```
```bash
npm run build
```

Tests run against the MySQL database `bengkelsync_test` (see `phpunit.xml`), so row locking behaves like production.

## Documentation

- [docs/SPEC.md](docs/SPEC.md): requirements, data model, MVP scope and decisions
- [docs/BUILD_SUMMARY.md](docs/BUILD_SUMMARY.md): what is built, how to review it, known limitations
- [docs/PANDUAN.md](docs/PANDUAN.md): user guide (Indonesian)
- [docs/AUDIT.md](docs/AUDIT.md): audit of the previous version
- [CLAUDE.md](CLAUDE.md): rules for contributors and AI assistants

## Security

The repository is public. Never commit database dumps, real customer data, `.env` files or keys. Please report security issues privately to the maintainer instead of opening a public issue.

## License

MIT. See [LICENSE](LICENSE).
