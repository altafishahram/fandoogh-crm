# Real Estate Agency CRM — Coding Standards

**Document version:** 1.0  
**Status:** Mandatory engineering standard  
**Last updated:** 2026-07-25  
**Applies to:** Laravel backend, Filament panels, REST API, Flutter application, database migrations, tests, and repository workflow

## 1. Purpose

These standards make implementation predictable across developers and prevent business rules, security, and tenancy behavior from depending on individual style.

Rules use:

- **MUST / MUST NOT** — enforced requirement.
- **SHOULD / SHOULD NOT** — default; deviation requires a code-review explanation.
- **MAY** — optional and context-dependent.

When documents conflict:

1. Security and tenant-isolation requirements in `PROJECT_SPEC_MVP.md`
2. Physical schema in `DATABASE_DESIGN.md`
3. This coding standard
4. Framework defaults

## 2. General Engineering Principles

Code MUST be:

- Correct before clever.
- Explicit at security and transaction boundaries.
- Small enough to review.
- Testable without framework UI automation where domain behavior is concerned.
- Free of speculative Enterprise functionality.
- Written in English for identifiers, comments, commits, logs, API fields, and documentation.

Apply SOLID pragmatically:

- One class has one reason to change.
- Extend behavior through focused collaborators, not large condition blocks.
- Subtypes honor contracts.
- Interfaces describe cohesive client needs.
- High-level Services depend on contracts, not Eloquent implementations.

Avoid abstraction without demonstrated duplication or a required boundary. A small explicit class is preferred over a generic framework inside the application.

## 3. Source Layout

### 3.1 Laravel

```text
app/
  Application/
    Contracts/
    DTOs/
    Services/
  Domain/
    Agency/
      Enums/
      Events/
      Exceptions/
    Customer/
    Owner/
    Property/
    User/
  Filament/
    Control/
    Agency/
    Agent/
  Http/
    Controllers/Api/V1/
    Middleware/
    Requests/Api/V1/
    Resources/Api/V1/
  Infrastructure/
    Persistence/Eloquent/
  Models/
  Policies/
  Providers/
  Support/
    Logging/
    Tenancy/
```

Laravel-standard locations such as `config`, `database`, `routes`, and `tests` remain conventional.

Models MAY remain in `App\Models` to preserve framework ergonomics. Business enums, exceptions, and events live under their domain namespace.

### 3.2 Flutter

```text
lib/
  app/
  core/
    auth/
    errors/
    network/
    routing/
    storage/
    theme/
  features/
    <feature>/
      data/
      domain/
      presentation/
test/
  core/
  features/
integration_test/
```

Do not create an empty `data/domain/presentation` ceremony. A layer exists only when it owns real behavior.

## 4. Naming

### 4.1 Universal rules

- Names MUST reveal intent and use domain vocabulary from the specifications.
- Avoid abbreviations except established terms such as `DTO`, `API`, `URL`, `ID`, and `CRM`.
- Boolean names start with `is`, `has`, `can`, `should`, or a clear verb.
- Collections use plural nouns.
- Methods use verbs.
- Do not encode types in names.
- Avoid `data`, `info`, `item`, `manager`, `helper`, `util`, or `process` unless the name is genuinely precise.

### 4.2 PHP and Laravel

| Item | Convention | Example |
|---|---|---|
| Namespace/class/enum | `PascalCase` | `ChangePropertyStatusService` |
| Method/property/variable | `camelCase` | `assignedAgentId` |
| Constant/enum case | `SCREAMING_SNAKE_CASE` | `STATUS_RESERVED` only for constants |
| Backed enum case | `PascalCase` | `PropertyStatus::Reserved` |
| Config key | `snake_case` | `property_image_max_bytes` |
| Route name | dot notation | `api.v1.properties.index` |
| Permission | `resource.action` | `properties.change_status` |
| Event | past tense | `PropertyStatusChanged` |
| Command/service | imperative/use-case name | `CreatePropertyService` |
| Form Request | action + resource | `StorePropertyRequest` |
| API Resource | resource name | `PropertyResource` |
| Policy method | Laravel ability verb | `view`, `update`, `restore` |

Interface names describe their role and use a `Contract` suffix:

```php
interface PropertyRepositoryContract
```

Implementations identify technology:

```php
final class EloquentPropertyRepository implements PropertyRepositoryContract
```

### 4.3 Database

- Tables: plural `snake_case`.
- Columns: singular `snake_case`.
- Foreign keys: `<singular>_id`.
- Pivot tables: singular names in alphabetical/domain-readable order, as specified in `DATABASE_DESIGN.md`.
- Indexes/constraints: explicit descriptive names, within MySQL's identifier limit.
- Timestamps: `<event>_at`.
- Never use reserved or ambiguous names such as `order`, `group`, or `type` without domain qualification.

### 4.4 Dart and Flutter

Follow `dart format` and effective Dart naming:

| Item | Convention | Example |
|---|---|---|
| Class/enum/extension/typedef | `UpperCamelCase` | `PropertyRepository` |
| Variable/method/parameter | `lowerCamelCase` | `loadNextPage` |
| File/directory/library | `lowercase_with_underscores` | `property_detail_page.dart` |
| Private member | leading underscore | `_tokenStorage` |
| Provider | descriptive suffix | `propertyListControllerProvider` |
| Async command | verb phrase | `submitProperty` |

Avoid prefixes such as `My`, `Base`, `Common`, or `Custom` unless they communicate a real domain role.

## 5. PHP Language Rules

- Every PHP file MUST begin with `declare(strict_types=1);`.
- Use PHP 8.3 typed properties, parameter types, and return types.
- Use `final` by default for concrete Services, DTOs, repositories, listeners, and controllers.
- Use `readonly` DTOs/value objects where mutation is not required.
- Prefer constructor property promotion.
- Prefer backed enums over string constants for finite domain states.
- Use `match` when exhaustive enum handling improves correctness.
- Never suppress errors with `@`.
- Never use `eval`, variable variables, or dynamic class construction from request input.
- Avoid global functions for application behavior.
- Avoid static state; framework facades are acceptable only at infrastructure boundaries and tests.
- Named arguments to framework/vendor methods SHOULD be avoided because vendor parameter names may change.
- Date/time values use immutable Carbon instances at domain boundaries.
- Monetary arithmetic uses fixed-point decimal strings or an approved money value object, never `float`.

Formatting MUST pass Laravel Pint with the repository configuration.

## 6. Laravel Layer Standards

### 6.1 Routes

Routes MUST:

- Be named.
- Use `/api/v1` version grouping for mobile API.
- Declare authentication, token ability, active-user, tenant, and throttle middleware at the narrowest reusable group.
- Use scoped implicit binding or explicit tenant-safe binding.
- Point to controller methods; closures are allowed only for a trivial health endpoint.

Routes MUST NOT:

- Contain database queries or business rules.
- Accept `agency_id` as a tenant-selection mechanism.
- Expose `withTrashed` binding outside manager-only restore/trash routes.

### 6.2 Middleware

Middleware is for request-wide concerns:

- Request/correlation ID
- Authentication
- Active user/agency
- Tenant context
- Rate limiting
- Content negotiation

Record authorization belongs to Policies. Middleware MUST NOT become a second permission matrix.

### 6.3 Controllers

A controller method SHOULD:

1. Receive a Form Request and scoped model when applicable.
2. Call `$this->authorize(...)`.
3. Build a DTO from validated input.
4. Call exactly one application Service operation.
5. Return an API Resource or standard empty response.

Controllers MUST NOT:

- Call Eloquent query methods.
- Start transactions.
- Generate Property codes.
- Mutate multiple models.
- Catch `Throwable` to convert every error to `500`.
- Shape ad hoc JSON.
- Contain role-name conditionals that belong in Policies.

Target size: at most 15 executable lines per action. Exceeding this is a review signal, not a license to hide code in a generic helper.

### 6.4 Form Requests and validation

Every mutation endpoint uses a dedicated Form Request.

Validation MUST:

- Use allowlists for enums, sort fields, filter fields, includes, and file types.
- Use tenant-aware `Rule::exists` / `Rule::unique` conditions.
- Reject or ignore prohibited ownership fields explicitly; API mutation endpoints SHOULD reject them.
- Normalize only representation concerns in `prepareForValidation`, such as trimmed email or localized digits.
- Put multi-record/business invariants in Services, not custom validator callbacks that query broadly.
- Return the standard API validation envelope.

The `authorize()` method MAY perform coarse permission checks. Record Policies remain mandatory.

Never use a global uniqueness rule for tenant-owned business identifiers when the schema defines tenant-aware uniqueness.

### 6.5 DTOs

DTOs:

- Are immutable.
- Contain validated application input, not `Request` objects.
- Use domain enums/value objects where useful.
- Contain no Eloquent Models except when an explicitly documented command requires an already-authorized aggregate root.
- Do not query, authorize, log, or persist.
- Provide a named constructor such as `fromValidated()` when mapping is non-trivial.

DTO field names use PHP `camelCase`; API mapping remains `snake_case`.

### 6.6 Application Services

Each public Service method represents one use case:

- `CreatePropertyService::execute(...)`
- `UpdateCustomerService::execute(...)`
- `ChangePropertyStatusService::execute(...)`

Services MUST:

- Enforce business invariants.
- Establish/reuse TenantContext.
- Depend on repository contracts.
- Own the transaction boundary for multi-write operations.
- Lock rows when concurrency affects correctness.
- Emit domain events only after the relevant state is valid.
- Return a domain model/result, not an HTTP response.

Services MUST NOT:

- Read directly from the HTTP request.
- Return Filament notifications or API Resources.
- Use `withoutGlobalScope()` except in a dedicated platform Service backed by an explicit repository method.
- Catch and discard database exceptions.

Nested transaction behavior must be deliberate. A Service called by another transactional Service SHOULD join the existing transaction and MUST NOT commit independently.

### 6.7 Repository contracts

Repositories are required at persistence boundaries but MUST remain use-case-oriented.

Good:

```php
interface PropertyRepositoryContract
{
    public function createForAgency(AgencyId $agencyId, CreatePropertyData $data): Property;
    public function lockAgencySettings(AgencyId $agencyId): AgencySettings;
    public function findVisibleToAgent(PropertyId $propertyId, UserId $agentId): ?Property;
}
```

Forbidden:

```php
interface BaseRepository
{
    public function all();
    public function find($id);
    public function create(array $data);
    public function update($id, array $data);
}
```

Rules:

- Contracts belong to Application.
- Eloquent implementations belong to Infrastructure.
- Methods return typed models, collections, paginators, or explicit result objects.
- Query filter objects are preferred over unstructured arrays for complex lists.
- Repositories never decide authorization.
- Tenant scope is applied even when the caller is expected to be scoped.

### 6.8 Eloquent Models

Models MAY contain:

- Relationships
- Casts
- Attribute objects for representation
- Global `AgencyScope`
- Small query scopes
- Simple state predicates

Models MUST:

- Use explicit `$fillable` allowlists or guarded construction controlled by repositories.
- Cast enum, boolean, decimal, date, JSON, and encrypted values explicitly.
- Declare tenant relationships and `agency_id` handling through a reusable tenant-owned concern.
- Prevent changing `agency_id` after creation.

Models MUST NOT:

- Access Request/Auth directly.
- Send notifications or call external services.
- Perform cross-aggregate workflows in `booted()`.
- Generate Property codes in an Observer.
- Hide N+1 queries behind accessors.

### 6.9 Policies and permissions

Policies are mandatory for every tenant-owned Model.

Policy decision order:

1. Active authenticated user
2. Required permission through Laravel Gate/Spatie
3. Same tenant
4. Record visibility/assignment
5. State-specific restriction

Super Admin is not a universal `Gate::before(fn () => true)` bypass. A `Gate::before` MAY grant only explicit platform abilities. Tenant operational access is denied unless a separately specified controlled feature exists.

Use `$user->can('properties.update')`, not direct role-name checks in controllers. Role checks are acceptable in policy helpers when behavior is truly role-specific.

The User model uses `web` as the single Spatie permission guard. Sanctum authenticates the same User and Laravel Gate evaluates the same permission catalog; the project MUST NOT create a duplicate `sanctum` role/permission set.

### 6.10 API Resources

API Resources MUST:

- Define the public contract explicitly.
- Use `whenLoaded` for optional relationships.
- Never expose `agency_id` to Agent clients unless explicitly needed.
- Never expose password, token, internal storage path, encrypted identity data, or hidden metadata.
- Format money as decimal strings and times as UTC ISO 8601.
- Keep list resources smaller than detail resources.

Do not return `Model::toArray()` from an API endpoint.

### 6.11 Observers and events

Observers are limited to simple lifecycle behavior that is:

- Local
- Fast
- Deterministic
- Non-authoritative for a critical invariant

Domain events are past-tense facts. In MVP they are handled synchronously. Event listeners MUST be idempotent where re-dispatch is possible.

Property History writes SHOULD be invoked within the same Service transaction. An event listener is acceptable only if its transaction participation is verified.

### 6.12 Filament

Filament Resources and Pages:

- Use the same Services and Policies as API controllers.
- Keep form/table configuration declarative.
- Use scoped queries and Policy-driven actions.
- Never depend only on hidden buttons for authorization.
- Use dedicated Panel Providers for `control`, `agency`, and `agent`.
- Avoid calling `Model::query()` from action closures for mutations.
- Translate labels through language files.
- Include loading, empty, validation, and permission states.

Large Resource classes SHOULD be split into schema/table/action classes within the same panel namespace.

## 7. Tenant-Safety Standards

### 7.1 Tenant-owned model contract

Every tenant-owned model MUST:

- Have a non-null `agency_id`.
- Use `BelongsToAgency` and `AgencyScope`.
- Obtain `agency_id` from trusted server context.
- Have same-tenant relationship validation.
- Have IDOR feature tests.

Console commands, seeders, and scheduled tasks that query tenant-owned Models MUST establish an explicit TenantContext per Agency or call a narrowly approved platform repository method. CLI execution is not a reason to disable tenant safety globally.

### 7.2 Unscoped queries

Calls to any of the following are prohibited in ordinary code:

```text
withoutGlobalScope
withoutGlobalScopes
newQueryWithoutScope
DB::table on a tenant-owned table
raw SQL against tenant-owned tables
```

An approved platform repository method MAY use an unscoped query when:

- Its name contains the global intent, such as `aggregateGloballyByAgency`.
- It requires Super Admin permission.
- It selects only necessary columns.
- It is covered by a test proving no record content leaks.
- A code comment references the architecture decision.

### 7.3 Related identifiers

Never trust a parent or related ID because the primary record is scoped. Every related record must be resolved inside the same agency. Prefer composite database foreign keys plus Service validation.

### 7.4 Cache and file keys

Every tenant cache key includes `agency:{agencyId}`. Every private file path begins with an opaque tenant partition. Authorization is rechecked before download; path knowledge is not access.

## 8. Transactions and Concurrency

Use a transaction when:

- Creating a Property and consuming an agency sequence.
- Linking Owners to a Property.
- Changing Property status and writing history.
- Setting an exclusive cover image.
- Setting a default Saved Filter.
- Performing soft delete/restore with dependent checks.
- Mutating multiple records that represent one use case.

Rules:

- Keep transactions short; no network/file streaming inside a database transaction.
- Lock only rows required for the invariant and in a consistent order.
- Use `lockForUpdate()` for sequence and state transitions.
- Database unique constraints are the final concurrency guard.
- Retry deadlocks only for documented idempotent operations, with a maximum of three attempts and jitter.
- Never "check then insert" without a matching unique constraint.
- Never use table locks.

For file upload:

1. Validate and store to a temporary/private path.
2. In a transaction, create image metadata and enforce count/order/cover rules.
3. Move/finalize the file.
4. If finalization fails, compensate by deleting/soft-deleting metadata and log the request ID.

## 9. Exceptions and Error Handling

### 9.1 Exception taxonomy

- `DomainException` subclasses: a user-understandable rule conflict.
- `AuthorizationException`: permission failure.
- `ValidationException`: field validation.
- `ModelNotFoundException`: missing/inaccessible scoped resource.
- Infrastructure exception: database, storage, or provider failure.

Domain exceptions carry a stable machine code and safe message. They do not carry HTTP responses.

### 9.2 API mapping

One global exception renderer maps exceptions to the envelope in `PROJECT_SPEC_MVP.md`.

Rules:

- Do not use `try/catch` solely to log and rethrow at every layer.
- Catch only when adding actionable context, translating an infrastructure error, compensating, or choosing a documented recovery.
- Unexpected exceptions use `INTERNAL_ERROR`; details appear only in protected logs.
- Cross-tenant not-found and ordinary not-found are indistinguishable.

### 9.3 Flutter errors

Map transport responses to sealed domain failures:

```text
NetworkFailure
UnauthenticatedFailure
ForbiddenFailure
AgencyInactiveFailure
PasswordChangeRequiredFailure
NotFoundFailure
ValidationFailure
ConflictFailure
RateLimitedFailure
ServerFailure
```

Widgets render domain failures and MUST NOT inspect Dio response maps directly.

## 10. API Standards

- Endpoints use plural nouns and standard HTTP verbs.
- Actions that are domain transitions use a subordinate endpoint, such as `/properties/{id}/status`.
- `PATCH` performs partial update; `PUT` is not used unless complete replacement exists.
- Collection filters use `filter[field]`.
- Sort uses `sort=field` or `sort=-field`.
- Pagination uses `page` and `per_page`.
- Request and response keys are `snake_case`.
- Error codes are `SCREAMING_SNAKE_CASE`.
- New response fields are backward-compatible; renames/removals require a new API version.
- All mutation endpoints reject unsupported fields.
- `204` responses have no body.
- API examples in tests/docs use real envelope shapes.

OpenAPI MAY be generated or maintained, but it cannot replace executable feature tests.

## 11. Database and Migration Standards

### 11.1 Migrations

Each migration:

- Has one coherent purpose.
- Uses explicit column types, lengths, nullability, defaults, indexes, and foreign-key behavior.
- Names indexes and constraints when the generated name could be unclear or exceed limits.
- Is tested on a fresh database.
- Is forward-safe for existing rows.
- Avoids application Models; use Schema Builder or explicit query builder data migration.

Rules:

- Never edit a migration already applied outside local development; create a new migration.
- Never use `float`/`double` for money.
- Store timestamps in UTC.
- Use `utf8mb4`.
- Use `decimal` precision/scale from `DATABASE_DESIGN.md`.
- Use `VARCHAR` plus application enums and database `CHECK` constraints for domain states.
- Tenant-aware unique keys begin with `agency_id` unless the domain explicitly defines global uniqueness.
- Add an index for every frequent foreign-key/filter path; do not add speculative indexes.
- Destructive column/table removal requires a staged migration and verified backup/rollback plan.

### 11.2 Foreign keys

- Foreign keys are required unless a documented reason prohibits them.
- Same-agency relationships use composite keys where specified.
- `CASCADE` is limited to pure dependent metadata.
- Domain records with history generally use `RESTRICT` plus soft deletion.
- Do not rely on application validation alone for referential integrity.

### 11.3 Query standards

- Select only required columns for reports/aggregates.
- Eager-load required relations.
- Use `whereHas` or joins deliberately and inspect generated SQL for complex queries.
- Paginate every user-visible collection.
- Avoid `%term%` scans on unbounded tables; apply tenant and other selective predicates first.
- Raw SQL requires a code-review justification, parameter binding, tenant predicate, and query-plan evidence.
- Any query added to dashboard/report/search receives an `EXPLAIN` review with representative data.

## 12. Seeders and Factories

Seeders:

- Roles/permissions seeder is deterministic and idempotent.
- Reference enum values are code-owned; do not seed mutable duplicates.
- Production seeding MUST NOT create known default passwords.
- Local/demo seeding clearly marks non-production users.
- Seeder order follows `DATABASE_DESIGN.md`.

Factories:

- Produce valid domain records by default.
- Offer named states for invalid-edge tests, roles, statuses, deleted records, and multiple tenants.
- Never choose a random agency independently for related records.
- Use deterministic values when tests assert ordering or uniqueness.

## 13. Security Standards

### 13.1 Input and output

- Validate length, format, and allowlisted values.
- Normalize emails and phones consistently.
- Escape HTML output.
- Sanitize rich text; MVP SHOULD use plain text for notes/descriptions unless approved.
- Protect mass assignment.
- Verify uploaded content by decoding and server-derived MIME.
- Use constant-time framework password/token checks.

### 13.2 Secrets and sensitive data

- Secrets live in environment/secret management only.
- Never commit `.env`, private keys, service credentials, or production dumps.
- Tokens are displayed once and stored hashed by Sanctum.
- Optional Owner identity numbers use encrypted casts and are never indexed/logged.
- Logs redact Authorization, Cookie, password, token, identity, and binary fields.
- Test fixtures MUST NOT contain real customer data.

### 13.3 Dependency security

CI runs:

- `composer audit`
- Dart/Flutter dependency audit mechanism adopted by the project
- Static analysis
- Secret scanning

Dependencies with a critical/high vulnerability block release unless a time-bound written exception identifies exposure and mitigation.

## 14. Logging and Observability

Use structured contextual logs. Required fields where available:

```text
request_id
environment
route
http_method
status_code
duration_ms
user_id
agency_id
event_code
resource_type
resource_id
```

Rules:

- Use stable event codes such as `PROPERTY_STATUS_CHANGED`.
- Log once at the layer that can act on the failure.
- Expected validation/authorization failures are not error-level events.
- Never log entire Eloquent models or request bodies.
- Use `warning` for recoverable abnormal conditions, `error` for failed operations, and `critical` for application-wide loss of service/integrity.
- Flutter production logging excludes response bodies and personal data.

## 15. Laravel Testing Standards

### 15.1 Test organization

```text
tests/
  Unit/
    Domain/
    Application/
  Feature/
    Api/V1/
    Filament/
    Tenancy/
  Integration/
    Database/
    Storage/
```

### 15.2 Test naming

Test names describe behavior:

```php
public function test_agent_cannot_update_property_assigned_to_another_agent(): void
```

Use Arrange–Act–Assert with visible separation. One test may contain multiple assertions for one behavior.

### 15.3 Required coverage

Every Service:

- Happy path
- Validation/domain conflict
- Permission/assignment boundary
- Transaction rollback when a dependent write fails

Every tenant endpoint:

- Unauthenticated
- Wrong role/permission
- Same tenant
- Cross tenant
- Inactive user/agency
- Missing record
- Invalid related tenant ID

Every list:

- Filter allowlist
- Sort allowlist
- Pagination boundaries
- Stable ordering
- Tenant scoping

Coverage percentage is a diagnostic, not proof. Nevertheless:

- Changed backend application/domain lines SHOULD maintain at least 85% line coverage.
- Security/tenancy Services and Policies MUST have direct behavioral tests for every branch.

### 15.4 Test isolation

- Tests use a dedicated database.
- Each test creates its own tenants/users unless a clearly immutable reference fixture is shared.
- Time, filesystem, and randomness are controlled.
- Parallel tests use isolated databases or schemas.
- Do not depend on test execution order.
- Do not call real external services.

## 16. Flutter Standards

### 16.1 Architecture

- Widgets render state and send user intents.
- Controllers/Notifiers coordinate feature state.
- Repositories define data access.
- API clients perform transport and serialization.
- Domain models do not import Flutter UI libraries.
- Dio interceptors handle Authorization, request ID, timeout, and standard error mapping.
- Provider dependencies are injected; avoid global mutable singletons.

### 16.2 State

Every asynchronous screen state represents:

```text
initial/loading
data
empty
refreshing
validation failure
recoverable failure
blocking auth/agency failure
```

Rules:

- Do not call APIs from `build()`.
- Cancel or ignore stale requests after disposal/search changes.
- Debounce search input.
- Keep pagination state per active filter.
- Invalidate related providers after successful mutation.
- Do not optimistically mutate critical status/ownership data unless rollback behavior is implemented and tested.

### 16.3 Models and serialization

- Use typed immutable models.
- Generated JSON serialization is preferred for non-trivial models.
- API `snake_case` maps explicitly to Dart `lowerCamelCase`.
- Money remains a decimal string or approved decimal value object.
- Dates parse as UTC and convert only for presentation.
- Unknown enum values map to an explicit unsupported value or a controlled parse failure; never silently choose a valid state.

### 16.4 Widgets and UX

- Prefer small composable widgets.
- Extract a widget when it has state, reusable behavior, or materially improves readability.
- Use project theme tokens; avoid one-off colors and text styles.
- User-visible text comes from localization resources.
- Support RTL, text scaling, semantic labels, keyboard navigation where applicable, and minimum tap targets.
- Never show a blank screen for an error.
- Destructive actions require a clear confirmation and describe impact.
- Server validation errors attach to the correct fields.

### 16.5 Flutter tests

- Unit-test repositories, serialization, error mapping, and controllers.
- Widget-test loading, empty, data, validation, permission, and error states.
- Test auth expiry and agency suspension navigation.
- Integration-test login and primary Property/Customer paths.
- Golden tests MAY protect stable high-value layouts but MUST not replace behavior tests.

Code MUST pass:

```text
dart format
flutter analyze
flutter test
```

## 17. Comments and Documentation

Comments explain **why**, risk, invariant, or non-obvious framework behavior. They do not narrate the code.

Required documentation:

- Public contract PHPDoc where native types cannot express the contract.
- Invariant comment beside any explicit global-scope bypass.
- Migration comment for non-obvious data conversion.
- README/setup changes when commands or environment variables change.
- API specification/examples when contract changes.
- Architecture decision record for a cross-cutting architectural change.

Remove stale comments in the same change.

## 18. Performance Standards

- Solve measured bottlenecks.
- Establish a representative data set before query tuning.
- Review query count and `EXPLAIN` for list, dashboard, report, and search changes.
- Avoid N+1 queries.
- Avoid loading a collection when only `exists`, `count`, or an aggregate is needed.
- Stream/chunk only for bounded administrative operations; tenant and authorization rules still apply.
- Do not cache mutable tenant data without a complete key, TTL, and invalidation strategy.
- File cache is the MVP default; code must not assume Redis-only primitives.
- Do not add an index without identifying the query it supports.
- Do not trade correctness or tenant isolation for performance.

## 19. Refactoring Standards

Refactor when:

- A method has multiple responsibilities.
- The same business rule appears in more than one entry point.
- A Policy or Service has repeated state/role branches that form a coherent collaborator.
- A query is repeated and has a stable use-case meaning.
- Tests are hard to write because responsibilities are coupled.

Rules:

- Preserve behavior with tests before risky refactoring.
- Keep refactoring and feature behavior in separate commits when practical.
- Do not create generic base Service/Repository/Controller classes to remove superficial repetition.
- Delete unused code; do not comment it out.
- Avoid "future-proof" abstractions for Enterprise capabilities.

## 20. Dependency Policy

### 20.1 Allowed dependency criteria

A new dependency must:

- Solve a current approved requirement.
- Be maintained and compatible with pinned framework versions.
- Have an acceptable license.
- Have a reviewed security history.
- Reduce more risk/complexity than it adds.
- Be covered by an adapter when it affects a domain boundary or may require replacement.

### 20.2 Approved baseline categories

- Laravel first-party packages
- Filament and required Livewire/Tailwind packages
- Spatie Laravel Permission
- Flutter Riverpod, Dio, go_router, secure storage, and approved serialization tooling
- Development-only formatter, static-analysis, and test packages

Exact packages and versions belong in lock files and a dependency decision record.

### 20.3 Prohibited dependencies

Without an approved scope change:

- Redis/queue/Horizon integration
- Elasticsearch clients
- S3-provider-specific domain packages
- Billing/payment SDKs
- AI/LLM/vector packages
- Push/SMS/campaign providers
- Public API/OAuth server packages
- Direct 2FA/device-management packages or feature wiring (transitive packages required by the approved Filament baseline do not authorize the feature)
- Generic admin generators that bypass Filament Policies/Services
- Abandoned packages or packages requiring disabled TLS verification

## 21. Git Workflow

### 21.1 Branches

- `main` is protected and releasable.
- Feature branches use:

```text
feature/<ticket>-short-description
fix/<ticket>-short-description
chore/<ticket>-short-description
docs/<ticket>-short-description
```

- Branches are short-lived.
- Direct pushes to `main` are prohibited.
- Rebase or update from `main` before final review according to repository policy.

### 21.2 Commits

Use Conventional Commit-style subjects:

```text
feat(properties): add transactional property code generation
fix(tenancy): reject missing tenant context
test(api): cover cross-agency customer access
docs(database): define property owner constraints
```

Rules:

- Imperative subject, lowercase type/scope, no trailing period.
- Subject SHOULD be at most 72 characters.
- Body explains reason and trade-off where needed.
- Each commit builds/tests where practical.
- Do not mix generated formatting noise with unrelated behavior.
- Never include secrets or personal data in history.

### 21.3 Pull requests

A PR MUST include:

- Problem and scope
- User-visible behavior
- Architecture/schema/API changes
- Security and tenancy impact
- Test evidence
- Migration/deployment/rollback notes
- Screenshots for meaningful UI changes
- Explicit list of out-of-scope follow-ups

Keep PRs reviewable. A large cross-cutting change SHOULD be split into safe vertical increments.

## 22. Code Review Checklist

The author self-reviews before requesting review.

Reviewers verify:

### Correctness

- Acceptance criteria are met.
- Edge cases and state transitions are explicit.
- Transactions and concurrency are correct.

### Tenancy and authorization

- Tenant context is server-derived and fail-closed.
- Queries and related IDs are tenant-scoped.
- Policies enforce record access.
- Cross-tenant tests exist.
- No unsafe global-scope bypass exists.

### Data

- Schema matches `DATABASE_DESIGN.md`.
- Nullability, constraints, indexes, and deletion behavior are deliberate.
- Money/time/enums are represented correctly.

### API/UI

- Envelopes and status codes are stable.
- Resources expose only allowed fields.
- Loading/empty/error/permission states exist.
- RTL/accessibility/localization behavior is considered.

### Maintainability

- Responsibilities are separated.
- Naming communicates intent.
- No generic or speculative abstraction was introduced.
- Dependency addition is justified.

### Operations

- Logs are actionable and safe.
- Migration/deployment/rollback impact is documented.
- Tests and static checks pass.

## 23. CI Quality Gates

Backend pipeline:

```text
composer validate
composer audit
Laravel Pint check
PHP static analysis
PHPUnit unit/feature/integration tests
fresh migration + seed
schema/constraint verification
```

Flutter pipeline:

```text
dependency resolution from lock file
dart format --output=none --set-exit-if-changed
flutter analyze
flutter test
Android debug build
```

Documentation pipeline:

```text
Markdown lint
relative-link check
Mermaid syntax check
forbidden MVP term/schema check
```

No quality gate may be disabled solely to merge a change. A temporary exception requires owner, reason, risk, expiry date, and follow-up issue.

## 24. Forbidden Practices

The following are prohibited:

- Business logic in Controllers, Filament action closures, Blade, or Widgets
- Request/Auth access inside Models
- Direct Eloquent access from Controllers
- Generic `BaseRepository`, `BaseService`, or catch-all helpers
- Role or permission columns on `users`
- Client-controlled `agency_id`
- Tenant queries that default to global when context is absent
- Unreviewed `withoutGlobalScope()` usage
- Authorizing by hidden UI controls
- Cross-agency related IDs
- Property code generation in an Observer
- Check-then-insert uniqueness without a unique constraint
- Money stored/calculated as floating point
- Raw SQL string concatenation
- `Model::toArray()` as an API response
- Logging tokens, passwords, cookies, or full personal-data payloads
- Publicly accessible upload directories
- Trusting file extensions or client MIME headers
- Hard-deleting operational records through MVP UI/API
- Silent exception swallowing
- Returning stack traces or SQL to clients
- N+1 queries accepted as "small MVP data"
- Unbounded list/report/export queries
- Real production data in local/test environments
- Disabled tests, committed focused tests, or order-dependent tests
- Commented-out code or unresolved work markers without a tracked issue
- Enterprise-only fields, packages, routes, interfaces, or placeholders in MVP

## 25. Definition of Done for a Code Change

A change is complete when:

1. It satisfies an approved specification/issue.
2. It follows the required layers and naming.
3. Tenant scope, Policy, validation, and sensitive-data handling are correct.
4. Database invariants and concurrency controls are enforced.
5. Automated tests cover success, failure, role, and cross-tenant behavior.
6. Static analysis, formatting, audits, and builds pass.
7. API/documentation/migrations are updated.
8. Logging and operational behavior are safe.
9. UI states and accessibility are handled where relevant.
10. Code review is approved with no unresolved blocking comment.
