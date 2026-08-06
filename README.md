# Fandoogh Real Estate CRM

Fandoogh is an Android-first, multi-tenant CRM for real estate agencies. This repository is a monorepo containing the Laravel backend, Filament web panels, and Flutter mobile application.

## Repository layout

```text
backend/       Laravel 13, Filament 5, Sanctum, and Spatie Permission
mobile/        Flutter 3.44 Android application
docker/        PHP-FPM, Nginx, and MySQL development configuration
.github/       Continuous-integration workflows
```

The normative product and engineering documents are:

- `PROJECT_SPEC_MVP.md`
- `PROJECT_SPEC_ENTERPRISE.md`
- `CODING_STANDARDS.md`
- `DATABASE_DESIGN.md`

## Phase gate

Implementation is approval-gated. Phases 0 through 3 are complete. Phase 4 implementation is complete and its final external acceptance gates are recorded below. Phase 5 hardening and release work is in progress.

| Phase | Scope | Status |
|---|---|---|
| 0 | Repository, framework baselines, Docker, CI, shared HTTP/logging/test foundation | Complete |
| 1 | Identity and tenancy | Complete |
| 2 | Core domain | Complete |
| 3 | Web panels | Complete |
| 4 | Flutter MVP workflows | Complete |
| 5 | Hardening and release | In progress; IP staging deployed, external release inputs pending |

## Local development

Prerequisites: Docker Desktop with WSL2 integration, Git, Flutter 3.44.6, Android SDK, and Java compatible with Flutter.

```powershell
Copy-Item .env.example .env
Copy-Item backend/.env.example backend/.env
docker compose build app
docker compose up -d db
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate --seed --force
docker compose up -d app nginx
```

The application health route is available at `http://localhost:8080/up`.

Backend quality checks:

```powershell
docker compose run --rm app composer audit
docker compose run --rm app vendor/bin/pint --test
docker compose run --rm app vendor/bin/phpstan analyse --memory-limit=512M
docker compose run --rm -e APP_ENV=testing -e BCRYPT_ROUNDS=4 -e CACHE_STORE=array -e DB_DATABASE=fandoogh_test app composer test
```

Flutter quality checks:

```powershell
Set-Location mobile
flutter pub get
dart format --output=none --set-exit-if-changed .
flutter analyze
flutter test
flutter build apk --debug
```

Documentation check:

```powershell
python scripts/check_markdown.py
```

## Phase 0 acceptance record

Phase 0 was verified on 2026-07-26 with PHP 8.3.32, Laravel 13.22.0, Filament 5.7.3, MySQL 8.4.10, Nginx 1.28.3, Flutter 3.44.6, and Dart 3.12.2.

- Docker Compose services start and MySQL reports healthy.
- `/up`, `/`, and the standard API error envelope return the expected response and request ID.
- Composer validation and security audit pass.
- Pint and PHPStan level 8 pass.
- All Laravel and Flutter tests pass.
- The Android debug APK builds successfully.
- Domain migrations were deferred to Phase 2; panels and operational screens remain outside Phase 0.

## Phase 1 acceptance record

Phase 1 was verified on 2026-07-26 against a fresh MySQL 8.4 database.

- Agencies, agency settings, users, authorization, database sessions, and Sanctum token migrations pass from empty schema.
- The deterministic seeder maintains 3 fixed roles and 38 exact permissions without creating default users or passwords.
- Mobile login, profile, logout, logout-all, 30-day expiry, ability checks, throttling, and forced password replacement are implemented.
- Web sessions and mobile bearer tokens are mutually isolated.
- Tenant context fails before SQL when missing and scopes tenant-owned queries to exactly one agency.
- Inactive users/agencies, cross-agency access, immutable tenant membership, Policies, activation, suspension, and token revocation are tested.
- The Phase 1 suite passes 31 tests with 138 assertions; Pint and PHPStan level 8 pass.

Implemented mobile identity endpoints:

```text
POST /api/v1/auth/login
GET  /api/v1/auth/me
POST /api/v1/auth/logout
POST /api/v1/auth/logout-all
PUT  /api/v1/profile/password
```

## Phase 2 acceptance record

Phase 2 was verified on 2026-07-26 against MySQL 8.4 using the Phase 1 tenant isolation foundation.

- Owners, Properties, ownership links, private images, Property notes/history, Customers, and Customer notes are represented by tenant-safe migrations and models.
- Property codes are generated transactionally from a locked agency sequence; code, currency, and tenant ownership remain immutable.
- Ownership sets require at least one Owner, exactly one primary Owner, and either no percentages or percentages totaling exactly `100.00`.
- Property and Customer state transitions, assignment rules, archive/delete/restore constraints, immutable history, private uploads, and optimistic concurrency are enforced by Services and Policies.
- Operational route-model binding runs only after tenant establishment and returns `RESOURCE_NOT_FOUND` for inaccessible tenant identifiers.
- The combined Phase 0–2 suite passes 48 tests with 202 assertions; Pint and PHPStan level 8 pass.

Implemented Phase 2 mobile resources:

```text
/api/v1/owners
/api/v1/properties
/api/v1/properties/{property}/status
/api/v1/properties/{property}/history
/api/v1/properties/{property}/notes
/api/v1/properties/{property}/images
/api/v1/customers
/api/v1/customers/{customer}/notes
```

Delete, restore, reassignment administration, Saved Filters, dashboards, reports, and web panels remain outside the mobile Phase 2 API.

## Phase 3 acceptance record

Phase 3 was verified on 2026-07-26 with three isolated Filament panels and the shared Phase 2 Services and Policies.

- `/control` is restricted to active Super Admins and contains aggregate-only platform dashboards, Agency administration, and Agency Manager administration.
- `/agency` is restricted to active Agency Managers in active agencies and contains operational dashboards, Properties, Owners, Customers, Agents, Saved Filters, reports, search, settings, and profile management.
- `/agent` is restricted to active Agents in active agencies and scopes Properties and Customers to the current Agent.
- Property images, owners, notes, history, assignment, status, archive, soft delete, and restore operations use the existing domain Services and Policies.
- Saved Filters enforce exact server allowlists, unique names, 50 rows per user/module, a single transactional default, and current-user ownership.
- Global search is tenant- and role-scoped, accepts 2 to 100 characters, returns no more than 20 summaries per entity, and does not expose full records.
- Reports use inclusive Agency-timezone periods limited to 366 days; dashboard cache keys contain role, user, and Agency context and expire within 60 seconds.
- Forced password replacement redirects web users to their current panel profile, and password changes revoke mobile tokens.
- Role-based web acceptance covers all three dashboards, operational lists and create forms, reports, search, settings, profiles, and cross-panel denial.
- The combined Phase 0–3 suite passes 54 tests with 271 assertions; Pint and PHPStan level 8 pass.

Implemented Phase 3 mobile capabilities:

```text
GET    /api/v1/dashboard
GET    /api/v1/search?q={term}
GET    /api/v1/reports/me?from={Y-m-d}&to={Y-m-d}
GET    /api/v1/saved-filters
POST   /api/v1/saved-filters
PATCH  /api/v1/saved-filters/{saved_filter}
DELETE /api/v1/saved-filters/{saved_filter}
```

Operational details and verification commands are documented in `PHASE_3_WEB_OPERATIONS.md`.

## Phase 4 implementation record

Phase 4 was implemented on 2026-07-28 with Flutter 3.44.6, Dart 3.12.2, Android SDK 36, and the Phase 0–3 Laravel API.

- Sanctum login, secure session restoration, logout, logout-all, required password replacement, authentication expiry, and suspended-agency routing are implemented.
- The personal dashboard, global search, server-paginated Property and Customer lists, filters, Saved Filters, pull-to-refresh, and explicit loading/empty/error/success states are implemented.
- Property workflows cover create/edit, Owner lookup/create, optimistic concurrency, status, private images with per-upload progress/retry, notes, and history.
- Customer workflows cover create/edit, optimistic concurrency, filters, detail, and notes.
- My Report and Profile cover date ranges, profile editing, password replacement, and local/all-device logout.
- The Android debug APK builds for API 24+ and arm64-v8a, armeabi-v7a, and x86_64.
- Emulator acceptance passed on Android 17 / API 37 for login, dashboard, session restoration, main navigation, empty states, and populated Property results.
- Physical acceptance passed on a Xiaomi M2012K11AG with Android 13 / API 33 for authentication, navigation, search, Saved Filters, Property status/notes/images/history, Customer notes, reports, profile, password rotation, and both logout modes.
- Physical verification found and fixed premature dialog-controller disposal; the regression path is now covered by an automated Saved Filter lifecycle test. Profile password validation also matches the backend security policy.
- Flutter analysis passes with no issues; 9 Flutter tests cover error mapping, auth expiry, pagination, dialog lifecycle, 200% text scaling, and primary Property/Customer list and create paths.
- The combined backend suite passes 55 tests with 276 assertions; PHPStan level 8 and Pint pass.

The Phase 4 release-engine download gate was closed on 2026-07-29. The earlier HTTP 403 was caused by the restricted execution environment's proxy/network policy, not a missing Flutter artifact: the exact official artifact now returns HTTP 200, a full Release APK validation build succeeds, and a second Gradle Release build succeeds offline from the populated cache. Phase 5 remains open only for production release inputs and the production-signed artifact. Phase 4 commands and acceptance evidence are documented in `PHASE_4_FLUTTER.md`; Phase 5 status and deployment operations are documented in `PHASE_5_HARDENING_RELEASE.md` and `DEPLOYMENT_RUNBOOK.md`.

## Phase 5 implementation record

Phase 5 repository-local hardening was executed on 2026-07-29. Final phase closure remains blocked only by production release inputs and the signed Android artifact.

- Dashboard and Report aggregates are tenant-constrained and use bounded grouped queries instead of N+1 loops.
- Cross-tenant aggregate disclosure and missing Filament runtime assets found during UAT are fixed with regression coverage.
- Empty-schema, seeded-snapshot, idempotent seed, no-op migration, and rollback/forward rehearsals pass on isolated MySQL databases.
- Composer validation/audit, Pint on 302 files, PHPStan on 272 files, and 58 backend tests with 292 assertions pass without skips.
- Flutter format/analyze, 9 tests, and the version `0.1.0+1` debug APK pass; Flutter Release engine artifacts are cached and validated, while unsigned production Release builds remain intentionally rejected.
- Real-browser smoke tests pass all three panels, operational lists/reports, and cross-panel denial. Temporary acceptance data was removed afterward.
- Production environment, TLS Nginx, atomic deployment, backup, verification, rollback, secure Android signing, and artifact procedures are documented.
- An Ubuntu 24.04 IP-only staging server is live with MySQL 8.4.11, Nginx/PHP-FPM, UFW, SSH key-only access, atomic release layout, migrations, deterministic seed data, production caches, and HTTP smoke checks. The no-secret evidence and remaining TLS/backup/admin gates are recorded in `SERVER_DEPLOYMENT_20260729.md`.

## Environment policy

The committed values are development defaults only. Production secrets are supplied by the deployment environment and are never committed. MySQL is the only application database; queues use the synchronous driver, cache uses files, and uploads use private local storage in the MVP.
