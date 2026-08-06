# Phase 5 Hardening and Release

Phase 5 started on 2026-07-29. Its exit condition is every release gate in `PROJECT_SPEC_MVP.md` Section 21.3 passing. Work must not advance beyond this phase while any gate below is open.

## Prerequisites and external inputs

Repository-local work can run with Docker Desktop, MySQL 8.4, PHP 8.3, Flutter 3.44.6, Java/Android SDK, and the approved physical Android device.

Final production release additionally requires:

- a production hostname, DNS, TLS certificate, least-privilege database credentials, backup destination, and deployment operator;
- final Android application ID confirmation and version approval;
- production Android keystore, alias/passwords, encrypted keystore backup, and store access;
- an approved unrestricted build environment. Flutter Release engine access was verified and cached on 2026-07-29; restricted Sandbox processes still cannot be used for network-dependent Flutter builds.

No real secret belongs in Git. `backend/.env.production.example` and `mobile/android/key.properties.example` contain placeholders only.

## Hardening changes

- Agency Dashboard and Report agent aggregates are explicitly constrained by `agency_id`; a cross-tenant agent name/identifier disclosure found during the IDOR audit is closed.
- Dashboard and Report agent counts use grouped aggregate queries instead of per-agent N+1 queries. Automated budgets remain at no more than 10 Dashboard queries and 12 Report queries with 16 agents in the test tenant.
- Composer tests run PHPUnit directly so the test environment is loaded before Laravel bootstrap. Local/Docker test commands must still set `DB_DATABASE=fandoogh_test` explicitly.
- Android release builds no longer fall back to the debug key. Missing or incomplete production signing input fails the build with an actionable error.
- The app version matches MVP release `0.1.0+1`; signing material and keystores are ignored by Git.
- Real-browser UAT exposed missing generated Filament JavaScript/CSS assets. Composer now publishes them on every install/update, the deployment runbook publishes them explicitly, generated output is ignored, and a regression test requires the critical runtime files.
- A production environment template, TLS Nginx example, atomic deployment/rollback procedure, backup steps, health verification, and Android artifact checklist are supplied in `DEPLOYMENT_RUNBOOK.md`.

## Release gate commands

Backend commands must explicitly target the isolated test database:

```powershell
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=fandoogh_test -e CACHE_STORE=array app composer validate --strict
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=fandoogh_test -e CACHE_STORE=array app composer audit --locked --no-interaction
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=fandoogh_test -e CACHE_STORE=array app vendor/bin/pint --test
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=fandoogh_test -e CACHE_STORE=array app vendor/bin/phpstan analyse --memory-limit=512M
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=fandoogh_test -e CACHE_STORE=array app composer test
```

Flutter and documentation gates:

```powershell
Set-Location mobile
dart format --output=none --set-exit-if-changed .
flutter analyze
flutter test
flutter build apk --debug
Set-Location ..
python scripts/check_markdown.py
```

## Migration rehearsal

Use two isolated, fixed-name rehearsal databases. Never use the development or production database. The rehearsal must prove:

1. all migrations apply to an empty MySQL 8.4 database;
2. deterministic roles/permissions seed twice without drift;
3. a seeded release snapshot reports no unexpected pending migration;
4. the latest reversible migration rolls back and reapplies;
5. the health endpoint boots against the rehearsed schema.

Release 0.1 has no earlier released MVP snapshot. The “previous MVP snapshot” gate is therefore recorded as not applicable for the first release; the current seeded snapshot rehearsal becomes the baseline required by the next release.

## Gate status

| Gate | Status | Evidence or blocker |
|---|---|---|
| Automated backend tests | Pass | 61 tests and 303 assertions pass. |
| Tenant/IDOR suite, no skips | Pass | Full suite has no skips; aggregate cross-tenant regression passes. |
| Empty and seeded migration rehearsal | Pass | 15 migrations, 3 roles, 38 permissions, idempotent seed, no-op migrate, and rollback/forward pass. |
| Static analysis and formatting | Pass | Pint passes after formatting the complete backend; PHPStan baseline remains green; Flutter analyze reports no issues. |
| Dependency security audit | Pass | Composer validates strictly and reports no locked-package security advisory. |
| Web and Android smoke test | Partial pass | Glassmorphism/Persian web UAT passes at desktop and mobile sizes, and the signed internal Release APK passes install/launch/visual checks on the first physical phone; phones two and three remain pending. |
| Severity 1/2 defects | Pass | Cross-tenant aggregate disclosure and missing Filament assets were fixed; no severity 1/2 defect remains open in tested scope. |
| Signed release artifact | Internal pass / production blocked externally | The private internal APK is signed and verified with v2/v3. Production application ID, HTTPS API URL, production signing input, and store approval are still absent. |

## Acceptance evidence

- The production-style Laravel cache sequence (`config`, events, routes, views, Blade icons, and Filament) completes and clears successfully.
- `/up` returns HTTP 200 with database connectivity, a request ID, and the development Nginx security headers.
- Real-browser UAT passes the Control dashboard and Agency list; Agency Manager Property, Customer, and aggregate Report screens; Agent-assigned Property, Customer, and personal Report screens; and a cross-panel Agent-to-Agency denial returning 403.
- Temporary UAT users, sessions, Agency, Owner, Property, and Customer were removed after the smoke test. The development database retains only the deterministic role/permission baseline.
- Flutter format and analysis pass, all 9 Flutter tests pass, and the glassmorphism/Persian internal Release APK builds successfully.
- The debug APK is 159,369,026 bytes with SHA-256 `0EAF1E8B9ACD0B373721CA7E49A19EABE528F15D01D25B44EA2CDC66B69209AA`.
- A negative release-build test confirms that an unsigned Release is rejected with the expected signing-configuration error.
- The former Flutter Release artifact HTTP 403 is closed: the exact official artifact returns HTTP 200, an online Release validation APK build succeeds, and a second Gradle Release build succeeds offline from the populated cache. The temporary validation keystore, signing properties, and test-signed APK were removed afterward.
- A separate `internal` Android flavor now produces a privately signed Release APK for direct testing without a store. It uses package `com.fandoogh.crm.fandoogh_crm.internal`, version `0.1.0-internal+1`, a temporary staging API endpoint, and cleartext HTTP only in that internal flavor; the `production` flavor remains HTTPS-only.
- The internal APK installs beside the existing debug application, launches on the Android emulator, keeps a live process, and emits no Flutter or Android runtime crash. The emulator itself had no default network route, so API acceptance remains assigned to the three approved physical phones.
- Internal APK: `mobile/build/app/outputs/flutter-apk/app-internal-release.apk`, 57,111,144 bytes, SHA-256 `4804765979658926B5981B5F600F1FAD479677308AD17929E045C2FDD064B24D`. APK Signature Scheme v2/v3 verification passes with the RSA-4096 internal-test certificate SHA-256 `4C18BC6EDB9E5D78CE438A6B217A30D8E2FC9C78F9564FF2A064960FDB7760F1`.
- The APK and all three Filament panels use the «ملک بان» brand, the «طراحی‌شده توسط فندوق استودیو» credit, local Vazirmatn font, RTL Persian text, and a responsive glassmorphism theme.
- The internal APK installed, launched, and passed visual inspection on the first physical phone (`M2012K11AG`). The second and third physical-device acceptance runs remain pending connection.
- Ubuntu 24.04 staging is operational with MySQL 8.4.11, Nginx, PHP-FPM, Composer dependencies, all 15 migrations, deterministic roles/permissions, production optimization, UFW, SSH key-only access, health/API responses, Vazirmatn, Persian 403 handling, and Filament assets pass. See the local deployment evidence when server access is required.
- The IP endpoint is intentionally temporary HTTP. The first approved Super Admin now exists with mandatory password rotation. A domain, TLS, secure-cookie activation, and an off-host backup target are still required before real data or production acceptance.

## Environment incident record

During the first local full-suite run on 2026-07-29, `php artisan test` booted using the Docker development database before PHPUnit environment overrides were applied. The run was stopped, but Laravel `RefreshDatabase` had already rebuilt the development schema and removed the acceptance-only Agency, Agent, Owner, Property, and Customer records. Those records were explicitly non-seeded and no repository backup exists. The official Seeder restored the deterministic role/permission catalog; it cannot reconstruct the acceptance-only records. No production system or production data was involved.

The preventive change is permanent: Composer now invokes `vendor/bin/phpunit` directly, CI uses `composer test`, and every documented Docker test command supplies `APP_ENV=testing` and `DB_DATABASE=fandoogh_test` before Laravel bootstraps.
