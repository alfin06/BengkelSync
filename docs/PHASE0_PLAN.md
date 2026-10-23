# Phase 0 Plan — Foundation

Status: **awaiting approval** · Covers SPEC section 9 "MVP — Phase 0" · Written 2026-10-24

## Goal
By the end of Phase 0 the app has a clean, tested foundation:
- tenants and branches with isolation
- roles and permissions
- login
- translations
- money, numbering, phone, and plate helpers
- audit log
- a mobile-first UI shell

Every Phase 1 module (customers, work orders, invoices, …) is then built on top of it, one at a time.

Phase 0 does **not** rebuild any business module. Those come in Phase 1, in SPEC order.

## Approach
- **Clean rebuild, not patching.** The shop is not live, and nearly every existing module gets a rewrite (AUDIT section 4). Patching old code first would be wasted work.
  - Tag the current state as `legacy-v0` so the old pages stay available as UI reference.
  - Remove the old modules from the running app.
  - Rebuild each module in Phase 1, reusing its page layouts where they are good.
- **Work happens on a `phase-0` branch.** Each step below is one reviewable commit. Merge to `main` when the whole phase is green.
- **Every step ends green:** `php artisan test`, PHPStan, `pint --test`, `npm run build`, and `npm run type-check`.

## Packages

| Package | Type | Why |
|---|---|---|
| `pestphp/pest` 5 + `pestphp/pest-plugin-laravel` | dev | Test runner required by CLAUDE.md. Readable tests for money, stock, and permission rules. |
| `larastan/larastan` 3 | dev | Static analysis at level 6. Catches type errors and wrong relations before runtime. |
| `spatie/laravel-permission` 8 | runtime | Roles and permissions per tenant (teams feature). Standard, well maintained, and avoids writing our own RBAC. |
| `laravel-vue-i18n` 2 | runtime (npm) | One set of `lang/*.json` files used by both Laravel `__()` and Vue `$t()`. |
| `typescript` + `vue-tsc` | dev (npm) | Real type checking for the existing `lang="ts"` pages (`npm run type-check`). |

**To remove:**
- `@tailwindcss/vite`: installed but unused, and conflicts with Tailwind 3.
- `laravel/socialite`: only if Google login is dropped (decision 2).

Exact versions are whatever Composer and npm resolve for Laravel 13 / PHPUnit 12. If the latest TypeScript is not yet supported by `vue-tsc`, I'll pin the newest supported one.

## Steps

### 0.1 Safety net and branch
1. Create the `legacy-v0` tag and the `phase-0` branch.
2. Create a `openbengkel_test` MySQL database for tests. Tests run on MySQL, not SQLite, because production is MySQL and locking (`FOR UPDATE`) must behave the same.
3. Run `composer install` with dev dependencies (they are missing from `vendor/` today).

### 0.2 Tooling
1. **Pest:** convert the Breeze tests to Pest. Add shared helpers for tenancy tests (see 0.4).
2. **Larastan:** `phpstan.neon` at level 6, with no baseline for new code.
3. **Pint:** `pint.json` using the Laravel preset.
4. **TypeScript:**
   - add `tsconfig.json` with `@/*` paths
   - rename `app.js`, `bootstrap.js`, and `lib/utils.js` to `.ts`
   - add `resources/js/types/` (shared Inertia page props: auth user, permissions, active branch, flash, locale)
   - add an `npm run type-check` script
5. **CI:** `.github/workflows/ci.yml` runs on every push and PR. It runs tests (MySQL service), PHPStan, Pint, build, type-check, `composer audit`, and `npm audit --omit=dev`.

### 0.3 Clean baseline
1. Remove the old migrations, models, controllers, requests, routes, and pages. They stay in the `legacy-v0` tag.
2. Keep the Breeze auth controllers and pages, the profile page, and `Components/ui`.
3. Add new migrations for the foundation tables only:
   - Laravel's `cache`, `jobs`, and `sessions` tables
   - `tenants` (name, slug, status, default_locale, settings JSON)
   - `branches` (code, address, phone, timezone, logo_path, npwp, tax fields, invoice_footer, soft deletes)
   - `users` (tenant_id, phone, locale, is_active, all_branches, soft deletes; email unique globally)
   - `branch_user`
   - spatie permission tables, with teams on `tenant_id`
   - `document_sequences`
   - `audit_logs`

   Module tables arrive with their modules in Phase 1.
4. Set `app.timezone = UTC`. Display uses the branch timezone (rule 11).
5. Turn on strict model behaviour in non-production: `preventLazyLoading`, `preventSilentlyDiscardingAttributes`, and `preventAccessingMissingAttributes`.

### 0.4 Tenancy and branches
1. **`TenantContext`** (a request-scoped singleton) holds the current tenant and branch. It is set by middleware from the logged-in user, never from request input.
2. **`BelongsToTenant` trait:**
   - global scope on `tenant_id`
   - fills `tenant_id` automatically on create
   - refuses to save a model whose `tenant_id` differs from the current tenant
3. **`BelongsToBranch` trait:** the same for `branch_id`.
4. **`SetActiveBranch` middleware:**
   - reads the branch from the session
   - checks it against the user's assigned branches (or `all_branches`)
   - picks the branch automatically when the user has exactly one
   - otherwise returns 403
5. **Branch switch:** a route switches the active branch after the same check. The switcher is hidden when a user has one branch.
6. **Isolation test kit:** reusable Pest helpers, e.g. `assertTenantIsolated(Model::class)`, prove that tenant A can't list, find, update, or attach tenant B's rows, and that a branch user can't reach another branch. Every future model uses them.

### 0.5 Permissions and roles
1. A **`Permission` enum** is the single source of truth for SPEC section 2 names (`customer.view`, `cash.transfer`, …). The seeder, policies, and frontend all read from it, so names can't drift.
2. **`DefaultRoles`** defines Owner, Branch Manager, Front Desk, Finance, and Warehouse, with the permission sets from SPEC section 2.
3. **`RoleProvisioner::forTenant($tenant)`** copies the default roles into a tenant (spatie teams).
4. **Owner safeguards:** the Owner role can't be deleted or emptied, and the last active owner can't be deactivated, demoted, or deleted. All tested.
5. **Frontend permissions:** the user's permission list is shared with Inertia for showing and hiding UI, through a typed `can('invoice.void')` helper. The server still checks every action.

### 0.6 `tenant:create` command
1. The command asks for the shop name, the branch name and code (e.g. `KLM`), the timezone (default `Asia/Jakarta`), and the owner's name and email. The owner password is typed in hidden or generated and shown once.
2. **`TenantProvisioner`** creates everything in one transaction: the tenant, first branch, default roles, and owner. Each Phase 1 module adds its own defaults here, for example payment methods, cash accounts, and expense categories.
3. Tests cover a full provision and rollback on failure.

### 0.7 Authentication
1. **Keep:** Breeze login, logout, forgot password, and reset password.
2. **Remove:** public registration and its pages and tests.
3. **Login rules:**
   - inactive or soft-deleted users can't log in
   - a deactivated user is logged out on their next request
   - login and reset are rate limited
   - the session is regenerated on login
4. **Security headers middleware:** CSP, HSTS in production, `X-Frame-Options`, `Referrer-Policy`, and `X-Content-Type-Options`.
5. **Profile:** remove self-delete (the owner manages users). Keep name, phone, language, and password change.
6. **Google login:** see decision 2.

### 0.8 Translations (i18n)
1. Add `lang/id.json` (source) and `lang/en.json`, loaded through `laravel-vue-i18n`.
2. A `SetLocale` middleware uses the user's locale, then the tenant default, then `id`.
3. Translate every string in the auth pages, layout, and profile.
4. A test fails the build if a key exists in one language file but not the other.

### 0.9 Support helpers (with unit tests)
- **`Money`:**
  - stores integer Rupiah
  - `format()` → `Rp 1.250.000`
  - `parse('1.250.000')` → 1250000
  - percentage with half-up rounding (tax, surcharge)

  The TS twin `formatRupiah` and a `<MoneyInput>` that formats as you type use the same rules.
- **`DocumentNumber::next($branch, 'INV')`:**
  - locks the `document_sequences` row with `FOR UPDATE` and resets per year
  - format `KLM/INV/2026/00001`
  - a concurrency test runs many parallel calls and asserts there are no duplicates
- **`Phone`:** accepts `08…`, `628…`, `+628…`, and spaces or dashes. Stores `+628…` and displays `0812-3456-7890`.
- **`PlateNumber`:** uppercases and normalizes spacing (`b1234xyz` → `B 1234 XYZ`), and validates the Indonesian pattern.

### 0.10 Audit log
1. **`Auditable` trait:** records old and new values on create, update, and delete for the models listed in rule 9. It skips noise fields like `updated_at`.
2. **`AuditLogger::record($action, $model, $reason, …)`:** for explicit actions that need a reason, such as void, revise, and cash adjust.
3. Each entry stores the user, branch, and IP. Entries are tenant-scoped and readable only with permission. A minimal list page for the Owner comes in Phase 2.

### 0.11 UI shell and shared components
1. **New `AppLayout`, mobile-first:**
   - bottom navigation (4–5 main items plus "Lainnya") on phones
   - a sidebar on tablet and desktop
   - a header with shop and branch name, the branch switcher (only when there are 2+ branches), and a user menu (language, profile, logout)
2. **Shared components in `Components/ui`:**
   - `Button`, `Input`, `MoneyInput`, `Select`, `Textarea`, `FormField` (label, hint, inline error)
   - `Dialog`, `Sheet` (bottom sheet on mobile), and `ConfirmDialog` (states what will happen; optional reason field)
   - `DataList` (table on desktop, cards on mobile), `Pagination` (no `v-html`), `SearchInput` (debounced, keeps the URL query)
   - `EmptyState`, `StatusBadge`, `PageHeader`, and toasts
3. **Cleanup:** remove the old Breeze components once nothing uses them.
4. **Dashboard:** a placeholder page with a welcome message and empty states, until Phase 2.
5. **Accessibility:** labelled inputs, visible focus, and 44px touch targets. Checked on 360px, 768px, and desktop widths in the browser.

### 0.12 Seeders
1. **`PermissionSeeder`** syncs permissions from the enum. It is safe to rerun on every deploy.
2. **`DemoSeeder`** runs in local/dev only and refuses to run in production. It creates a demo tenant with two branches and one user per role, with documented demo passwords in `README` (not real people).

## Definition of done
- All SPEC Phase 0 checklist items are ticked.
- `php artisan test`, `./vendor/bin/phpstan analyse`, `./vendor/bin/pint --test`, `npm run build`, and `npm run type-check` pass locally and in CI.
- Isolation tests exist for every model created in Phase 0.
- A fresh clone runs `composer install && npm ci && php artisan migrate --seed`, then `php artisan tenant:create` produces a working login on a phone-sized screen.

## Decisions needed before I start
1. **Retire the old modules now?** (Recommended.) While Phase 1 is in progress, the app will only offer login, profile, users and roles, and an empty dashboard. The alternative is keeping old modules running next to the new foundation. That means patching security holes in code we're about to delete, and a mixed schema.
2. **Google login:** drop it for the MVP (recommended: three accounts, fewer moving parts, one less external dependency), or keep it?
3. **Local database reset:** `migrate:fresh` wipes the current local `openbengkel` database (dev/test data only: 15 customers, 11 invoices, 32 parts). OK? I'll export it to a git-ignored file first if you want a copy.
4. **Branch workflow:** commit to a `phase-0` branch and merge when green (recommended), or commit straight to `main`?
