# CONVENTIONS.md

Coding conventions for tt-apps. Follow these in all new code. When in doubt, the rule here overrides personal preference.

---

## 1. PHP Style (PSR-12)

- 4-space indentation. No tabs.
- Opening brace `{` on the same line for classes, methods, control structures.
- No closing `?>` tag in PHP-only files.
- One blank line between method definitions.
- `declare(strict_types=1);` at the top of every PHP file.
- Type declarations on all method parameters and return types. Use `mixed` only as a last resort.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

class UserService
{
    public function findByEmail(string $email): ?array
    {
        // ...
    }
}
```

---

## 2. Naming Conventions

| Artifact | Convention | Example |
|----------|-----------|---------|
| Classes | `PascalCase` | `UserService`, `CommunicationLog` |
| Interfaces | `PascalCase` + `Interface` suffix | `MailerInterface` |
| Methods | `camelCase` | `findByEmail()`, `processQueue()` |
| Variables | `camelCase` | `$recipientList`, `$batchSize` |
| Constants (class) | `UPPER_SNAKE_CASE` | `const MAX_RETRY = 3` |
| Constants (global) | `UPPER_SNAKE_CASE` | avoid; use config instead |
| Database tables | `snake_case`, plural | `users`, `recipient_lists`, `communication_logs` |
| Database columns | `snake_case` | `created_at`, `body_html`, `is_active` |
| Routes (URL segments) | `kebab-case` | `/admin/recipient-lists` |
| Route names | `dot.notation` | `comms.recipients.index` |
| View files | `snake_case.php` | `compose_form.php` |
| Migration files | `YYYY-MM-DD-HHMMSS_verb_description.php` | `2024-01-15-120000_create_users.php` |
| Config keys | `camelCase` | `batchSize`, `smtpHost` |
| Env variables | `UPPER_SNAKE_CASE` | `COMMS_BATCH_SIZE`, `ADMIN_EMAIL` |

---

## 2.1 Module Naming (load-bearing rule)

All identifiers that name a module **must be in English** and must be homologous across the platform. Spanish (or any other natural-language) names belong only in user-facing copy (display name, page titles, body text), never in code, URLs, or DB schema.

| Identifier | Convention | Example (Mailboxes module) |
|------------|-----------|-----------------------------|
| Module folder | `PascalCase`, English, plural noun | `app/Modules/Mailboxes/` |
| PHP namespace | `App\Modules\{Folder}` | `App\Modules\Mailboxes` |
| Module key (in `modules` table) | `snake_case`, English | `mailboxes` |
| `route_base` (in `modules` table) | matches module key, no leading slash | `mailboxes` |
| URL prefix (web) | `kebab-case`, English, matches `route_base` | `/mailboxes`, `/mailboxes/settings` |
| URL prefix (API) | `/api/v1/{key}` | `/api/v1/mailboxes/...` |
| Route filter argument | `module_access:{key}` | `module_access:mailboxes` |
| Route names | `{key}.{resource}.{action}` | `mailboxes.index`, `mailboxes.settings` |
| Web controller class | `{Folder}` (singular form acceptable for hub controller) | `Mailboxes` |
| API controller class | `{Folder}ApiController` | `MailboxesApiController` |
| Service class | `{Folder}Service` (or domain-specific) | `MailboxesService`, `MailerService` |
| Model classes | `{Entity}Model` | `MailboxesSettingsModel` |
| Service container key (`Services.php`) | `camelCase` matching service | `mailboxesService` |
| DB tables owned by module | `snake_case`, English, prefer prefix `{key}_` for module-private state | `mailboxes_settings` |
| Seeder | `{Folder}ModuleSeeder` (for module registration) | `MailboxesModuleSeeder` |
| View folder | `snake_case`, English, matches key | `Views/mailboxes/` |
| Sidebar `$moduleIcons` / `$moduleSubnav` key | matches module key | `'mailboxes' => [...]` |

### Why

- **Homologation.** All modules share the same shape so anyone (humans or tooling) can navigate the codebase without translation lookups.
- **Greppability.** A single `grep "mailboxes"` returns every relevant file: routes, filters, namespaces, view folder, DB table.
- **Tooling.** CI4 autoloader, route names, and the access service all key off the same string. A mismatch (e.g. folder in Spanish, key in English) silently breaks `route_to()` resolution or filter routing.

### Display name vs identifier

The `name` column in the `modules` table (and the sidebar label) is the only place where Spanish (or localized) text belongs. Example for the Mailboxes module:

```php
$this->db->table('modules')->insert([
    'key'        => 'mailboxes',          // identifier: English
    'name'       => 'Buzones',            // display: Spanish, shown in sidebar
    'route_base' => 'mailboxes',          // identifier: matches key
    // ...
]);
```

### Picking a name

Translate the domain noun directly to English plural. Prefer the literal translation over abbreviations:

| Concept | Preferred | Avoid |
|---------|-----------|-------|
| Buzones de correo | `Mailboxes` / `mailboxes` | `Buzones`, `Mail`, `MB` |
| Comunicaciones | `Communications` / `comms` (short URL ok) | `Comunicaciones`, `Comms` (in folder) |
| KPIs operativos | `KPIsOperativos` / `kpis_operativos` | `OperationalKpis` (legacy compat) |
| Próximo módulo | Pensar en inglés primero | Cualquier nombre en español |

Short URL prefixes (`/comms`, `/kpi`) are acceptable when the long form would be noisy, but the **folder name and namespace must always be the full English noun**. The module key in the DB must match the URL prefix exactly.

### What to do if you find a mismatched module

Open a single PR that renames every identifier in lockstep (folder, namespace, key, URLs, filter args, view folder, DB table, sidebar). Half-migrations break route resolution and access control. Use `grep -rni "<old-name>" app/ docs/` to confirm zero leftovers before merging.

---

## 3. Controllers

- One controller per resource (e.g., `Users`, `Roles`, `Communications`).
- Web controllers in `Controllers/`, API controllers in `Controllers/Api/`.
- Method names follow CRUD semantics:

| HTTP + Route | Method name |
|---|---|
| `GET /resource` | `index()` |
| `GET /resource/new` | `new()` |
| `POST /resource` | `store()` |
| `GET /resource/{id}` | `show($id)` |
| `GET /resource/{id}/edit` | `edit($id)` |
| `POST /resource/{id}` (or PUT) | `update($id)` |
| `DELETE /resource/{id}` | `destroy($id)` |

- API controllers extend `BaseApiController` and use CI4's `ResourceController` convention (`index`, `show`, `create`, `update`, `delete`).
- Never write SQL or business logic inside a controller.

---

## 4. Services

- One service per domain entity or use case: `UserService`, `MailerService`, `ImportService`.
- Services are pure PHP classes — no CI4 framework dependencies unless truly necessary.
- Methods return a `ServiceResult` value object or throw specific exceptions; never return `true/false` booleans for meaningful results.

```php
// Prefer this
class ServiceResult
{
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data = null,
        public readonly array $errors = []
    ) {}
}

// Over this
return false;
```

- Services must not directly access `$_POST`, `$_GET`, or `$_SESSION`. Controllers pass clean data in.

---

## 5. Models

- One model per database table.
- Define `$allowedFields` explicitly — never use `$allowedFields = ['*']`.
- `$returnType = 'array'` by default (not objects) for consistency with view data.
- Custom query methods go in the model. Complex multi-join queries are acceptable in the model if they're reused in 2+ places; single-use queries stay in the Service.
- Use `$useTimestamps = true`. Never manage `created_at`/`updated_at` manually.

---

## 6. Routes

All routes declared in the module's `Routes.php`. Structure:

```php
// Web routes
$routes->group('comms', ['namespace' => 'App\Modules\Communications\Controllers', 'filter' => 'auth|module_access:communications'], function ($routes) {
    $routes->get('/',             'Communications::index',   ['as' => 'comms.index']);
    $routes->get('compose',       'Communications::compose', ['as' => 'comms.compose']);
    $routes->post('store',        'Communications::store',   ['as' => 'comms.store']);
    $routes->get('(:num)',        'Communications::show/$1', ['as' => 'comms.show']);
});

// API routes (mirroring web actions)
$routes->group('api/v1/comms', ['namespace' => 'App\Modules\Communications\Controllers\Api', 'filter' => 'api_auth|module_access:communications'], function ($routes) {
    $routes->resource('communications', ['controller' => 'CommunicationsApiController']);
    $routes->resource('recipients',     ['controller' => 'RecipientsApiController']);
    $routes->resource('lists',          ['controller' => 'ListsApiController']);
});
```

Rules:
- Always name routes with `['as' => '...']`.
- Always apply `auth` (web) or `api_auth` (API) filters to protected groups.
- Apply `module_access:key` filter per module group.
- No inline anonymous function controllers.

---

## 7. API Conventions

### Versioning
All API routes are prefixed `/api/v1/`. A new breaking version becomes `/api/v2/`.

### Authentication
Bearer token in header: `Authorization: Bearer {token}`.

### Resource URLs
Follow REST resource conventions:
```
GET    /api/v1/users           → list
POST   /api/v1/users           → create
GET    /api/v1/users/{id}      → show
PUT    /api/v1/users/{id}      → full update
PATCH  /api/v1/users/{id}      → partial update
DELETE /api/v1/users/{id}      → delete
```

Non-CRUD actions use a verb suffix on the resource:
```
POST   /api/v1/communications/{id}/send
POST   /api/v1/communications/{id}/preview
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
```

### Request / Response

- Accept and return `application/json` only on API routes.
- All responses use the standard envelope (see `ARCHITECTURE.md §7`).
- Validation errors return HTTP `422` with an `errors` object keyed by field name.
- Empty successful delete returns HTTP `204` with no body.

### Postman Collection

File: `/docs/tt-apps.postman_collection.json`

- Every new API endpoint must be added to the collection immediately.
- Use Postman environment variables: `{{base_url}}`, `{{api_token}}`.
- Group requests by module (folder per module in the collection).
- Add example responses to each request.

---

## 8. Migrations

```php
<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 191, 'unique' => true],
            'password'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('users');
    }

    public function down(): void
    {
        $this->forge->dropTable('users', true);
    }
}
```

Rules:
- Always implement `down()`.
- Never alter a column in the same migration it was created; use a separate migration.
- Never delete a migration file from the repo — add a new reversing migration instead.
- One table per migration file.

---

## 9. Views & Templates

- Views are PHP files, not a templating engine.
- Use `esc()` for all user-supplied data output: `<?= esc($user['name']) ?>`.
- Never use `echo` directly — use short tags `<?= ?>`.
- Extract view data in the controller before passing to the view; no DB queries in views.
- All CSS class names use the design system tokens from `DESIGN.md`.
- JavaScript: vanilla JS or minimal Alpine.js for interactive components. No jQuery.
- Inline `<script>` blocks only for page-specific initialization. Shared logic goes in `public/js/`.

---

## 10. Error Handling

- Validate all external input (HTTP request data, CSV uploads, API payloads) at the Service boundary.
- Use CI4's `Validation` class for rules; define rule sets in the Service or a dedicated `ValidationRules` config class.
- Throw typed exceptions (`\RuntimeException`, `\InvalidArgumentException`, or custom domain exceptions) for unexpected states.
- Never silently swallow exceptions with empty `catch` blocks.
- Log errors via CI4's `log_message('error', '...')` — never to stdout/echo.

---

## 11. Security

- Always use CI4's query builder or parameterized queries — never string-interpolated SQL.
- Hash passwords with `password_hash($pass, PASSWORD_DEFAULT)`.
- Validate and sanitize file uploads: check MIME type server-side (not just extension), enforce max size, store outside `public/`.
- CSRF protection enabled by default on all state-changing web forms (`csrf_token()` in forms).
- API routes are exempt from CSRF but protected by Bearer token.
- Rate-limit login attempts (CI4 Throttler or custom filter).
- Never log passwords, tokens, or PII.

---

## 12. Git & Commits

Commit message format (conventional commits):
```
type(scope): short description

[optional body]
```

Types: `feat`, `fix`, `refactor`, `chore`, `docs`, `test`, `migration`.
Scope: module name in lowercase (`core`, `comms`, `api`).

Examples:
```
feat(comms): add CSV bulk import for recipients
fix(core): redirect to login on session expiry
migration(comms): add communication_logs table
docs(api): update postman collection with send endpoint
```

- One logical change per commit.
- Never commit `.env` or files with secrets.
- Migration files are immutable once merged to main.
