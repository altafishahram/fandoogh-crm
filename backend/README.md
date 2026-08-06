# بک‌اند ملک بان

بک‌اند Laravel سامانه مدیریت املاک «ملک بان»، طراحی‌شده توسط فندوق استودیو. این سامانه شامل احراز هویت، مجوزهای دسترسی، نشست‌های وب، ورود موبایل با Sanctum، جداسازی امن داده‌های آژانس‌ها، پنل‌های مدیریتی، جست‌وجو، فیلترهای ذخیره‌شده، داشبورد و گزارش‌ها است.

## Local commands

Run commands from the repository root:

```powershell
docker compose run --rm app composer install
docker compose run --rm app php artisan migrate --seed --force
docker compose up -d app nginx
```

The health endpoint is `http://localhost:8080/up` and the API base path is `/api/v1`.

No production or demo user is created by the seeder. Platform identities must be provisioned through an approved secure operational process.

## Quality gate

```powershell
docker compose run --rm app composer audit
docker compose run --rm app vendor/bin/pint --test
docker compose run --rm app vendor/bin/phpstan analyse --memory-limit=512M
docker compose run --rm -e APP_ENV=testing -e DB_DATABASE=fandoogh_test -e BCRYPT_ROUNDS=4 -e CACHE_STORE=array app php artisan test
```

## Web panels

- `/control`: Super Admin only; aggregate platform dashboard, Agencies, and Agency Managers.
- `/agency`: Agency Manager only; complete Agency operations and settings.
- `/agent`: Agent only; current Agent dashboard and assigned operational records.

No production or demo identity is seeded. A securely provisioned Super Admin is required before `/control` can be used. Role access, active-user state, active-Agency state, tenant context, and forced password replacement are enforced on every authenticated panel request.
