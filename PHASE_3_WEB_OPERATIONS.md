# Phase 3 Web Operations

This document is the implementation and operations record for Phase 3. It does not authorize or include Phase 4 Flutter work.

## Panel boundaries

| Path | Exact role | Data boundary |
|---|---|---|
| `/control` | `super-admin` | Platform aggregates, Agencies, and Agency Managers only |
| `/agency` | `agency-manager` | Current active Agency |
| `/agent` | `agent` | Current active Agency and assigned operational records |

Inactive users, soft-deleted users, and users in suspended Agencies cannot enter a panel. A user cannot enter a panel assigned to another role. A user with `must_change_password` is redirected to the profile page of the current panel before any other page is served.

## Control panel

The Control panel provides:

- aggregate active/suspended Agency and active manager/Agent counts;
- aggregate Property and Customer counts grouped by Agency without tenant record content;
- Agency list, search, active-state filter, create, view, edit, activate, and suspend;
- Agency Manager list, Agency and active-state filters, create, view, edit, activate/deactivate, password reset, and workload context;
- current-user profile and password change.

Agency activation requires an active Agency Manager. Suspending an Agency revokes its mobile tokens and blocks its web users on the next request. Activated Agency slugs are immutable.

## Agency panel

The Agency panel provides:

- dashboard status/customer/workload/recent-history summaries;
- Property CRUD, search, filters, sort, assignment, owner selection, inline Owner creation, image management, notes, history, status changes, archive, trash, and restore;
- Owner CRUD, contact search, linked-Property count, trash, and dependency-aware restore;
- Customer CRUD, assignment, requirements, notes, status/conversion management, trash, and restore;
- Agent create/edit, workload counts, activate/deactivate, and password reset;
- current-user Saved Filters;
- policy-scoped global search;
- synchronous Agency-timezone reports;
- Agency contact, locale, timezone, numbering prefix, and default page-size settings;
- current-user profile and password change.

The Property code prefix becomes immutable after the first Property code is allocated.

## Agent panel

The Agent panel provides:

- personal assigned-inventory, active-Customer, recent-Property, and accessible-note dashboard data;
- assigned Property list/create/view/edit, owner selection/creation, image management, notes, and permitted status transitions;
- assigned Customer list/create/view/edit and notes;
- current-user Saved Filters;
- policy-scoped global search and personal report;
- current-user profile and password change.

Assignment and transaction-type controls that are manager-only are not dehydrated into Agent edit requests. Services still enforce the same boundary if a client submits crafted input.

## Saved Filters

Saved Filters use the `properties`, `owners`, or `customers` module allowlists. Names are unique per user/module. Each user may store at most 50 filters per module and at most one default per module. Setting a new default clears the previous default in the same transaction. Delete is a hard delete.

Filter payloads reject unknown fields, raw URLs, NUL bytes, semicolons, SQL-style comments, nested objects, and unsupported sort columns. Loading a legacy stored filter sanitizes removed keys and returns warnings.

## Search and reports

Global search trims terms, requires 2 to 100 characters, escapes wildcard input, returns at most 20 summary rows per entity, and applies tenant and Agent-assignment scope.

Reports accept inclusive `from` and `to` dates in Agency timezone and reject reversed ranges or periods longer than 366 days. Agency reports group current Property and Customer counts by operational dimensions and include dated new/closed activity plus Agent workload. Agent reports include current assigned inventory and dated personal activity.

## Local operation

Start the backend from the repository root:

```powershell
docker compose up -d db app nginx
docker compose exec -T app php artisan migrate --seed --force
```

Open `http://localhost:8080/control`, `http://localhost:8080/agency`, or `http://localhost:8080/agent` with a securely provisioned identity of the exact matching role. The deterministic seeder creates roles and permissions only; it never creates user credentials.

## Acceptance gate

Run from the repository root:

```powershell
docker compose exec -T app vendor/bin/pint --test
docker compose exec -T app vendor/bin/phpstan analyse --memory-limit=1G --no-progress
docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=fandoogh_test -e BCRYPT_ROUNDS=4 -e CACHE_STORE=array app php artisan test
python scripts/check_markdown.py
```

Phase 3 is accepted only when cross-panel denial and the authenticated role paths pass for Super Admin, Agency Manager, and Agent. Phase 4 remains pending explicit user approval.
