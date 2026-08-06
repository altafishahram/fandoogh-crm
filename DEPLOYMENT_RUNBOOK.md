# MVP Deployment and Rollback Runbook

This runbook covers the release 0.1 single-VPS topology. Docker Compose remains development-only. Commands are examples and must be run by an approved operator after replacing hostnames and release identifiers.

## Production prerequisites

- Linux VPS with Nginx, PHP-FPM 8.3 and required PHP extensions, Composer 2, MySQL 8.4, Git, and cron;
- DNS and a valid TLS certificate for the API/web hostname;
- least-privilege MySQL application and backup credentials;
- `/var/www/fandoogh/releases`, `/var/www/fandoogh/shared/storage`, and a root-owned shared production environment file;
- a tested database backup destination outside the application release directory;
- CI release gates passing for the exact commit;
- for Android delivery, the final application ID, HTTPS API URL, production keystore, aliases/passwords, and store account access.

Use `backend/.env.production.example` as a checklist. Generate `APP_KEY` securely on the target host. Never copy a local/test key, credential, database, or acceptance identity into production.

## Preflight

1. Record the commit, release name, PHP/MySQL versions, operator, start time, and approved maintenance window.
2. Confirm `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, secure/encrypted session cookies, the private local filesystem, and the intended database.
3. Run the repository gates in `PHASE_5_HARDENING_RELEASE.md` on the exact commit.
4. Confirm at least 20 percent free disk space and that `storage` and `bootstrap/cache` are the only application-writable paths.
5. Verify the health endpoint on the current release and perform a restore test of the most recent backup in an isolated database.

## Backup

Create a consistent MySQL backup before maintenance or schema changes. Store it outside the release tree, restrict it to the operator, and record its checksum. A representative command is:

```bash
mysqldump --single-transaction --routines --triggers --set-gtid-purged=OFF \
  --host=127.0.0.1 --user=fandoogh_backup --password fandoogh \
  | gzip > /srv/backups/fandoogh-before-RELEASE.sql.gz
sha256sum /srv/backups/fandoogh-before-RELEASE.sql.gz
```

Supply the password interactively or through an operator-owned MySQL option file; do not place it in shell history.

## Deploy

Assume `RELEASE=/var/www/fandoogh/releases/RELEASE_ID` and shared paths under `/var/www/fandoogh/shared`.

```bash
git clone --no-checkout REPOSITORY_URL "$RELEASE"
git -C "$RELEASE" checkout --detach APPROVED_COMMIT
cd "$RELEASE/backend"
composer install --no-dev --no-interaction --prefer-dist --classmap-authoritative
php artisan filament:assets
ln -s /var/www/fandoogh/shared/.env .env
rm -rf storage
ln -s /var/www/fandoogh/shared/storage storage
php artisan about --only=environment
php artisan migrate --pretend --force
php artisan down --refresh=15 --secret="OPERATOR_GENERATED_BYPASS"
php artisan migrate --force
php artisan optimize
ln -sfn "$RELEASE" /var/www/fandoogh/current.next
mv -Tf /var/www/fandoogh/current.next /var/www/fandoogh/current
sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx
php artisan up
```

If `php artisan optimize` reports an incompatible cache, clear only that cache and document the exception. Run Laravel Scheduler every minute as the deploy user:

```cron
* * * * * cd /var/www/fandoogh/current/backend && php artisan schedule:run >> /dev/null 2>&1
```

## Verify

Within the maintenance window:

1. Verify `/up` returns success without secrets and that a unique `X-Request-Id` is returned.
2. Verify HTTPS redirect, certificate chain, HSTS, content-type, frame, referrer, and permissions headers.
3. Smoke-test login and the role-correct dashboard for Super Admin, Agency Manager, and Agent test identities provisioned specifically for production acceptance.
4. Smoke-test one tenant-safe Property read, Customer read, search, report, and authenticated image response; do not create or alter customer data unless the acceptance plan authorizes it.
5. Confirm scheduler execution, writable-path ownership, application logs, Nginx/PHP logs, database error logs, and P95 response samples.
6. Revoke and remove temporary acceptance identities/tokens according to the acceptance plan.

## Rollback

For an application-only defect, enable maintenance mode, atomically repoint `current` to the previous release, reload PHP-FPM/Nginx, clear/rebuild compatible caches, and bring the application back up.

Do not automatically roll back a database migration. If the new release wrote data using the new schema, keep the compatible application in maintenance mode and follow the migration-specific forward fix or approved restore procedure. Restoring the pre-deploy backup discards post-backup writes and therefore requires explicit incident approval.

## Android release

1. Confirm the final application ID before the first store upload; it becomes the permanent store identity.
2. Keep the production keystore and `key.properties` outside version control and protected by encrypted backup and limited operator access.
3. Run network-dependent Flutter commands from an approved unrestricted build terminal. The restricted automation Sandbox uses a non-routable proxy and must not be used for artifact downloads. Keep the official Flutter storage endpoint; do not substitute an untrusted mirror:

```powershell
Set-Location mobile
flutter doctor -v
flutter pub get
flutter precache --android
```

4. Copy `mobile/android/key.properties.example` to the ignored `key.properties`, set the HTTPS API URL, and build:

```powershell
Set-Location mobile
flutter build appbundle --release --dart-define=API_BASE_URL=https://crm.example.com/api/v1
```

5. Verify the AAB/APK signature, version `0.1.0+1`, application ID, API URL, minimum SDK, and SHA-256 checksum before store upload.
6. Retain the signed artifact, mapping files if produced, checksum, commit, Flutter version, and release notes in access-controlled release storage.
