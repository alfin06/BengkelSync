# CLAUDE.md — Bengkel Management System (SaaS)

Multi-tenant SaaS for Indonesian automotive repair shops (bengkel). One account (tenant) can run multiple branches.
Full requirements: @docs/SPEC.md. Read the relevant section before starting any feature.

**Current goal: MVP for a pilot in one shop** (SPEC section 9). Build only MVP items; do not start "After pilot" features unless asked. Keep the multi-tenant/multi-branch architecture intact even though the pilot uses one tenant and one branch.

## Tech stack
- Laravel 12 (PHP 8.3+), MySQL 8
- Vue 3 (Composition API, `<script setup>`) + TypeScript. Frontend style: **Inertia.js v2 monolith** (controllers return `Inertia::render()`, Ziggy `route()` in Vue, session auth; no separate API). Small JSON endpoints for autocomplete only. TypeScript toolchain (`vue-tsc`, `tsconfig.json`) is not set up yet; see docs/AUDIT.md
- Tailwind CSS
- spatie/laravel-permission with the **teams** feature enabled (team = tenant)
- laravel-vue-i18n: one set of translation files shared by Laravel and Vue
- Redis for cache and queues; S3-compatible storage for uploads (logos, receipts)
- Pest for tests, Larastan (PHPStan) level 6+, Laravel Pint for formatting

## Commands
- `composer dev` / `php artisan serve` + `npm run dev`
- `php artisan test` must pass before a task is done
- `./vendor/bin/phpstan analyse` and `./vendor/bin/pint --test` must pass
- `npm run build` and `npm run type-check` must pass for frontend changes
- `php artisan migrate`. Never edit a migration that has already run in production; create a new one
- `php artisan db:seed` seeds permissions, a demo tenant with two branches, and demo users

## Current codebase state
Audited 2026-10-21, full report in `docs/AUDIT.md`. Until the refactor lands, existing code does **not** follow the rules below, so don't copy its patterns.
- **Actual versions:** Laravel 13.5 (not 12), PHP 8.3, Vue 3.5, Inertia 2, Vite 8, Tailwind 3.4. Started from Breeze (Vue). Pages use `<script setup lang="ts">` but are untyped (`any`); entry is `resources/js/app.js`.
- **Missing:** spatie/permission, laravel-vue-i18n, Pest, Larastan, vue-tsc, `lang/`, Services, Policies, Support helpers. Dev dependencies are not installed in `vendor/`.
- **Single-shop schema:** no `tenant_id`/`branch_id` anywhere. One global `settings` row. Roles are a `users.role` enum (`admin|staff|finance`) with `admin`/`staff` middleware. Most routes only require `auth`.
- **Money** is `decimal(15,2)` with float math. **Stock** is written directly to `parts.stock` (and parts on invoices are deducted twice). No document numbers (`INV-#id`). Timestamps are stored in WIB (`app.timezone = Asia/Jakarta`).
- **Existing modules:** customers (3 phone columns), vehicles, work orders (statuses `Pending/Proses/Selesai/Batal`, task checklist `work_order_items`, `work_order_parts`), invoices (`invoice_details`, one per WO, built in Vue), receivables, parts, stock entries, suppliers, mechanics (non-login table), expenses, admin/staff dashboards, reports, settings, users, `activity_logs`.
- **Uncommitted WIP** (polymorphic `payments`, supplier debt, petty cash): superseded by the approved design in SPEC §4 (`payment_receipts`/`payments`/`payment_methods`, `purchases`/`supplier_payments`, `cash_accounts`/`cash_movements`). Don't build on the WIP tables.
- **Pilot decisions:** pilot shop is Bengkel Kalimas Lumas, not live yet, so there is **no production data**. Phase 0 replaces the migrations with a clean baseline (AUDIT §5). Accounts: Owner, Front Desk, Finance. Mechanics are non-login records (NIK encrypted, KTP on a private disk). The repo is **public**: never commit dumps, real personal data, or secrets.
- **Schema drift:** the local DB records 7 migrations whose files were deleted (see AUDIT §6). That's moot once the clean baseline lands.
- **Quality bar:** follow SPEC §8.1 (mobile-first, simple UX), §8.2 (security), and §8.3 (engineering principles) for every change.
- **Plan:** keep the Inertia/Vue shell, auth, and most pages. Refactor customers, vehicles, parts, suppliers, stock entries, expenses, settings, and users. Rewrite work orders, invoices, payments, stock, and dashboards behind Services. All schema changes are additive and preserve data (AUDIT section 5).

## Project structure (target)
```
app/
  Models/                       Eloquent models
  Models/Concerns/BelongsToTenant.php, BelongsToBranch.php
  Services/<Module>/            business logic (InvoiceService, StockService, ...)
  Http/Controllers/<Module>/    thin controllers
  Http/Requests/<Module>/       FormRequest validation + authorization
  Policies/                     one policy per model
  Support/                      Money, DocumentNumber, PlateNumber, Phone helpers
resources/js/
  Pages/<Module>/               pages
  Components/<Module>/          module components
  Components/ui/                shared UI components
lang/id.json, lang/en.json      translations (Indonesian is the source language)
docs/SPEC.md
```

## Non-negotiable rules
1. **Tenant isolation.** Every tenant-owned model uses the `BelongsToTenant` trait (global scope + auto-fill `tenant_id`). Never query tenant data with `withoutGlobalScopes()` except in clearly marked platform-admin code. Every new model gets a test proving tenant A cannot read or write tenant B's data.
2. **Branch scoping.** Operational data (work orders, invoices, payments, stock, expenses) has `branch_id`. Users only see branches they are assigned to. The active branch comes from the session, never from an unchecked request parameter.
3. **Authorization on the server.** Every controller action is authorized through a Policy or FormRequest using permission names from SPEC section 2. Hiding a button in Vue is not security.
4. **No hardcoded UI text.** All strings go through `__()` / `trans()` / `$t()`. Add keys to BOTH `lang/id.json` and `lang/en.json` in the same change.
5. **Money is integer Rupiah** in `BIGINT UNSIGNED` columns (or signed where negatives are valid). Never float or double. Format with the `Money` helper (`Rp 1.250.000`).
6. **Inventory changes only through `StockService`**, which writes a `stock_movements` row and updates `parts_branch_stock.qty` in one transaction.
7. **Multi-step writes use `DB::transaction()`** (invoicing, payments, invoice edits, stock).
8. **Edits to issued invoices go through `InvoiceService::revise()`**, which checks permission, requires a reason, saves a revision snapshot, recalculates totals, syncs stock, and writes the audit log. Never update invoice rows directly.
9. **Audit log** for create/update/delete on work orders, invoices, payments, stock adjustments, expenses, users, roles, and settings.
10. **Document numbers** come from `DocumentNumber::next($branch, 'INV')`, which uses a locked sequence row (`SELECT ... FOR UPDATE`). Never use `max(id)+1`.
11. Timestamps are stored in UTC and displayed in the branch timezone (default `Asia/Jakarta`).

## Conventions
- Business logic lives in Services. Controllers validate, authorize, call one service method, and return.
- Use Eloquent eager loading; avoid N+1 queries (enable `Model::preventLazyLoading()` in non-production).
- Paginate every list server-side, with search and sort.
- Soft deletes for customers, vehicles, parts, suppliers, users, branches.
- Indonesian formats: phone accepts `08...` and is stored as `+62...`; plate number uppercase with normalized spacing (`B 1234 XYZ`).
- Database names: snake_case plural tables, snake_case columns (Laravel defaults).

## Workflow for each task
1. Read the relevant part of `docs/SPEC.md`. Ask if anything is ambiguous.
2. Propose a plan (migrations, models, services, pages). Wait for approval when it changes the schema or touches existing data.
3. Implement migration → model → service → policy/request → controller → Vue page → translations.
4. Write Pest tests: service logic, permissions, and tenant isolation.
5. Run tests, PHPStan, Pint, and the frontend build. Fix failures before saying done.
6. Tick the checklist in `docs/SPEC.md` section 9.

## Don't
- Don't add packages without explaining why.
- Don't put business logic in Vue components or controllers.
- Don't rename permissions without updating the seeder and SPEC.
- Don't "fix" tests by weakening assertions.
