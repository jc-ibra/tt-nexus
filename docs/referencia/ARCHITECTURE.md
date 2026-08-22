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
  Config/          (module-level config, optional)
  Controllers/     (HTTP controllers + API controllers in Api/)
  Database/
    Migrations/    (timestamped migration files)
    Seeders/       (initial/reference data + module registration)
  Libraries/       (third-party API clients, integrations, optional)
  Models/          (CI4 Model subclasses)
  Services/        (business logic classes)
  Commands/        (spark CLI commands, optional)
  Views/           (server-rendered PHP templates, in a subfolder matching module key)
  Routes.php       (route declarations for this module)
```

### Naming (load-bearing)

**All module identifiers are in English and must be homologous across the platform.** See `CONVENTIONS.md §2.1` for the full table mapping folder, namespace, key, URL prefix, controller, service, model, DB table, and sidebar key. Spanish (or any localized) text appears only in the `modules.name` display column and user-facing copy, never in code, URLs, or schema.

### Wiring a new module

A module is wired into the platform through five touch points. All five must use the same identifier (see CONVENTIONS.md §2.1 for the full table).

1. **Autoload** (`app/Config/Autoload.php`): register the namespace.
   ```php
   'App\Modules\Mailboxes' => APPPATH . 'Modules/Mailboxes',
   ```

2. **Routes** (`app/Config/Routes.php`): require the module's `Routes.php`.
   ```php
   require APPPATH . 'Modules/Mailboxes/Routes.php';
   ```

3. **Services** (`app/Config/Services.php`): register the module's primary service for DI via `service('<name>Service')`.
   ```php
   public static function mailboxesService(bool $getShared = true): MailboxesService { ... }
   ```

4. **Module registration** (module seeder): insert the module into the `modules` table and grant SuperAdmin access via `role_module`. The seeder lives at `app/Modules/<Folder>/Database/Seeders/<Folder>ModuleSeeder.php` and runs via `php spark db:seed App\Modules\<Folder>\Database\Seeders\<Folder>ModuleSeeder`.

5. **Sidebar** (`app/Modules/Core/Views/partials/sidebar.php`): add an entry to `$moduleIcons` and (optionally) `$moduleSubnav` keyed by the module key.

### Module checklist (PR review)

When reviewing a new module PR, verify:

- [ ] Folder name is `PascalCase` English plural noun.
- [ ] All PHP files declare the matching `namespace App\Modules\<Folder>\...`.
- [ ] `modules.key`, `route_base`, URL prefix, filter argument, and view folder all use the same lowercase English string.
- [ ] `Routes.php` applies both `auth` and `module_access:<key>` filters to every web route group.
- [ ] Every web action has a mirrored API endpoint under `/api/v1/<key>/...`.
- [ ] Migrations live under `app/Modules/<Folder>/Database/Migrations/` and use timestamped filenames.
- [ ] A `<Folder>ModuleSeeder.php` registers the module and links it to SuperAdmin.
- [ ] Autoload, Services container, Routes loader, and sidebar all reference the module.
- [ ] Postman collection updated with the new endpoints (see §7).
- [ ] No `buzones`-style mismatch: a single `grep -rni "<key>" app/` returns every file that should reference it.

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

The canonical Postman collection is maintained at `/docs/referencia/tt-apps.postman_collection.json`. It must be updated whenever an API endpoint is added or changed. Import it into Postman to test all endpoints.

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

---

## 11. PWA (instalación en el escritorio)

Nexus se puede instalar como aplicación de escritorio desde Chrome o Edge. Son
cuatro piezas, todas estáticas salvo el parcial:

```
public/manifest.json                          ← nombre, colores, íconos, display: standalone
public/sw.js                                  ← service worker
public/offline.html                           ← pantalla sin conexión (estilos propios, no depende de app.css)
public/img/icons/                             ← icon-192, icon-512, icon-maskable-512
app/Modules/Core/Views/partials/pwa.php       ← <link rel="manifest">, theme-color y registro del SW
```

El parcial va en el `<head>` de **ambos** layouts (`main.php` y `auth.php`): la app
instalada puede abrir primero en el login, y sin manifiesto ahí el navegador
considera que esa pantalla quedó fuera del alcance de la aplicación.

**Requisitos del navegador:** HTTPS (salvo `localhost`), manifiesto con íconos de
192 y 512, y un service worker registrado con manejador `fetch`. Sin cualquiera
de los tres no aparece el botón de instalar.

### Reglas del service worker

Un service worker vive en el navegador de cada usuario y sobrevive a los
despliegues, así que `public/sw.js` se mantiene deliberadamente estrecho:

- **El HTML nunca se cachea.** Toda navegación va a la red; si falla, se muestra
  `offline.html`. Nexus es una app con sesión y una página guardada podría
  mostrarle a un usuario datos de otro en una computadora compartida.
- **Solo intercepta GET del mismo origen bajo `css/`, `js/` e `img/`**, que hoy
  contienen únicamente activos de la aplicación. Si alguna vez se sirve contenido
  de usuario bajo esas rutas (fotos, adjuntos), hay que excluirlo antes.
- **No toca** POST, `/api/v1`, el login ni las descargas (exportaciones a
  CSV/Excel): pasan de largo con sus cookies y su token CSRF intactos.
- Los estáticos se sirven de caché y se revalidan en segundo plano
  (*stale-while-revalidate*), contra el servidor y no contra la caché del
  navegador.

### Despliegues de CSS/JS

Las páginas piden los estáticos con la fecha de modificación del archivo en la
URL (`css/app.css?v=1785916675`), vía el helper `asset_url()` de
`app/Common.php`. **Es lo que hace que un cambio se vea de inmediato.**

Sin esa marca la URL nunca cambiaría, y como el servidor no manda
`Cache-Control` en los estáticos (solo `ETag`), el navegador aplica su
heurística de frescura y puede dar por buena la copia guardada durante horas;
el service worker, además, serviría su copia y el usuario vería HTML nuevo con
CSS viejo. Con la marca, un archivo modificado es una URL distinta: no hay nada
guardado bajo esa clave, se pide a la red y no existe la ventana de
inconsistencia. El service worker borra solo las versiones anteriores del mismo
archivo para que la caché no crezca con cada despliegue.

Al agregar un `<link>` o `<script>` a un archivo de `public/`, usa `asset_url()`
en lugar de `base_url()`.

**Para desactivarlo:** pon `DISABLED = true` en `public/sw.js` y despliega. El
worker borra sus cachés y se desinstala solo en la siguiente visita, sin que los
usuarios tengan que hacer nada.
