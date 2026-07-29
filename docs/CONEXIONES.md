# Conexiones a sistemas externos (estado actual)

> **Propósito.** Este documento describe **qué integraciones existen hoy** en tt-nexus y **en qué modo** se conectan: protocolo, tipo de autenticación, dónde se guarda la configuración y si está cifrada. Sirve como punto de partida para planear nuevas integraciones (por ejemplo, para pasar contexto a Claude Desktop).
>
> **Alcance.** No se listan rutas ni endpoints concretos de cada API. El foco es el inventario de sistemas disponibles y su modo de conexión. Para el contrato detallado de la Intranet ver `app/Modules/Provisioning/README.md`; para Mailcow ver `docs/mailcow.md`; para MailDispatch ver `docs/modulo-maildispatch.md`.
>
> Última actualización: 2026-07-28.

---

## 1. Modelo general de conexiones

Antes del inventario, tres patrones que se repiten en todas las integraciones:

**1.1. La configuración vive en base de datos, no en `.env`.**
Cada módulo que se conecta a un sistema externo guarda su configuración (URL, usuario, tokens, opciones) en una **tabla de settings tipo llave-valor** propia del módulo, editable desde el panel de administración (rol SuperAdmin). El `.env` solo aporta la **llave maestra de cifrado**; los secretos operativos se administran desde la UI.

**1.2. Los secretos se cifran en reposo (AES).**
Las credenciales sensibles (contraseñas, API keys, client secrets) se almacenan **cifradas** en la columna correspondiente. Se usan tres llaves de cifrado según el módulo:

| Llave (variable en `.env`) | Qué cifra | Módulos |
|---|---|---|
| `encryption.key` (llave maestra de CI4, vía `CredentialCipher`) | Credenciales de Provisioning, password de la BD GLPI, secreto de Graph, API key de Claude | Provisioning, MailDispatch, ServiceDesk |
| `MAILBOXES_ENCRYPTION_KEY` (dedicada) | URL y API key de Mailcow | Mailboxes |
| `APP_SETTINGS_ENCRYPTION_KEY` (dedicada) | Usuario y contraseña SMTP | Core |

Los valores cifrados nunca se devuelven a la UI: los formularios muestran un indicador tipo "(definida)" y un campo vacío al guardar significa "conservar el valor actual".

**1.3. Casi todas las integraciones tienen "Probar conexión".**
Los paneles de configuración incluyen un botón que valida la conexión en vivo (ping, versión, token OAuth, etc.) antes de habilitar el sistema. Las excepciones se indican en cada sección.

---

## 2. Inventario de sistemas conectados

Resumen de un vistazo:

| Sistema | Modo de conexión | Autenticación | Config / cifrado | Módulos que lo consumen |
|---|---|---|---|---|
| **GLPI** | BD directa (MySQL/MariaDB) + API REST + plugin Additional Fields (vía BD) | Usuario BD / App-Token + user_token (o Basic) | `provisioning_settings` + `provisioning_system_credentials` (cifrado con `encryption.key`) | Provisioning, ServiceDesk, KPIsOperativos |
| **Mailcow** | API REST | Cabecera `X-API-Key` | `mailboxes_settings` (cifrado con `MAILBOXES_ENCRYPTION_KEY`) | Mailboxes, Provisioning (reúso) |
| **Intranet** | API REST | Bearer token | `provisioning_systems` + `provisioning_system_credentials` (cifrado con `encryption.key`) | Provisioning |
| **Microsoft 365 (Graph)** | API REST (Graph v1.0) | OAuth2 client credentials | `maildispatch_settings` (secreto cifrado con `encryption.key`) | MailDispatch |
| **SMTP (correo saliente)** | Protocolo SMTP | Host/puerto/usuario/contraseña + TLS/SSL | `core_app_settings` (cifrado con `APP_SETTINGS_ENCRYPTION_KEY`), con respaldo a `.env` | Communications (reutilizado por Provisioning, Core, ServiceDesk) |
| **Anthropic (Claude API)** | API REST (Messages) | API key en cabecera | `servicedesk_settings` (cifrado con `encryption.key`) | ServiceDesk (creador IA + widget) |

Las secciones siguientes detallan cada uno.

---

### 2.1. GLPI

Es la integración más rica: se conecta de **tres maneras complementarias**, más una vía de importación offline.

**a) Conexión directa a la base de datos GLPI (MySQL/MariaDB).**
La forma principal de leer y escribir en GLPI. La conexión se construye en tiempo de ejecución (no es un grupo estático de `Config/Database.php`), por lo que el host puede variar por despliegue. En desarrollo apunta a la instancia GLPI local (GLPI 11).

- **Config almacenada en:** `provisioning_settings` (llaves `glpi_db_host`, `glpi_db_port`, `glpi_db_name`, `glpi_db_user`, `glpi_db_password`, `glpi_db_enabled`).
- **Cifrado:** solo `glpi_db_password`, con `encryption.key`.
- **Autenticación:** usuario/contraseña de la BD.
- **Probar conexión:** sí. Verifica conectividad y la presencia de tablas del plugin Additional Fields.
- **Servicio base:** `Provisioning/Services/GlpiDbConnection.php`.

**b) API REST de GLPI (`apirest.php`).**
Usada para operaciones de identidad (crear/activar/desactivar usuarios, rotar contraseñas) y creación de tickets.

- **Config almacenada en:** `provisioning_systems` (registro `glpi`: base_url, `auth_type`, opciones JSON) + `provisioning_system_credentials`.
- **Credenciales:** `app_token` (siempre) y `user_token` (preferido) o, como respaldo, `api_username` + `api_password`. Todas cifradas con `encryption.key`.
- **Autenticación:** sesión en dos pasos (initSession con App-Token + user_token/Basic → Session-Token → killSession).
- **Opciones:** `default_profile_id` (por defecto 4, Self-Service) y `default_entity_id` (por defecto 0, raíz).
- **Probar conexión:** sí (initSession → getFullSession → killSession).
- **Servicio base:** `Provisioning/Connectors/GlpiConnector.php`.

**c) Plugin Additional Fields (introspección + escritura vía BD directa).**
El módulo ServiceDesk descubre en vivo los "contenedores" y campos adicionales configurados en GLPI y escribe en sus tablas al importar tickets. Reutiliza la conexión directa (a).

- **Descubrimiento dinámico:** lee `glpi_plugin_fields_containers` y `glpi_plugin_fields_fields` y deriva las columnas físicas por convención del plugin.
- **Escritura:** CRUD de catálogos dropdown (`glpi_plugin_fields_*dropdowns`, con mantenimiento del árbol: completename, nivel, cachés) y filas de contenedor por ticket.
- **Servicios base:** `ServiceDesk/Services/GlpiSchemaIntrospector.php`, `TicketBulkImporter.php`, y `Provisioning/Services/GlpiCatalogService.php`.

**d) Importación de archivos CSV/XLSX (offline, KPIsOperativos).**
No es una conexión viva a GLPI: el módulo KPIsOperativos parsea **exportaciones** de GLPI (CSV/XLSX) y las carga en tablas locales (`kpi_glpi_*`) para calcular KPIs. Sin credenciales ni red.

> **Regla de oro (catálogos IDS):** los nombres de catálogos solo se cambian desde Nexus, nunca directamente en GLPI, para no romper la sincronización.

---

### 2.2. Mailcow

Servidor de correo. Administración de buzones vía API.

- **Modo:** API REST (`{base_url}/api/v1/...`).
- **Autenticación:** cabecera `X-API-Key`. La API key debe ser **Read-Write** para las operaciones de escritura; la IP del servidor Nexus debe estar en la allowlist de Mailcow.
- **Config almacenada en:** `mailboxes_settings` (llaves `mailcow_url`, `mailcow_api_key`), ambas cifradas con `MAILBOXES_ENCRYPTION_KEY`.
- **Operaciones:** listar/ver buzones y dominios, crear, editar (nombre, cuota, contraseña, estado), activar/desactivar, eliminar, consultar versión, resolver dirección disponible.
- **Probar conexión:** sí (consulta la versión de Mailcow).
- **Dueño y reúso:** el módulo **Mailboxes** es el dueño del cliente y la configuración. **Provisioning lo reutiliza** sin modificarlo, a través de `MailcowConnector`, en modo `mailcow_settings_reuse` (toma la URL y API key de Mailboxes). Existe un modo alterno `mailcow_own_credentials` para usar credenciales propias de Provisioning.
- **Servicios base:** `Mailboxes/Libraries/MailcowApi.php`, `Mailboxes/Services/MailboxesService.php`, `Provisioning/Connectors/MailcowConnector.php`.

> En el flujo de alta de empleados, Mailcow se aprovisiona **primero**: el buzón institucional se convierte en la llave de correo usada por GLPI e Intranet.

---

### 2.3. Intranet

Intranet corporativa. Nexus es el **cliente**; la Intranet expone la API que Nexus consume para crear/administrar cuentas de login de empleados.

- **Modo:** API REST (JSON sobre HTTPS).
- **Autenticación:** Bearer token (`Authorization: Bearer <api_key>`).
- **Config almacenada en:** `provisioning_systems` (registro `intranet`: base_url) + `provisioning_system_credentials` (credencial `api_key`, cifrada con `encryption.key`).
- **Operaciones:** crear usuario, deshabilitar (soft-disable, nunca borra), habilitar + rotar contraseña, cambiar contraseña, actualizar datos (nombre, número de empleado, correo, foto), ping.
- **Probar conexión:** sí (ping).
- **Contrato:** la Intranet separa "empleado" (nodo del organigrama, no inicia sesión) de "usuario" (cuenta de login). La llave de recurso es `nexus_id` (ej. `NX-42`). Detalle completo en `Provisioning/README.md`.
- **Dueño:** exclusivo de Provisioning (sin reúso cross-module). Servicio base: `Provisioning/Connectors/IntranetConnector.php`.

---

### 2.4. Microsoft 365 / Graph

Acceso a buzón compartido de M365 para el módulo MailDispatch (despacho de correo compartido).

- **Modo:** Microsoft Graph API REST v1.0 (`https://graph.microsoft.com/v1.0`).
- **Autenticación:** OAuth2 **client credentials** (aplicación, sin consentimiento de usuario). Token en `https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token`, scope `https://graph.microsoft.com/.default`.
- **Config almacenada en:** `maildispatch_settings` (llaves `graph_tenant_id`, `graph_client_id`, `graph_client_secret`, `mailbox_address`, más flags de sincronización y SLA). Solo `graph_client_secret` se cifra (con `encryption.key`).
- **Operaciones:** obtención de token, sincronización delta incremental de Bandeja de entrada y Enviados, listado paginado de mensajes, envío de respuestas dentro del hilo. Estado de sync persistido en `maildispatch_sync_state` y ejecuciones en `maildispatch_sync_runs`.
- **Interruptores:** `sync_enabled` (activa/desactiva la sincronización) y `send_from_nexus_enabled` (permite o no responder desde Nexus, modo solo-lectura).
- **Probar conexión:** sí (obtiene token OAuth y consulta metadatos de la bandeja).
- **Servicios base:** `MailDispatch/Services/GraphMailService.php`, `MailboxSyncService.php`, `ReplyService.php`.

> **Nota:** `Provisioning/Services/MsLicenseService.php` administra un **catálogo local** de licencias M365 (SKUs como E5, Business Standard) en la tabla `provisioning_ms_licenses`. **No llama a Graph ni a Azure**; es solo referencia interna para el aprovisionamiento.

---

### 2.5. SMTP (correo saliente)

Envío de correo (campañas de Communications, restablecimiento de contraseña, correo de bienvenida de Provisioning, reporte de backlog de ServiceDesk, notificaciones).

- **Modo:** protocolo SMTP directo (clase Email de CI4).
- **Autenticación / parámetros:** host, puerto (por defecto 587), usuario, contraseña y cifrado (`tls` o `ssl`), más remitente (from email/name).
- **Config almacenada en:** `core_app_settings` (llaves `smtp_host`, `smtp_port`, `smtp_crypto`, `smtp_user`, `smtp_password`, `smtp_from_email`, `smtp_from_name`). Se cifran `smtp_user` y `smtp_password` con `APP_SETTINGS_ENCRYPTION_KEY`.
- **Respaldo a `.env`:** si la BD no tiene configuración, se cae a `app/Config/Email.php`, que lee las variables `email.SMTPHost`, `email.SMTPUser`, `email.SMTPPass`, `email.SMTPPort`, `email.SMTPCrypto`, `email.fromEmail`, `email.fromName`. **La UI de administración tiene prioridad** sobre el `.env`.
- **Cola:** los envíos masivos se encolan y procesan por lotes con `php spark comms:process-queue` (parámetros `COMMS_BATCH_SIZE`, `COMMS_THROTTLE_SECONDS`).
- **Probar conexión:** sí (envía un correo de prueba a la dirección remitente configurada).
- **Dueño y reúso:** el `MailerService` de **Communications** es el punto único de envío; otros módulos lo reutilizan como servicio (nunca instancian SMTP por su cuenta).
- **Servicios base:** `Communications/Services/MailerService.php`, `Core/Services/AppSettingsService.php`.

---

### 2.6. Anthropic (Claude API)

Funciones de IA del módulo ServiceDesk: el creador de tickets asistido por operador y el widget/landing público de autoservicio.

- **Modo:** API REST de Mensajes de Anthropic (SDK `anthropic-ai/sdk`).
- **Autenticación:** API key en cabecera.
- **Config almacenada en:** `servicedesk_settings` (llave `ai_api_key` cifrada con `encryption.key`; en texto plano: `ai_model`, `ai_system_prompt`, `ai_max_tickets_per_request`, `ai_daily_token_budget`, y el toggle de habilitación).
- **Modelos configurables:** `claude-haiku-4-5` (por defecto), `claude-sonnet-5`, `claude-opus-4-8`.
- **Usos:**
  - **Creador IA (operador):** conversación multi-turno que recopila datos y propone de 1 a N filas de ticket (structured output), revisables en una tabla antes de encolarlas al importador. Tope 4096 tokens por petición; tickets forzados a estado "EN CURSO".
  - **Widget / landing público:** chat que crea **un** ticket nuevo de forma síncrona en GLPI. Tope 2048 tokens por petición; tickets forzados a estado "NUEVO".
- **Presupuesto y auditoría:** límite diario de tokens configurable; cada turno se registra en `servicedesk_ai_usage` (tokens de entrada/salida, caché, tickets propuestos/creados, costo estimado).
- **Probar conexión:** **no** existe (aún) un botón de validación de la API key en la configuración.
- **Servicios base:** `ServiceDesk/Services/TicketCreatorService.php`, `WidgetTicketService.php`, `ServiceDeskSettings.php`.

---

## 3. Variables de entorno relevantes

Las conexiones dependen de estas variables en `.env` (los secretos operativos viven en BD, no aquí):

| Variable | Rol |
|---|---|
| `encryption.key` | Llave maestra de CI4. Cifra credenciales de Provisioning (GLPI API, Intranet, password BD GLPI), secreto de Graph (MailDispatch) y API key de Claude (ServiceDesk). |
| `MAILBOXES_ENCRYPTION_KEY` | Cifra la URL y API key de Mailcow (`mailboxes_settings`). |
| `APP_SETTINGS_ENCRYPTION_KEY` | Cifra usuario y contraseña SMTP (`core_app_settings`). |
| `email.*` (SMTPHost, SMTPUser, SMTPPass, SMTPPort, SMTPCrypto, fromEmail, fromName) | Respaldo de SMTP cuando la BD no tiene configuración. |
| `COMMS_BATCH_SIZE`, `COMMS_THROTTLE_SECONDS` | Ritmo de la cola de correo. |

> Todas las llamadas salientes viajan por HTTPS con verificación SSL activa. Ninguna credencial se guarda en texto plano en la BD ni se escribe en logs.

---

## 4. Tablas de configuración y operación (referencia rápida)

| Tabla | Sistema | Contenido |
|---|---|---|
| `provisioning_systems` | GLPI, Mailcow, Intranet | Registro por sistema: base_url, auth_type, opciones, activo. |
| `provisioning_system_credentials` | GLPI, Mailcow, Intranet | Credenciales por sistema (cifradas). |
| `provisioning_settings` | GLPI (BD directa), correo de bienvenida | Config BD GLPI + textos del correo de bienvenida. |
| `provisioning_external_accounts` | Todos (Provisioning) | Registro de qué cuenta existe en qué sistema por empleado. |
| `provisioning_log`, `provisioning_retry_queue` | Provisioning | Auditoría y reintentos de operaciones idempotentes. |
| `mailboxes_settings` | Mailcow | URL y API key (cifradas). |
| `maildispatch_settings` | Microsoft Graph | Tenant, client id/secret, buzón, flags de sync/SLA. |
| `core_app_settings` | SMTP | Host, puerto, usuario/contraseña (cifrados), remitente. |
| `servicedesk_settings` | Claude API | API key (cifrada), modelo, prompt, topes. |
| `servicedesk_ai_usage` | Claude API | Consumo de tokens y costo estimado por turno. |

---

## 5. Cómo aprovisiona Provisioning (orquestación)

El módulo Provisioning es el coordinador central del ciclo de vida de identidades sobre **GLPI, Mailcow e Intranet**. Puntos clave para planear nuevas integraciones que dependan de él:

- `ConnectorFactory` instancia el conector correcto por llave de sistema (`glpi`, `mailcow`, `intranet`), lee y descifra las credenciales y las inyecta.
- `AccessOrchestrator` coordina alta, baja, cambio de contraseña, reactivación y sincronización de perfil a través de los sistemas seleccionados.
- **No hay transacción distribuida:** una falla en un sistema no revierte los demás; las fallas quedan visibles y reintentables (cola de reintentos vía cron, solo para operaciones idempotentes como deshabilitar o actualizar).
- **Mailcow siempre primero:** el correo institucional resultante es la llave usada por GLPI e Intranet (nunca se usa correo personal).
- Todo queda registrado en `provisioning_log`.

Para el contrato completo de la Intranet y el detalle del orquestador, ver `app/Modules/Provisioning/README.md`.
