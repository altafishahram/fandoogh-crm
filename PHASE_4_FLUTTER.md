# Phase 4 Flutter Operations and Acceptance

## Status

Phase 4 is complete. The emulator, physical-device, debug APK, and Flutter Release engine artifact gates pass. A full Release APK validation build and a second offline Gradle Release build succeeded on 2026-07-29. Production signing and store delivery remain Phase 5 gates.

Phase 5 hardening and release work is now in progress; see `PHASE_5_HARDENING_RELEASE.md`.

## Runtime baseline

| Component | Verified version |
|---|---|
| Flutter | 3.44.6 stable |
| Dart | 3.12.2 |
| Android SDK | 36.0.0 |
| Debug APK minimum SDK | 24 |
| Debug APK target SDK | 36 |
| Emulator | Android 17 / API 37, x86_64 |
| Physical device | Xiaomi M2012K11AG, Android 13 / API 33, arm64-v8a |

The generated debug APK contains `arm64-v8a`, `armeabi-v7a`, and `x86_64` native code.

## Application structure

The mobile application uses the project-standard stack:

- Riverpod for dependency injection and feature state;
- Dio for API calls, request IDs, bearer tokens, timeouts, and normalized failures;
- `go_router` for session-aware routing;
- `flutter_secure_storage` for the Sanctum token; and
- a small Android platform channel using `ACTION_OPEN_DOCUMENT` for image selection.

The image picker deliberately requires no broad storage permission. The selected content is copied to the application cache before multipart upload. Upload progress and retry state are shown for the pending image.

Feature code lives under `mobile/lib/features`. Shared authentication, storage, networking, routing, UI states, and field catalogs live under `mobile/lib/core`.

## API configuration

The default development endpoint is suitable for the Android emulator:

```text
http://10.0.2.2:8080/api/v1
```

Supply every non-local endpoint at build time and use TLS:

```powershell
flutter build apk --release --dart-define=API_BASE_URL=https://crm.example.com/api/v1
```

Cleartext HTTP is enabled only by the Android debug manifest. The main/release manifest does not opt into cleartext traffic.

## Implemented paths

### Authentication

- Splash and secure session restoration
- Agent login
- Required password replacement and token rotation
- `401` credential clearing and login redirect
- `PASSWORD_CHANGE_REQUIRED` routing
- Blocking `AGENCY_INACTIVE` screen
- Current-device and all-device logout

### Dashboard and discovery

- Personal status totals
- Active-customer total
- Recent Properties and notes
- Global Property, Owner, and Customer search
- Quick create actions

### Properties

- Server pagination, search, status/type/transaction filters, Saved Filters, and pull-to-refresh
- Detail and edit screens
- Create flow with Owner search or inline Owner creation
- Optimistic concurrency through `expected_updated_at`
- Status transition with reason
- Private authenticated image display, upload progress/retry, and deletion
- Notes create/edit/delete
- Immutable history display

### Customers

- Server pagination, search, status/intent filters, Saved Filters, and pull-to-refresh
- Detail, create, and edit screens
- Contact preferences and Property requirements
- Optimistic concurrency through `expected_updated_at`
- Notes create/edit/delete

### Report and profile

- Date-range My Report
- Read and edit current profile
- Change password
- Logout and logout-all

The profile editor is backed by `PATCH /api/v1/profile`, which was added with validation, current-user authorization, and a feature test because the endpoint existed in the MVP contract but was missing from the Phase 3 route implementation.

## Quality gates

Run Flutter checks from `mobile`:

```powershell
dart format --output=none --set-exit-if-changed lib test
flutter analyze --no-pub
flutter test --no-pub
flutter build apk --debug --no-pub
```

`--no-pub` is appropriate only after `pubspec.lock` dependencies are present locally. On an unrestricted workstation, run `flutter pub get` first and omit the flag.

Run backend regression checks from the repository root:

```powershell
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=fandoogh_test -e BCRYPT_ROUNDS=4 -e CACHE_STORE=array app php artisan test
docker compose exec -T app vendor/bin/phpstan analyse --memory-limit=1G --no-progress
docker compose exec -T app vendor/bin/pint --test
```

Verified results on 2026-07-29:

- Flutter analyze: no issues
- Flutter tests: 9 passed
- Backend tests: 55 passed, 276 assertions
- PHPStan level 8: no errors
- Pint: 300 files passed
- Debug APK: built successfully

The debug artifact is generated at:

```text
mobile/build/app/outputs/flutter-apk/app-debug.apk
```

## Emulator acceptance record

The debug APK was installed on `emulator-5554` and exercised against the Docker API through `10.0.2.2:8080`.

Passed paths:

- Agent login against the real Sanctum endpoint
- Dashboard data load
- Secure-token session restoration after force-stop and restart
- Home, Properties, Customers, My Report, and Profile navigation
- Loading and empty Property/Customer states
- Populated Property list from real tenant data
- Accessibility semantics for main controls and navigation
- Widget rendering at 200% text scale without an exception

An acceptance-only Agency, Agent, Property, Owner, and Customer were created in the local development database. They are not created by application seeders and are never created in production.

## Physical-device acceptance record

The debug APK was installed on a Xiaomi M2012K11AG running Android 13 / API 33 and exercised against the Docker API through an ADB reverse tunnel to `127.0.0.1:8080`.

Passed paths:

- Agent login, secure session restoration after force-stop/restart, password rotation, current-device logout, and all-device logout
- Dashboard totals, recent records, global Property/Owner search, all five primary navigation destinations, My Report, and Profile
- Property list, filter application, Saved Filter create/apply/delete, create-form validation, detail, status transition and restoration, notes create/delete, immutable history, and image upload/display/delete
- Customer list, create-form validation, detail, and notes create/edit/delete
- Controlled Android document selection using a test-only PNG without reading or selecting personal media
- Final cleanup with zero active acceptance tokens, Saved Filters, notes, or images and the acceptance Property restored to `available`

Physical verification exposed a dialog-lifecycle defect: several local `TextEditingController` instances were disposed before their route exit animations completed. Dialog-owned state now controls those lifetimes, and a Saved Filter regression test reproduces the former failure and passes with the fix. Profile password validation was also aligned with the backend requirement for length, mixed case, numbers, and symbols.

## Release artifact resolution record

The earlier Release build reached Gradle dependency resolution but received HTTP 403 for `arm64_v8a_release`, `x86_64_release`, and `flutter_embedding_release`. Investigation showed that the restricted execution environment set `HTTP_PROXY` and `HTTPS_PROXY` to a non-routable local endpoint. Outside that restricted network context, Flutter reported all network resources available and the exact official engine artifact returned HTTP 200.

The fix is to run Flutter's network-dependent cache/build steps from an approved unrestricted terminal with HTTPS access to:

```text
https://storage.googleapis.com/download.flutter.io/
https://pub.dev/
```

Do not replace the official storage base URL with an untrusted mirror. Populate the official cache with:

```powershell
flutter pub get
flutter precache --android
flutter build apk --release --dart-define=API_BASE_URL=https://your-api.example/api/v1
```

Verified on Flutter 3.44.6 / engine `83675ed27633283e7fc296c8bca22e841224c096`:

- the exact `flutter_embedding_release` POM returned HTTP 200;
- the full online Release validation build produced a 57,428,922-byte APK without HTTP 403;
- a subsequent `:app:assembleRelease --offline` completed successfully with 177 tasks, proving the Flutter engine binaries are in the Gradle cache; and
- temporary validation signing material and the test-signed APK were removed immediately after validation.

The repository still rejects Release builds without explicit signing configuration. Production signing and store delivery remain Phase 5 work.
