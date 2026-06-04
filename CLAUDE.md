# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

---

## Project Overview

**tt-apps** is a modular internal platform built with **CodeIgniter 4**, designed to grow through independent modules while maintaining a shared authentication and role-based access control (RBAC) core.

- **Organization:** ibrastudio
- **Framework:** CodeIgniter 4 (latest stable 4.x)
- **PHP Version:** 8.2+
- **Database:** MySQL/MariaDB with CI4 Migrations and Seeders
- **Initial Module:** Communications (internal bulk email system)

The platform enforces a strict modular architecture where each module encapsulates its own routes, controllers, models, migrations, and views, with runtime validation of module access via user roles.

---

## Reference Documents

| Document | Purpose |
|---|---|
| `docs/ARCHITECTURE.md` | CI4 request lifecycle, module system, controller/service/model patterns, API structure, DB schema design |
| `docs/CONVENTIONS.md` | PHP/CI4 coding conventions: PSR-12, naming, routes, migrations, security, git commits |
| `docs/tt-apps.postman_collection.json` | Postman collection — import to test all API endpoints |
| `DESIGN.md` | UI/UX design system — all CSS tokens, components, accessibility rules |
| `BRIEF.md` | Original functional spec — acceptance criteria and full feature list |

---

## Architecture & Key Patterns

### Modular Structure

```
app/Modules/
  Core/              # Shared: auth, users, roles, permissions, module registry
  Communications/    # Module 1: bulk email communications
  [future modules]
```

**Core Principles:**
- Each module registers its namespace in `app/Config/Autoload.php`
- Routes are declared in each module's `Routes.php`
- Migrations are module-scoped
- Module registration via `modules` table tracks which roles can access which modules
- **Critical:** Module access is never hardcoded; it's validated at runtime via filters against the user's roles

### Authentication & Authorization

**Authentication (CI4 Session-based):**
- Email + password login with attempt throttling
- Session managed by CI4's Session class
- AuthFilter validates active session
- Password reset via email (uses Communications module's mailer)
- No self-registration; users created via admin panel

**Authorization (Role-Based Module Access):**
- **AuthFilter:** Ensures valid session exists
- **ModuleAccessFilter:** Checks if user's roles grant access to the requested module; returns 403 otherwise
- Filters applied per module route group
- Access helper/service provides `canAccessModule($key)` and `can($permissionKey)` for templates and controllers
- Dynamic menu built from accessible modules per user

### Database Model

**Core entities:**
- `users` – id, name, email (unique), password (bcrypt/argon2), status, timestamps
- `roles` – id, name (unique), description, status
- `modules` – id, key (slug), name, description, route_base, is_active
- `role_module` – pivote: which roles can access which modules
- `user_roles` – pivote: users can have 1..N roles

**Optional (prepared for granular permissions):**
- `permissions` – id, module_id, key (e.g., `communications.send`), name
- `role_permission` – pivote: fine-grained permission assignment

**Communications module:**
- `recipients` – internal mailing list targets (independent of users)
- `recipient_lists` – groups of recipients
- `list_recipient` – pivote
- `communications` – email campaigns (title, subject, HTML body, image, status, scheduling)
- `communication_list` – pivote: which lists receive each campaign
- `communication_logs` – per-recipient delivery status and errors

### API Layer

**Every web action has a mirrored API endpoint.** This is non-negotiable — if a controller method exists, a corresponding API endpoint must exist.

- API routes live under `/api/v1/` with Bearer token authentication
- API controllers in `Controllers/Api/` per module, extending `BaseApiController`
- All API responses use the standard JSON envelope: `{ "status": "success"|"error", "data": ..., "errors": ... }`
- Non-CRUD actions use a verb suffix: `POST /api/v1/comms/communications/{id}/send`
- The Postman collection at `docs/tt-apps.postman_collection.json` must be updated whenever an endpoint is added or changed

See `docs/ARCHITECTURE.md §7` for the full API architecture (auth, versioning, response format, route structure).

### Service Layer & Validation

- **Business logic in Service classes**, not in controllers (keep controllers thin)
- **Validation rules** centralized per module
- **Response convention:** Server-side views for UI; JSON endpoints for AJAX/preview operations

### Design System

The platform uses a Shopify Polaris-inspired design system. Key tokens:
- **Primary color:** `#1773C8` (Blue 500)
- **CSS custom properties** for all spacing, colors, typography, shadows (see DESIGN.md §13)
- **Component hierarchy:** primary/secondary/tertiary/critical buttons, semantic badges (success/warning/critical), cards, inputs, banners, sidebars, tables
- **Accessibility:** WCAG AA minimum, visible focus states always, 44×44px touch targets, aria-labels on icon buttons

---

## Development Commands

### Prerequisites
- PHP 8.2+
- MySQL/MariaDB
- Composer

### Setup

```bash
# Install dependencies
composer install

# Create .env from .env.example
cp .env.example .env

# Configure database and SMTP in .env
# - DB_HOSTNAME, DB_DATABASE, DB_USERNAME, DB_PASSWORD
# - email.SMTPHost, email.SMTPUser, email.SMTPPass, email.SMTPPort, email.SMTPCrypto

# Run database migrations (--all discovers all module namespaces)
php spark migrate --all --namespace App\Modules\Core

# Seed initial data (superadmin role, admin user, module registry)
php spark db:seed CoreSeeder
```

### Running the Application

```bash
# Start local development server (localhost:8080)
php spark serve
```

Access the app at `http://localhost:8080`. Login with credentials from `.env` (created by seeder).

### Database

```bash
# Run migrations from Core module  (-n is the correct flag, NOT --namespace)
php spark migrate -n "App\Modules\Core"

# Run migrations from Communications module
php spark migrate -n "App\Modules\Communications"

# Run all module migrations at once
php spark migrate -n "App\Modules\Core" && php spark migrate -n "App\Modules\Communications"

# Rollback last batch (specify namespace)
php spark migrate:rollback -n "App\Modules\Core"

# Seed core data (roles, admin user, modules registry)
php spark db:seed CoreSeeder
```

### Queue/Background Tasks

```bash
# Process email queue (run via cron, configured in .env with batch size and throttle)
php spark comms:process-queue

# Test run: process 10 emails with 1-second delay between batches
php spark comms:process-queue --batch=10 --throttle=1
```

This command:
- Fetches queued communications from the database
- Sends emails in batches respecting SMTP limits
- Updates `communication_logs` with delivery status (sent/failed/bounced)
- Handles transient failures with basic retry logic

**Scheduling with cron** (typical every 5 minutes):
```
*/5 * * * * cd /path/to/app && php spark comms:process-queue
```

### Code Style & Linting

```bash
# Check PHP syntax (if linter configured)
php spark lint

# Format code (if formatter configured, e.g., PHP-CS-Fixer)
composer run format
```

### Testing

```bash
# Run all tests (if PHPUnit configured)
./vendor/bin/phpunit

# Run tests for a specific module
./vendor/bin/phpunit tests/Modules/Core/

# Run a single test file
./vendor/bin/phpunit tests/Modules/Communications/Models/CommunicationLogTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html build/coverage
```

---

## File Organization & Key Locations

### Controllers

Location: `app/Modules/[ModuleName]/Controllers/`

- Web controllers: thin, return views or redirects
- API controllers: in `Controllers/Api/`, always return JSON via `BaseApiController`
- Every web action must have a parallel API action

Examples:
- `app/Modules/Communications/Controllers/Communications.php` (web)
- `app/Modules/Communications/Controllers/Api/CommunicationsApiController.php` (API)

### Models

Location: `app/Modules/[ModuleName]/Models/`

- Extend `CodeIgniter\Model`
- Define table name, primary key, allowed/protected fields
- Use CodeIgniter's query builder for complex queries
- Keep business logic in Services, not in models

Example: `app/Modules/Communications/Models/Communication.php`

### Services

Location: `app/Modules/[ModuleName]/Services/`

- Encapsulate business logic (e.g., `MailerService`, `ImportService`)
- Injected into controllers or other services
- Can call models and external APIs

Example: `app/Modules/Communications/Services/MailerService.php` handles SMTP via CI4 Email class, queuing, and delivery logging.

### Migrations

Location: `app/Modules/[ModuleName]/Database/Migrations/`

- Naming: `YYYY-MM-DD-HHMMSS_create_[table].php`
- Used by `php spark migrate`

### Seeders

Location: `app/Modules/[ModuleName]/Database/Seeders/`

- Initial data (roles, modules, admin user)
- Run via `php spark db:seed`

### Routes

Location: `app/Modules/[ModuleName]/Routes.php`

- Declare all routes for the module with prefixes (e.g., `/admin/users`, `/comms`)
- Apply filters (AuthFilter, ModuleAccessFilter) to groups
- Example:
  ```php
  $routes->group('admin', ['filter' => 'auth'], function($routes) {
      $routes->resource('users');
  });
  ```

### Views

Location: `app/Modules/[ModuleName]/Views/`

- Server-side rendered PHP templates
- Use design system tokens (CSS custom properties)
- Check user permissions with Access service: `<?= service('access')->canAccessModule('communications') ? ... : '' ?>`

### Config Files

- `app/Config/Autoload.php` – Register module namespaces
- `app/Config/Filters.php` – Global filter registration (AuthFilter, ModuleAccessFilter)
- `app/Config/Email.php` – Email configuration (SMTP settings)
- `.env` – Runtime configuration (database, SMTP, admin credentials, queue settings)
- `.env.example` – Template for .env

---

## Common Workflows

### Adding a New Module

1. Create directory `app/Modules/NewModule/`
2. Add subdirectories: `Controllers/Api/`, `Models/`, `Services/`, `Database/Migrations/`, `Database/Seeders/`, `Views/`, `Config/`
3. Register namespace in `app/Config/Autoload.php`:
   ```php
   'App\Modules\NewModule' => APPPATH . 'Modules/NewModule',
   ```
4. Create `Routes.php` — declare both web routes and `api/v1/` routes
5. Create migration to register module in `modules` table
6. Define web Controllers, API Controllers (in `Controllers/Api/`), Models, and Services
7. Create Seeder to assign module to SuperAdmin role
8. Add the new module's API endpoints to `docs/tt-apps.postman_collection.json`

### Creating Admin Pages with Module Access Control

1. Define route in module's `Routes.php` with AuthFilter and ModuleAccessFilter
2. Create Controller that extends BaseController
3. Implement action methods returning views
4. Use Access service in template to conditionally show menu items:
   ```php
   <?php if (service('access')->canAccessModule('communications')): ?>
     <a href="<?= base_url('/comms') ?>">Communications</a>
   <?php endif; ?>
   ```

### Adding Email Functionality

1. Use `MailerService` from Communications module (if sending internal emails)
2. Configure SMTP in `.env`:
   ```
   email.SMTPHost = smtp.gmail.com
   email.SMTPUser = your-email@gmail.com
   email.SMTPPass = your-app-password
   email.SMTPPort = 587
   email.SMTPCrypto = tls
   ```
3. Call service: `service('mailer')->sendMail($recipient, $subject, $body, $htmlBody)`
4. For bulk operations, queue messages and process via `php spark comms:process-queue`

### Composing & Sending Communications

1. Admin accesses `/comms/compose`
2. Fills form: title, subject, body (rich editor), image upload, recipient list selection
3. Saves as draft
4. Previews HTML in iframe sandbox
5. Optionally sends test email to specified address
6. Submits for sending (creates `communications` record with status `draft`, then `scheduled`)
7. `php spark comms:process-queue` (via cron) picks it up and sends in batches
8. Logs recorded per recipient in `communication_logs`

---

## Configuration & Environment

### .env Setup

```ini
# Database
DB_DRIVER = mysql
DB_HOSTNAME = localhost
DB_DATABASE = tt_apps
DB_USERNAME = root
DB_PASSWORD = 
DB_PORT = 3306

# App
app.baseURL = http://localhost:8080
ENVIRONMENT = development

# Email (SMTP)
email.SMTPHost = smtp.gmail.com
email.SMTPUser = your-email@gmail.com
email.SMTPPass = your-app-password
email.SMTPPort = 587
email.SMTPCrypto = tls
email.fromEmail = noreply@ibrastudio.com
email.fromName = ibrastudio

# Admin seeding (initial credentials)
ADMIN_NAME = Administrator
ADMIN_EMAIL = admin@example.com
ADMIN_PASSWORD = securepassword123

# Queue processing
COMMS_BATCH_SIZE = 50
COMMS_THROTTLE_SECONDS = 2

# Image uploads
COMMS_MAX_IMAGE_SIZE = 5242880  # 5MB in bytes
COMMS_ALLOWED_IMAGE_TYPES = image/jpeg,image/png,image/gif
```

---

## Important Constraints & Patterns

- **No hardcoded module access:** Always check at runtime via filters and Access service
- **Thin controllers:** Move logic to Services
- **Centralized validation:** Define validation rules in Service or Config, reuse across controllers and API endpoints
- **Module isolation:** Migrations, routes, and views are scoped per module; avoid cross-module direct calls (use Services as interfaces)
- **Responsive HTML emails:** Communications use inline CSS and CID-embedded images to ensure SMTP client compatibility
- **Soft deletes not used initially:** Status columns (active/inactive, draft/sent) track state instead
- **No auto-registration:** Users created only via admin panel to maintain control
- **Design tokens:** All styling uses CSS custom properties defined in global stylesheet; no hardcoded values in component CSS

---

## Debug & Troubleshooting

### Enable Debug Mode

In `.env`, set `ENVIRONMENT = development` and `CI_ENVIRONMENT = development`. Enables detailed error pages and logging.

### Check Logs

```bash
# Application logs
tail -f writable/logs/log-*.log

# Email queue issues
php spark comms:process-queue --debug
```

### Database Issues

- Verify migrations ran: `php spark migrate:status`
- Check `modules` table is populated: `SELECT * FROM modules;`
- Verify `role_module` entries exist: `SELECT * FROM role_module;`

### Email Not Sending

1. Verify SMTP credentials in `.env`
2. Check `communication_logs` for error messages
3. Ensure cron job is running: `php spark comms:process-queue`
4. Test manually: `php spark comms:send-test recipient@example.com`

### Module Access Denied (403)

1. Verify user has a role assigned: Check `user_roles` table
2. Verify role is linked to module: Check `role_module` table
3. Check ModuleAccessFilter is applied to the route group

---

## Design System Reference

See **DESIGN.md** for comprehensive UI/UX guidelines including:
- Color palette (blues, neutrals, semantics)
- Typography scale and weights
- Spacing system (4px base)
- Component hierarchy (buttons, inputs, cards, badges, banners, tables)
- Accessibility requirements (WCAG AA, focus states, touch targets)
- Motion and transitions
- Layout grid and responsive breakpoints

Key rule: **Always use CSS custom property tokens, never hardcode values.**

---

## Future Extensibility

The platform is designed to easily add modules. Core patterns:
- **New modules follow the same structure:** Controllers → Services → Models
- **Permissions can be granularized:** Currently role-based by module; `permissions` table ready for action-level control (view/create/send)
- **Queue system extensible:** Can be reused for other async tasks beyond email
- **Email templates:** Currently inline HTML; can migrate to dedicated template system
- **Logging & analytics:** Communication logs are a foundation for tracking metrics and delivery reports

