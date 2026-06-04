# BRIEF — Plataforma Modular Interna (CodeIgniter 4)

## Contexto
ibrastudio. Plataforma interna multipropósito en PHP/CodeIgniter 4, diseñada desde el inicio para crecer por módulos. El núcleo (auth + RBAC) es transversal; cada utilidad futura se monta como módulo independiente. El primer módulo de negocio es **Comunicación Interna** (envío masivo de correos a usuarios de la organización).

> Diseño/UI: ver `DESIGN.md` (no abordar aquí).

---

## 1. Stack y convenciones

- **Framework:** CodeIgniter 4 (última estable 4.x).
- **PHP:** 8.2+.
- **BD:** MySQL/MariaDB, usando Migrations y Seeders de CI4.
- **Estructura modular:** usar el sistema de módulos de CI4 (`app/Modules/` con namespaces PSR-4 por módulo, o paquetes vía Composer). Cada módulo encapsula sus Controllers, Models, Migrations, Routes y Views.
- **Autoload de módulos:** registrar namespaces en `app/Config/Autoload.php` y rutas vía el `Routes.php` de cada módulo.
- **Capa de servicio:** lógica de negocio en clases `Service` (no en controllers). Controllers delgados.
- **Validación:** reglas centralizadas por módulo.
- **Convención de respuesta:** vistas server-side para UI; endpoints JSON donde haya AJAX (preview, envío async).

---

## 2. Arquitectura modular

```
app/Modules/
  Core/            ← auth, usuarios, roles, permisos, módulos
  Communications/  ← Módulo 1: comunicados masivos
```

Cada módulo declara:
- Sus rutas (prefijo propio, ej. `/admin/users`, `/comms`).
- Sus migraciones.
- Su registro en la tabla `modules` (para el control de acceso por rol).

**Regla clave:** el acceso a un módulo NO se hardcodea; se valida en runtime contra los permisos del rol del usuario logueado (filtro/middleware global).

---

## 3. Núcleo: Auth + RBAC

### 3.1 Modelo de datos (mínimo)

- **users**: id, name, email (único), password (hash bcrypt/argon2), status (active/inactive), created_at, updated_at.
- **roles**: id, name (único), description, status.
- **modules**: id, key (slug único, ej. `communications`), name, description, route_base, is_active. *(Registro de módulos disponibles en la plataforma.)*
- **role_module** (pivote): role_id, module_id. *(Qué módulos puede ver/usar cada rol.)*
- **user_roles** (pivote): user_id, role_id. *(Soportar 1..N roles por usuario; si se prefiere 1 rol, dejar el FK directo pero el pivote da flexibilidad futura.)*

Opcional para granularidad fina (recomendado dejar la estructura aunque empiece simple):
- **permissions**: id, module_id, key (ej. `communications.send`, `communications.view`), name.
- **role_permission** (pivote): role_id, permission_id.

> Empezar con acceso **por módulo** (role_module). La tabla permissions queda preparada para acciones finas (ver / crear / enviar) sin rehacer el esquema.

### 3.2 Autenticación
- Login con email + password, throttling de intentos fallidos.
- Sesión gestionada con CI4 Session + filtro `auth`.
- Logout, recordar sesión opcional, reset de contraseña vía email (reutiliza el servicio de mailing del Módulo 1).
- Sin auto-registro: los usuarios se crean desde el panel (CRUD por admin).

### 3.3 Autorización (filtros CI4)
- **AuthFilter**: exige sesión válida.
- **ModuleAccessFilter**: dado el módulo de la ruta, valida que algún rol del usuario tenga acceso a ese `module_id`. Si no, 403.
- Aplicar filtros por grupo de rutas en cada módulo.
- Helper/Service `Access` con métodos `canAccessModule($key)` y `can($permissionKey)` para usar en vistas (mostrar/ocultar menú) y controllers.

### 3.4 ABM del núcleo (UI admin)
- **Usuarios:** listar, crear, editar, activar/desactivar, asignar rol(es), reset password.
- **Roles:** crear, editar, eliminar; pantalla para marcar a qué módulos da acceso el rol (checklist de `modules`); si se activan permisos finos, checklist de acciones.
- **Menú dinámico:** se construye según los módulos accesibles del usuario.

### 3.5 Seeders iniciales
- Rol `SuperAdmin` con acceso a todos los módulos.
- Usuario admin inicial (credenciales vía `.env`, no hardcode).
- Registro del módulo `communications` en tabla `modules`.

---

## 4. Módulo 1 — Comunicación Interna

**Objetivo:** enviar comunicados por correo, de forma masiva, a listas de usuarios internos, con composición de contenido, imagen adjunta/embebida y vista previa antes de enviar.

### 4.1 Entidades

- **recipients** (destinatarios internos): id, name, email, area/departamento (opcional), status (active/inactive), created_at.
  > Independientes de `users` del sistema: un destinatario no necesita cuenta. Permite cargar gente de la organización que solo recibe correos.
- **recipient_lists**: id, name, description, created_by, created_at.
- **list_recipient** (pivote): list_id, recipient_id.
- **communications** (comunicados): id, title, subject, body_html, image_path (nullable), status (draft/scheduled/sending/sent/failed), created_by, scheduled_at (nullable), sent_at (nullable), created_at, updated_at.
- **communication_list** (pivote): qué lista(s) recibe cada comunicado.
- **communication_logs**: id, communication_id, recipient_id, status (queued/sent/failed/bounced), error (nullable), sent_at. *(Trazabilidad por destinatario.)*

### 4.2 Gestión de destinatarios y listas
- CRUD de destinatarios.
- **Importación masiva** vía CSV/XLSX (subir archivo → validar columnas name/email/area → guardar en BD; reportar filas inválidas/duplicadas).
- CRUD de listas; asignar/quitar destinatarios a una lista (multi-select o por importación a una lista concreta).

### 4.3 Composición del comunicado
- Formulario: título (interno), asunto del correo, cuerpo (editor enriquecido / HTML), selección de una o varias listas destino.
- **Carga de imagen:** subida con validación (tipo, peso máximo, dimensiones), almacenamiento en `writable/uploads/communications/`. Decidir e implementar **imagen embebida en el HTML del correo** (inline/CID) y/o como banner del cuerpo; CID es lo más robusto para clientes de correo.
- Plantilla HTML base de correo (responsive, inline-CSS para compatibilidad) donde se inyecta el cuerpo + imagen.
- Guardar como **borrador** en cualquier momento.

### 4.4 Vista previa
- **Preview en pantalla** del HTML final renderizado (iframe sandbox) tal como llegará al correo.
- Opción **enviar correo de prueba** a una dirección que indique el usuario, antes del envío masivo.

### 4.5 Envío
- Servicio `Mailer` basado en CI4 `Email` (configurable SMTP vía `.env`).
- **Envío en cola/lotes** para evitar timeouts y respetar límites del SMTP: usar tabla de cola + un comando CLI (`spark`) procesable por cron (ej. `php spark comms:process-queue`), enviando en batches con throttling configurable.
- Estados del comunicado actualizados según avance; log por destinatario en `communication_logs`.
- Manejo de errores y reintentos básicos para fallos transitorios.
- (Opcional) Programar envío (`scheduled_at`) procesado por el mismo cron.

### 4.6 Listado e historial
- Lista de comunicados con estado, fecha, nº destinatarios, nº enviados/fallidos.
- Detalle de un comunicado: contenido, listas destino, métricas y log de envío descargable.

### 4.7 Permisos del módulo (si se activan finos)
- `communications.view`, `communications.recipients.manage`, `communications.compose`, `communications.send`.

---

## 5. Configuración y entorno
- `.env` para: credenciales BD, SMTP (host/port/user/pass/encryption), `app.baseURL`, datos del admin seed, límites de lote/throttling de envío, peso/tipos de imagen permitidos.
- No commitear secretos. Incluir `.env.example`.

---

## 6. Entregables esperados de Claude Code
1. Proyecto CI4 con estructura modular funcionando (Core + Communications registrados).
2. Migrations + Seeders del esquema completo descrito.
3. Auth (login/logout/reset) + filtros `Auth` y `ModuleAccess` operativos.
4. ABM de usuarios, roles y asignación de módulos a roles, con menú dinámico.
5. Módulo Communications completo: destinatarios, listas, importación CSV, composición con imagen, preview, envío de prueba, cola CLI + cron, historial y logs.
6. `.env.example` y un `README.md` con: requisitos, instalación, migrate/seed, configuración SMTP, y cómo correr el worker de cola por cron.

---

## 7. Criterios de aceptación (resumen)
- Un usuario sin acceso al módulo recibe 403 y no ve el ítem en el menú.
- Un rol puede crearse y limitarse a ciertos módulos; el usuario asignado solo accede a esos.
- Se puede importar una lista por CSV, componer un comunicado con imagen, previsualizarlo, enviar una prueba y luego el envío masivo, quedando registro por destinatario.
