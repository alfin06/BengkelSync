# BengkelSync

Management system for Indonesian automotive repair shops (bengkel): customers, vehicles, work orders, invoices, payments, inventory, supplier debts, petty cash and expenses. Built as a multi-tenant SaaS (one account can run several branches) and piloted in a single shop first.

> **Status:** rebuilding on a new foundation (Phase 0 of [docs/SPEC.md](docs/SPEC.md) section 9). Login, profile, branches, roles and permissions work; the shop modules are being rebuilt one by one. The previous version is kept under the git tag `legacy-v0`.

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
6. Scheduled jobs (daily supplier-debt reminders) need Laravel's scheduler. On a server, add this cron entry; locally, run `php artisan schedule:work` when you need it:
   ```
   * * * * * cd /path/to/bengkelsync && php artisan schedule:run >> /dev/null 2>&1
   ```

### Demo accounts (local only)

`php artisan db:seed` creates **Bengkel Demo** with two branches. All demo accounts use the password `password1`. They are fictional `.test` addresses and the seeder refuses to run in production.

| Email | Role |
|---|---|
| owner@demo.test | Owner (Pemilik), all branches |
| manager@demo.test | Branch Manager, both branches |
| frontdesk@demo.test | Front Desk (Admin Depan) |
| finance@demo.test | Finance (Keuangan) |
| warehouse@demo.test | Warehouse (Gudang), both branches |

### Creating a real shop

There is no public sign-up yet. Create a shop and its owner account with:

```bash
php artisan tenant:create
```

It asks for the shop name, first branch and code (used in document numbers, e.g. `KLM/INV/2026/00001`), and the owner's name, email and password. Leave the password empty to generate one; it is shown once.

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

- [docs/SPEC.md](docs/SPEC.md): requirements, data model, MVP scope
- [docs/AUDIT.md](docs/AUDIT.md): audit of the previous version
- [docs/PHASE0_PLAN.md](docs/PHASE0_PLAN.md): the foundation plan
- [CLAUDE.md](CLAUDE.md): rules for contributors and AI assistants

## Security

The repository is public. Never commit database dumps, real customer data, `.env` files or keys. Please report security issues privately to the maintainer instead of opening a public issue.

## License

MIT. See [LICENSE](LICENSE).
