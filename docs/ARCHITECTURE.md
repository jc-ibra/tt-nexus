# ARCHITECTURE.md

Architecture reference for tt-apps (CodeIgniter 4). All design decisions in this project must align with these patterns.

---

## 1. Request Lifecycle

```
HTTP Request
  → index.php (entry point)
  → CodeIgniter bootstrap
  → Router → matches URI to route group + controller
  → Filters (before): AuthFilter → ModuleAccessFilter
  → Controller::method()
    → validates input
    → calls Service(s)
      → Service calls Model(s)
      → Service applies business logic
      → Service returns result
  → Controller builds response (View or JSON)
  → Filters (after)
  → HTTP Response
```

CLI requests (`php spark`) skip HTTP filters and go directly to the Command class.

---

## 2. Module System

Each module is a self-contained unit under `app/Modules/`. A module owns:

```
app/Modules/ModuleName/
  Config/          ← module-level config (optional)
  Controllers/     ← HTTP controllers + API controllers
  Database/
    Migrations/    ← timestamped migration files
    Seeders/       ← initial/reference data
  Models/          ← CI4 Model subclasses
  Services/        ← business logic classes
  Commands/        ← spark CLI commands (if needed)
  Views/           ← server-rendered PHP templates
  Routes.php       ← route declarations for this module
```

Module namespace registered in `app/Config/Autoload.php`:
```php
'App\Modules\ModuleName' => APPPATH . 'Modules/ModuleName',
```

Routes loaded in `app/Config/Routes.php`:
```php
require APPPATH . 'Modules/ModuleName/Routes.php';
```

---

## 3. Controller Design

### Web Controllers

Thin — only routing logic, input extraction, and view/redirect responses.

```php
class Users extends BaseController
{
    public function __construct(private UserService $service) {}

    public function index()
    {
        $users = $this->service->paginate();
        return view('Core\Views\users\index', compact('users'));
    }

    public function store()
    {
        $data = $this->request->getPost(['name', 'email', 'role_ids']);
        $result = $this->service->create($data);

        if (!$result->success) {
            return redirect()->back()->withInput()->with('errors', $result->errors);
        }
        return redirect()->to('/admin/users')->with('message', 'User created.');
    }
}
```

### API Controllers

Mirror web controllers but live in a separate `Api/` subfolder and always return JSON.

```php
// app/Modules/Core/Controllers/Api/UsersApiController.php
class UsersApiController extends BaseApiController
{
    public function index()
    {
        $users = $this->service->paginate();
        return $this->respond($users);
    }

    public function store()
    {
        $data = $this->request->getJSON(true);
        $result = $this->service->create($data);

        if (!$result->success) {
            return $this->failValidationErrors($result->errors);
        }
        return $this->respondCreated($result->data);
    }
}
```

### BaseApiController

Extends CI4's `ResourceController` (or `BaseController` + `ResponseTrait`):

```php
// app/Modules/Core/Controllers/Api/BaseApiController.php
use CodeIgniter\RESTful\ResourceController;

abstract class BaseApiController extends ResourceController
{
    protected $format = 'json';
}
```

---

## 4. Service Layer

Services hold all business logic. They are plain PHP classes, not CI4 subclasses.

```php
class UserService
{
    public function __construct(
        private UserModel $users,
        private RoleModel $roles
    ) {}

    public function create(array $data): ServiceResult
    {
        // validate, hash password, insert, assign roles, etc.
    }
}
```

Register as CI4 service in `app/Config/Services.php`:
```php
public static function userService(): UserService
{
    return new UserService(new UserModel(), new RoleModel());
}
```

Use from controller: `service('userService')` or constructor injection.

---

## 5. Model Layer

Models extend `CodeIgniter\Model`. They handle DB access only — no business logic.

```php
class UserModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['name', 'email', 'password', 'status'];
    protected $useTimestamps  = true;
    protected $returnType    = 'array';
}
```

For complex queries, use the query builder inside the model:

```php
public function withRoles(int $id): ?array
{
    return $this->db->table('users u')
        ->select('u.*, GROUP_CONCAT(r.name) as roles')
        ->join('user_roles ur', 'ur.user_id = u.id', 'left')
        ->join('roles r', 'r.id = ur.role_id', 'left')
        ->where('u.id', $id)
        ->groupBy('u.id')
        ->get()->getRowArray();
}
```

---

## 6. Filters (Middleware)

Filters live in `app/Filters/`. Register them in `app/Config/Filters.php`.

```php
// AuthFilter — validates active session
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!session()->get('user_id')) {
            return redirect()->to('/login');
        }
    }
}

// ModuleAccessFilter — validates role has access to module
class ModuleAccessFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $moduleKey = $arguments[0] ?? null;
        if (!service('access')->canAccessModule($moduleKey)) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException();
            // or return $this->response->setStatusCode(403);
        }
    }
}
```

Apply per route group in the module's `Routes.php`:
```php
$routes->group('comms', ['filter' => 'auth|module_access:communications'], function ($routes) {
    $routes->get('/', 'Communications::index');
});
```

---

## 7. API Architecture

### Route Structure

Every web action has a mirrored API route:

```
Web:  GET  /admin/users           → Core\Controllers\Users::index
API:  GET  /api/v1/users          → Core\Controllers\Api\UsersApiController::index

Web:  POST /admin/users           → Core\Controllers\Users::store
API:  POST /api/v1/users          → Core\Controllers\Api\UsersApiController::store
```

API routes are grouped under `/api/v1/` with token-based authentication:
```php
$routes->group('api/v1', ['filter' => 'api_auth'], function ($routes) {
    $routes->resource('users',  ['controller' => 'Core\Controllers\Api\UsersApiController']);
    $routes->resource('roles',  ['controller' => 'Core\Controllers\Api\RolesApiController']);
    // ...
});
```

### Authentication

API authentication uses Bearer tokens stored in a `personal_access_tokens` table (similar to Laravel Sanctum's model):

- `POST /api/v1/auth/login` — returns `{ token, expires_at }`
- Token passed as `Authorization: Bearer <token>` header
- `ApiAuthFilter` validates token, loads user into request

### Response Envelope

All API responses use a consistent envelope:

```json
// Success (single resource)
{
  "status": "success",
  "data": { ... }
}

// Success (collection)
{
  "status": "success",
  "data": [ ... ],
  "meta": { "total": 100, "page": 1, "per_page": 20, "last_page": 5 }
}

// Validation error
{
  "status": "error",
  "message": "Validation failed",
  "errors": { "email": "The email field is required." }
}

// General error
{
  "status": "error",
  "message": "Unauthorized"
}
```

HTTP status codes used: `200`, `201`, `204`, `400`, `401`, `403`, `404`, `422`, `500`.

### Postman Collection

The canonical Postman collection is maintained at `/docs/tt-apps.postman_collection.json`. It must be updated whenever an API endpoint is added or changed. Import it into Postman to test all endpoints.

---

## 8. Database Schema Design

- All tables use `snake_case`.
- Primary keys are `id` (unsigned INT, auto-increment).
- Timestamps: `created_at`, `updated_at` (DATETIME, managed by CI4 Model).
- Foreign keys: `{table_singular}_id` (e.g., `user_id`, `role_id`).
- Pivot tables: `{table_a_singular}_{table_b_singular}` alphabetically (e.g., `role_module`, `user_roles`).
- Status fields use VARCHAR ENUMs defined in the migration comment, not MySQL ENUM type.
- Soft deletes: not used — use `status` columns instead.

Migration file naming: `YYYY-MM-DD-HHMMSS_create_{table}.php` or `_add_{column}_to_{table}.php`.

---

## 9. CLI Commands (Spark)

Custom commands live in `app/Modules/[Module]/Commands/`. They extend `BaseCommand`:

```php
class ProcessQueue extends BaseCommand
{
    protected $group   = 'comms';
    protected $name    = 'comms:process-queue';
    protected $usage   = 'comms:process-queue [--batch=50] [--throttle=2]';

    public function run(array $params)
    {
        $batch    = (int) CLI::getOption('batch')    ?: (int) env('COMMS_BATCH_SIZE', 50);
        $throttle = (int) CLI::getOption('throttle') ?: (int) env('COMMS_THROTTLE_SECONDS', 2);
        // ...
    }
}
```

---

## 10. View Architecture

Views are server-rendered PHP templates. Layout structure:

```
app/Modules/Core/Views/
  layouts/
    main.php          ← app shell (topnav + sidebar + main slot)
    auth.php          ← bare layout for login/reset pages
  partials/
    topnav.php
    sidebar.php
    flash.php         ← flash message banners

app/Modules/Communications/Views/
  communications/
    index.php
    compose.php
    show.php
  recipients/
    index.php
```

All views extend the main layout via CI4's `renderSection` / `extend`:
```php
<?= $this->extend('Core\Views\layouts\main') ?>
<?= $this->section('content') ?>
  ...
<?= $this->endSection() ?>
```

CSS global stylesheet (`public/css/app.css`) declares all design tokens from `DESIGN.md §13` as `:root` variables. Component CSS imports or inline styles must only use these tokens.
