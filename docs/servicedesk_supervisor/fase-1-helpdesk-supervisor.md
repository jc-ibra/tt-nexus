# Fase 1: Módulo HelpdeskSupervisor

> **Documento:** Especificación técnica para Claude Code  
> **Módulo:** HelpdeskSupervisor  
> **Clave:** `helpdesk_supervisor`  
> **Ruta base:** `/helpdesk-supervisor`  
> **Prefijo de tablas:** `helpdesk_supervisor_`  
> **Namespace:** `App\Modules\HelpdeskSupervisor`  
> **UI display:** "Supervisor de Mesa"  
> **Fase:** 1 de 3  
> **Prerrequisito:** Leer `NEXUS.md` y `CONEXIONES.md` (ya en el proyecto)

---

## 1. Objetivo

Construir un módulo que audite automáticamente los tickets de GLPI contra las reglas del Manual de Uso de GLPI para agentes MAC, y presente las desviaciones en un tablero por agente y por regla, sin que el supervisor tenga que entrar a GLPI ni descargar informes.

Este módulo es la base de datos para la Fase 3 (módulo de KPIs de agentes).

---

## 2. Dependencias existentes a reutilizar

| Componente | Dueño | Cómo se reutiliza |
|---|---|---|
| `GlpiDbConnection` | Provisioning | Conexión directa a la BD de GLPI. Se consume como servicio, sin modificar. |
| `MailerService` | Communications | Envío de correo por SMTP. Se usará en Fase 2 (no en esta fase). |
| Claude API config | ServiceDesk (`servicedesk_settings`) | Se usará en Fase 2 para redacción IA (no en esta fase). |
| AuthFilter / ModuleAccessFilter | Core | Control de acceso estándar. |

**Importante:** la instancia GLPI destino es `helpdesk.trantortechnologies.mx`. La conexión ya está resuelta por `GlpiDbConnection` que lee sus parámetros de `provisioning_settings`. Si la instancia nueva usa credenciales diferentes, se parametriza en la tabla `helpdesk_supervisor_settings` (ver sección 5) con su propia conexión, reutilizando el patrón de `GlpiDbConnection` pero con sus propios parámetros.

---

## 3. Mapeo de agentes: campo `glpi_user_id` en Core

### 3.1 Migración en Core

Agregar una columna nullable `glpi_user_id` (INT, UNSIGNED, nullable, default NULL) a la tabla `users` de Core.

```
Nombre de migración: YYYY-MM-DD-HHMMSS_add_glpi_user_id_to_users.php
Namespace: App\Modules\Core
```

- Tipo: `INT UNSIGNED`, nullable, default NULL.
- No es unique a nivel de BD (podrían existir usuarios de Nexus sin mapear).
- Solo se llena para usuarios que son agentes MAC medibles.

### 3.2 UI de administración

En la pantalla de edición de usuario (Core, panel de administración):

- Agregar un campo opcional "ID de usuario en GLPI" (`glpi_user_id`).
- Tooltip o ayuda: "Identificador numérico del usuario en GLPI. Se obtiene del perfil del usuario en GLPI. Solo necesario para agentes que serán auditados."
- Validación: entero positivo o vacío.

### 3.3 API

Actualizar el endpoint de usuarios de Core para aceptar y devolver `glpi_user_id` en el JSON.

---

## 4. Estructura del módulo

```
app/Modules/HelpdeskSupervisor/
  Config/
  Controllers/
    Api/
      HelpdeskSupervisorApiController.php
  Database/
    Migrations/
    Seeders/
      HelpdeskSupervisorModuleSeeder.php
  Models/
    AuditRun.php
    Deviation.php
    Escalation.php
    HelpdeskSupervisorSetting.php
  Rules/                          # <-- Clases de reglas de validación
    RuleInterface.php
    TitleFormatRule.php
    ReclassificationRule.php
    FieldCompletenessRule.php
    OpeningDateDefaultRule.php
    AbandonedTicketsRule.php
    FollowUpActivityRule.php
    CoordinatorAssignmentRule.php
    CorrectTabRule.php
    IdsTabRule.php
    ExternalIdRule.php
  Services/
    AuditRunnerService.php
    GlpiAuditQueryService.php
    DeviationService.php
    HelpdeskSupervisorSettingsService.php
  Views/
    dashboard.php
    agent_detail.php
    rule_detail.php
    escalations/
      index.php
      create.php
    settings.php
  Routes.php
```

---

## 5. Tablas de base de datos

### 5.1 `helpdesk_supervisor_settings`

Configuración del módulo (llave-valor, patrón estándar de Nexus).

| Columna | Tipo | Descripción |
|---|---|---|
| id | INT UNSIGNED AI PK | |
| key | VARCHAR(100) UNIQUE | Clave del setting |
| value | TEXT NULL | Valor (puede ser cifrado) |
| is_encrypted | TINYINT(1) DEFAULT 0 | Si el valor está cifrado |
| created_at | DATETIME | |
| updated_at | DATETIME | |

Settings iniciales:

| Key | Valor por defecto | Cifrado | Descripción |
|---|---|---|---|
| `glpi_db_host` | (vacío) | No | Host de la BD GLPI. Si vacío, reutiliza la config de Provisioning. |
| `glpi_db_port` | 3306 | No | Puerto. |
| `glpi_db_name` | (vacío) | No | Nombre de la BD GLPI. |
| `glpi_db_user` | (vacío) | No | Usuario. |
| `glpi_db_password` | (vacío) | Sí | Contraseña (cifrada con `encryption.key`). |
| `glpi_db_reuse_provisioning` | 1 | No | Si es 1, reutiliza la conexión de Provisioning en lugar de sus propios parámetros. |
| `audit_auto_run` | 0 | No | Reservado: si se habilita cron de auditoría automática. |
| `business_days_abandonment` | 5 | No | Días hábiles sin actividad para considerar abandono (KPI 4). |

### 5.2 `helpdesk_supervisor_audit_runs`

Registro de cada ejecución de auditoría.

| Columna | Tipo | Descripción |
|---|---|---|
| id | INT UNSIGNED AI PK | |
| period_start | DATE | Inicio del período auditado |
| period_end | DATE | Fin del período auditado |
| agent_glpi_user_id | INT UNSIGNED NULL | NULL = todos los agentes; valor = agente específico |
| total_tickets_audited | INT UNSIGNED DEFAULT 0 | Tickets procesados |
| total_deviations_found | INT UNSIGNED DEFAULT 0 | Desviaciones detectadas |
| status | ENUM('running','completed','failed') DEFAULT 'running' | |
| error_message | TEXT NULL | Si status = failed |
| run_by_user_id | INT UNSIGNED | Usuario Nexus que ejecutó la auditoría |
| started_at | DATETIME | |
| completed_at | DATETIME NULL | |
| created_at | DATETIME | |

### 5.3 `helpdesk_supervisor_deviations`

Cada desviación encontrada, vinculada a un run, un ticket y un agente.

| Columna | Tipo | Descripción |
|---|---|---|
| id | INT UNSIGNED AI PK | |
| audit_run_id | INT UNSIGNED FK | Referencia al audit_run |
| glpi_ticket_id | INT UNSIGNED | ID del ticket en GLPI |
| glpi_ticket_title | VARCHAR(255) | Título del ticket (snapshot para no depender de GLPI al consultar) |
| glpi_user_id | INT UNSIGNED | ID del agente en GLPI |
| nexus_user_id | INT UNSIGNED NULL | ID del usuario en Nexus (mapeado por `glpi_user_id`). NULL si no está mapeado. |
| agent_name | VARCHAR(150) | Nombre del agente (snapshot) |
| rule_key | VARCHAR(50) | Identificador de la regla (ej. `title_format`, `reclassification`) |
| rule_name | VARCHAR(150) | Nombre legible de la regla |
| severity | ENUM('critical','warning','info') DEFAULT 'warning' | |
| field_affected | VARCHAR(100) NULL | Campo que presenta la desviación |
| expected_value | TEXT NULL | Lo que debía encontrarse |
| actual_value | TEXT NULL | Lo que se encontró |
| detail | TEXT NULL | Descripción legible de la desviación |
| manual_reference | VARCHAR(100) NULL | Referencia al manual (ej. "Parte 3.3 - Título") |
| kpi_mapping | VARCHAR(20) NULL | A qué KPI alimenta (ej. "KPI-1", "KPI-2"). NULL si no aplica. |
| created_at | DATETIME | |

Índices: `(audit_run_id)`, `(glpi_user_id)`, `(rule_key)`, `(glpi_ticket_id)`, `(kpi_mapping)`.

### 5.4 `helpdesk_supervisor_escalations`

Registro manual de escalaciones validadas por el supervisor (KPI 5).

| Columna | Tipo | Descripción |
|---|---|---|
| id | INT UNSIGNED AI PK | |
| glpi_ticket_id | INT UNSIGNED | Ticket en GLPI donde ocurrió la omisión |
| glpi_user_id | INT UNSIGNED | Agente responsable en GLPI |
| nexus_user_id | INT UNSIGNED NULL | Agente en Nexus (mapeado) |
| agent_name | VARCHAR(150) | Nombre del agente |
| escalation_date | DATE | Fecha de la escalación |
| reason | TEXT | Motivo detallado de la escalación |
| reported_by | VARCHAR(150) NULL | Quién reportó la queja |
| validated_by_user_id | INT UNSIGNED | Usuario Nexus que validó (el supervisor) |
| period_year | SMALLINT UNSIGNED | Año del período de medición |
| period_month | TINYINT UNSIGNED | Mes del período de medición |
| is_valid | TINYINT(1) DEFAULT 1 | Si la escalación fue validada como procedente |
| created_at | DATETIME | |
| updated_at | DATETIME | |

Índices: `(glpi_user_id, period_year, period_month)`, `(glpi_ticket_id)`.

---

## 6. Reglas de validación (audit rules)

Cada regla implementa `RuleInterface`:

```php
interface RuleInterface
{
    public function key(): string;           // Identificador único (ej. 'title_format')
    public function name(): string;          // Nombre legible
    public function manualReference(): string; // Referencia al manual
    public function kpiMapping(): ?string;   // KPI al que alimenta (null si no aplica)
    public function severity(): string;      // 'critical', 'warning', 'info'

    /**
     * Recibe los datos de UN ticket (con sus campos custom, logs, etc.)
     * y devuelve un array de desviaciones encontradas (puede ser vacío).
     * Cada desviación es un array asociativo con:
     *   field_affected, expected_value, actual_value, detail
     */
    public function evaluate(array $ticketData): array;
}
```

### 6.1 `TitleFormatRule` (key: `title_format`)

- **Manual ref:** Parte 3.3 - Título
- **KPI mapping:** null (no mide KPI directamente, pero sí calidad)
- **Severidad:** warning
- **Lógica:**
  - Verificar que el título esté completamente en MAYÚSCULAS.
  - Para tickets de categorías CE (Clientes Externos): validar patrón `CLIENTE - SUCURSAL - DESCRIPCIÓN`. El segmento CLIENTE debe coincidir con el nombre del cliente de la categoría seleccionada.
  - Para categorías internas y de administración: validar patrón `NOMBRE CATEGORÍA - DESCRIPCIÓN` (sin segmento sucursal).
  - Categorías Edificios: validar patrón `CLIENTE - EDIFICIOS - DESCRIPCIÓN`.
  - Categorías Data Center: validar que lleve `DATA CENTER` como sucursal.
- **Datos necesarios de GLPI:** `glpi_tickets.name`, `glpi_itilcategories` (para saber la categoría y derivar el patrón esperado).

### 6.2 `ReclassificationRule` (key: `reclassification`)

- **Manual ref:** Parte 3.3 - Categoría; KPI 2 del sistema de evaluación
- **KPI mapping:** KPI-2
- **Severidad:** warning
- **Lógica:**
  - Buscar en `glpi_logs` del ticket si los campos Categoría (`itilcategories_id`), Tipo (`type`) o los campos custom de tipo de equipo / tipo de solicitud cambiaron de valor después de la fecha de creación del ticket.
  - Si hay cambio, es una reclasificación: el agente no clasificó bien al abrir.
- **Datos necesarios de GLPI:** `glpi_logs` filtrado por `items_id` (ticket), `itemtype` = 'Ticket', campos relevantes (`id_search_option` correspondiente a categoría, tipo), donde `date_mod` > `date` del ticket.

### 6.3 `FieldCompletenessRule` (key: `field_completeness`)

- **Manual ref:** Parte 3.7 - Campos personalizados; KPI 3 del sistema de evaluación
- **KPI mapping:** KPI-3
- **Severidad:** critical
- **Lógica por categoría:**
  - **Clientes Externos:** verificar que en la tab CE estén llenos: Regional, Estado, Municipio, Local/Foráneo, Usuario, Equipo, Modelo, Serie, CC. `NO PROPORCIONADO` cuenta como lleno (es la convención), pero vacío no.
  - **Áreas Internas:** verificar Equipo (obligatorio).
  - **Control de Activos:** verificar Equipo, Modelo, Serie.
  - **Control de Envíos:** verificar Guía, Carrier, Proyecto, Solicitante, CC, Remitente (nombre, estado, localidad), Destinatario (nombre, estado, localidad), Fecha de envío.
  - Verificar también campos del ticket base: Título no vacío, Categoría asignada, Estado, Origen, ID Externo.
- **Datos necesarios de GLPI:** tablas del plugin Additional Fields correspondientes a cada tab. La introspección de estas tablas ya está resuelta en ServiceDesk (`GlpiSchemaIntrospector`); se puede reutilizar el patrón.

### 6.4 `OpeningDateDefaultRule` (key: `opening_date_default`)

- **Manual ref:** Parte 3.3 - Fecha de apertura
- **KPI mapping:** null
- **Severidad:** warning
- **Lógica:**
  - Comparar `glpi_tickets.date` (fecha de apertura que captura el agente) con `glpi_tickets.date_creation` (fecha de creación real del registro en GLPI).
  - Si la diferencia absoluta es menor a 60 segundos, es probable que el agente dejó la fecha por default.
  - Tolerancia configurable en settings.
- **Datos necesarios de GLPI:** `glpi_tickets.date`, `glpi_tickets.date_creation`.

### 6.5 `AbandonedTicketsRule` (key: `abandoned_tickets`)

- **Manual ref:** Parte 4.1 - Propiedad del ticket; KPI 4 del sistema de evaluación
- **KPI mapping:** KPI-4
- **Severidad:** critical
- **Lógica:**
  - Buscar tickets **abiertos** (no resueltos ni cerrados) del agente donde la última modificación por el agente (`glpi_logs` o `glpi_tickets.date_mod`) sea mayor a N días hábiles (configurable, default 5).
  - El cálculo de días hábiles excluye sábado y domingo. Días festivos se pueden agregar después.
  - Solo cuenta la actividad del propio agente, no de otros usuarios.
- **Datos necesarios de GLPI:** `glpi_tickets.status`, `glpi_tickets.date_mod`, `glpi_logs` filtrado por `user_name` del agente.

### 6.6 `FollowUpActivityRule` (key: `followup_activity`)

- **Manual ref:** Parte 4.1 - Propiedad del ticket; KPI 1 del sistema de evaluación
- **KPI mapping:** KPI-1
- **Severidad:** warning
- **Lógica:**
  - Para tickets cerrados o resueltos en el período: verificar que el agente haya registrado al menos una actualización (seguimiento, tarea o solución) entre la fecha de apertura y la fecha de resolución/cierre.
  - Se busca en `glpi_itilfollowups`, `glpi_tickettasks`, `glpi_itilsolutions` donde `users_id` = el agente.
- **Datos necesarios de GLPI:** `glpi_itilfollowups`, `glpi_tickettasks`, `glpi_itilsolutions`.

### 6.7 `CoordinatorAssignmentRule` (key: `coordinator_assignment`)

- **Manual ref:** Parte 3.5 - Asignación del ticket
- **KPI mapping:** null
- **Severidad:** warning
- **Lógica:**
  - Solo aplica a categorías de atención dinámica por zona (la mayoría de CE).
  - Cruzar el estado capturado en la tab Clientes Externos con la tabla de coordinadores por estado (hardcoded en la regla o configurable).
  - Verificar que el usuario asignado al ticket (`glpi_tickets_users` con `type` = 2, assigned) coincida con el coordinador esperado para ese estado.
  - Si no coincide, es desviación.
- **Datos necesarios de GLPI:** `glpi_tickets_users`, tab CE (campo estado), tabla interna de mapeo estado-coordinador.
- **Tabla de mapeo:** esta regla necesita una tabla de referencia `helpdesk_supervisor_coordinator_map` o un archivo de configuración con el mapeo estado-coordinador. Se recomienda tabla para que sea editable desde la UI.

#### Tabla adicional: `helpdesk_supervisor_coordinator_map`

| Columna | Tipo | Descripción |
|---|---|---|
| id | INT UNSIGNED AI PK | |
| state_name | VARCHAR(100) | Nombre del estado (ej. "Nuevo León") |
| coordinator_glpi_user_id | INT UNSIGNED | ID del coordinador en GLPI |
| coordinator_name | VARCHAR(150) | Nombre del coordinador (para display) |
| zone | VARCHAR(50) | Zona (ej. "DTN - Zona 1") |
| created_at | DATETIME | |
| updated_at | DATETIME | |

Se carga con un seeder basado en la tabla de la Parte 3.5 del manual.

### 6.8 `CorrectTabRule` (key: `correct_tab`)

- **Manual ref:** Parte 3.7 - Campos personalizados
- **KPI mapping:** null
- **Severidad:** info
- **Lógica:**
  - Según la categoría del ticket, verificar que la tab correcta tenga datos y que las tabs incorrectas no hayan sido llenadas.
  - Mapeo: CE = tab Clientes Externos; AI + Servicios Internos = tab Áreas Internas; Control de Activos = tab Control de Activos; Control de Envíos = tab Control de Envíos.
- **Datos necesarios de GLPI:** tablas del plugin Additional Fields.

### 6.9 `IdsTabRule` (key: `ids_tab`)

- **Manual ref:** Parte 3.7.5 - Tab IDS
- **KPI mapping:** KPI-3 (complementa completitud)
- **Severidad:** warning
- **Lógica:**
  - Verificar que la tab IDS tenga los campos Nombre y Número de empleado llenos.
  - **Excepción:** categoría Control de Activos no requiere IDS.
  - Campos vacíos en categorías que sí requieren IDS = desviación.
- **Datos necesarios de GLPI:** tabla del plugin Additional Fields para IDS.

### 6.10 `ExternalIdRule` (key: `external_id`)

- **Manual ref:** Parte 3.3 - ID Externo
- **KPI mapping:** null
- **Severidad:** info
- **Lógica:**
  - Campo vacío = desviación (siempre debe tener algo: el número del cliente, `NO PROPORCIONADO` o `NO APLICA`).
  - Para CE: verificar que no diga `NO APLICA` (los CE siempre tienen o deberían tener un número de cliente; lo correcto es `NO PROPORCIONADO` si no lo dieron).
  - Para categorías internas: verificar que no diga `NO PROPORCIONADO` si la categoría es interna pura (lo correcto es `NO APLICA`).
- **Datos necesarios de GLPI:** campo del plugin Additional Fields para ID Externo (es un campo custom, no nativo de GLPI).

---

## 7. Servicio principal: `AuditRunnerService`

Orquesta la ejecución de una auditoría:

1. Recibe parámetros: `period_start`, `period_end`, `agent_glpi_user_id` (opcional, null = todos).
2. Crea un registro en `helpdesk_supervisor_audit_runs` con status `running`.
3. Obtiene la lista de agentes a auditar: los usuarios de Nexus que tengan `glpi_user_id` no nulo. Si se pasó un agente específico, solo ese.
4. Por cada agente, consulta los tickets del período en GLPI donde el agente sea el solicitante (`users_id_recipient`) o el creador (`users_id_lastupdater` en la creación).
5. Por cada ticket, ejecuta todas las reglas (`RuleInterface::evaluate()`).
6. Almacena las desviaciones en `helpdesk_supervisor_deviations`.
7. Actualiza el run con totales y status `completed` (o `failed` con mensaje si algo falló).

### `GlpiAuditQueryService`

Servicio dedicado a construir las consultas a GLPI. Encapsula:

- Obtener tickets por período y agente.
- Obtener datos de tabs custom (plugin Additional Fields) por ticket.
- Obtener logs de cambios por ticket.
- Obtener seguimientos, tareas y soluciones por ticket.
- Obtener asignaciones por ticket.

Reutiliza la conexión de `GlpiDbConnection` (o su propia conexión si `glpi_db_reuse_provisioning` = 0).

---

## 8. Rutas

### Web

```
GET  /helpdesk-supervisor                         -> Dashboard
GET  /helpdesk-supervisor/agents/{nexusUserId}    -> Detalle por agente
GET  /helpdesk-supervisor/rules/{ruleKey}         -> Detalle por regla
POST /helpdesk-supervisor/audit/run               -> Ejecutar auditoría
GET  /helpdesk-supervisor/audit/runs              -> Historial de auditorías
GET  /helpdesk-supervisor/audit/runs/{id}         -> Detalle de un run

GET  /helpdesk-supervisor/escalations             -> Lista de escalaciones
GET  /helpdesk-supervisor/escalations/create      -> Formulario nueva escalación
POST /helpdesk-supervisor/escalations             -> Guardar escalación
GET  /helpdesk-supervisor/escalations/{id}/edit   -> Editar escalación
PUT  /helpdesk-supervisor/escalations/{id}        -> Actualizar escalación
DELETE /helpdesk-supervisor/escalations/{id}       -> Eliminar escalación

GET  /helpdesk-supervisor/settings                -> Configuración del módulo
POST /helpdesk-supervisor/settings                -> Guardar configuración
POST /helpdesk-supervisor/settings/test-connection -> Probar conexión a GLPI
```

### API (bajo `/api/v1/helpdesk-supervisor/`)

Mismas rutas, JSON envelope estándar. El endpoint de auditoría es especialmente útil para automatización futura.

Filtros: `AuthFilter` + `ModuleAccessFilter` con key `helpdesk_supervisor`.

---

## 9. Pantallas

### 9.1 Dashboard (`/helpdesk-supervisor`)

**Sección superior: controles**
- Selector de período (fecha inicio / fecha fin, default: mes en curso).
- Botón "Ejecutar auditoría" que dispara el POST a `/audit/run`.
- Si ya hay un run del período, muestra la fecha/hora y botón para re-ejecutar.

**Sección de métricas globales (cards):**
- Total de tickets auditados.
- Total de desviaciones encontradas.
- Porcentaje de cumplimiento global: `(tickets_sin_desviacion / total_tickets) x 100`.
- Agentes auditados.

**Ranking de agentes (tabla):**

| Agente | Tickets | Desviaciones | Cumplimiento | Críticas | Warnings | Detalle |
|---|---|---|---|---|---|---|
| Nombre del agente | 45 | 8 | 82% | 2 | 6 | [Ver] |

Ordenado por desviaciones descendente (quien más falla arriba). Link "Ver" lleva al detalle del agente.

**Top reglas incumplidas (tabla o barras):**

| Regla | Incumplimientos | % del total |
|---|---|---|
| Completitud de campos | 15 | 35% |
| Título mal formado | 10 | 23% |

### 9.2 Detalle por agente (`/agents/{id}`)

**Encabezado:** nombre del agente, período, métricas individuales (tickets, desviaciones, cumplimiento, escalaciones del mes).

**Tabla de desviaciones:**

| Ticket | Regla | Campo | Esperado | Encontrado | Severidad | Ref. manual |
|---|---|---|---|---|---|---|
| #1234 - AFIRME - ... | Título mal formado | Título | AFIRME - SUC - DESC | afirme - suc - desc | Warning | Parte 3.3 |

Con filtros por regla y severidad. El número de ticket es link a GLPI (construido como `{glpi_base_url}/front/ticket.form.php?id={glpi_ticket_id}`).

**Sección de escalaciones del agente:** lista de escalaciones registradas para ese agente en el período, con opción de agregar nueva.

### 9.3 Detalle por regla (`/rules/{ruleKey}`)

Muestra todas las desviaciones de una regla específica, agrupadas por agente. Útil para ver si un problema es generalizado o de un agente.

### 9.4 Escalaciones (`/escalations`)

CRUD estándar. Formulario con:
- Ticket GLPI (número, con búsqueda).
- Agente (selector de agentes mapeados).
- Fecha de la escalación.
- Motivo (texto).
- Reportado por (texto libre).
- Período (año/mes, default: mes en curso).

### 9.5 Configuración (`/settings`)

- Conexión a GLPI: toggle "Reutilizar conexión de Provisioning" o campos propios (host, puerto, BD, usuario, contraseña).
- Botón "Probar conexión".
- Parámetro de días hábiles para abandono.

---

## 10. Seeder

`HelpdeskSupervisorModuleSeeder`:

1. Insertar registro en `modules` con key `helpdesk_supervisor`, name "Supervisor de Mesa", route_base `helpdesk-supervisor`, is_active 1.
2. Asignar al rol SuperAdmin en `role_module`.
3. Insertar los settings por defecto en `helpdesk_supervisor_settings`.
4. Insertar el mapeo de coordinadores por estado en `helpdesk_supervisor_coordinator_map` (los 32 estados con sus coordinadores, según la tabla de la Parte 3.5 del manual).

---

## 11. Sidebar

Agregar entrada "Supervisor de Mesa" en la navegación, con ícono apropiado (sugerido: `clipboard-check` o `shield-check`), visible solo para usuarios con acceso al módulo.

---

## 12. Consideraciones técnicas

- **Performance:** la auditoría puede procesar cientos de tickets. Las consultas a GLPI deben ser batch (traer todos los tickets del período de una vez, con JOINs a las tablas de Additional Fields), no ticket por ticket.
- **Idempotencia:** si se re-ejecuta la auditoría del mismo período, se crea un nuevo run y se generan nuevas desviaciones. Los runs anteriores se conservan para historial. No se borran desviaciones de runs previos.
- **Tablas de GLPI que se leen (referencia):**
  - `glpi_tickets` - datos base del ticket
  - `glpi_itilcategories` - categorías
  - `glpi_tickets_users` - relación ticket-usuario (solicitante, asignado, observador)
  - `glpi_logs` - historial de cambios
  - `glpi_itilfollowups` - seguimientos
  - `glpi_tickettasks` - tareas
  - `glpi_itilsolutions` - soluciones
  - Tablas del plugin Additional Fields (varían por contenedor; descubrir con `GlpiSchemaIntrospector` o hardcodear las conocidas)
- **Encoding:** los datos de GLPI vienen en UTF-8. Los títulos se comparan en mayúsculas usando `mb_strtoupper()`.
- **Zona horaria:** respetar la zona horaria de la instancia GLPI para el cálculo de días hábiles.

---

## 13. Entregables de la Fase 1

- [ ] Migración de `glpi_user_id` en tabla `users` (Core).
- [ ] UI de edición de usuario con campo `glpi_user_id`.
- [ ] Todas las migraciones del módulo HelpdeskSupervisor (5 tablas + coordinator_map).
- [ ] Seeder del módulo.
- [ ] Registro del namespace en `Autoload.php`.
- [ ] 10 reglas de validación implementadas.
- [ ] `AuditRunnerService` y `GlpiAuditQueryService`.
- [ ] Rutas web y API.
- [ ] 5 pantallas (dashboard, detalle agente, detalle regla, escalaciones CRUD, settings).
- [ ] Botón "Probar conexión" en settings.
- [ ] Entrada en sidebar.
- [ ] Agregar seeder a `setup.sh` y `public/setup.php`.
- [ ] Verificar con `php spark db:verify-schema`.
- [ ] Endpoints API en Postman collection.

---

*Fin de la Fase 1.*
