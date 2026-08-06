# MailDispatch — Modo Autogestión (auto-creación de tickets GLPI)

> Diseño. Estado: **DEFINICIÓN** (2026-08-05). Aún no construido.
> Objetivo: que un correo entrante con cierta forma dispare, por reglas, la creación
> automática de un ticket en GLPI, responda al remitente con el folio vía el SMTP del
> módulo, y quede en una bandeja "autogenerados" para que un agente lo verifique
> (mismo patrón que autocierre).

---

## 1. Decisiones cerradas (con el usuario)

1. **Ejecución en 2 pasos con worker diferido y reintentos.** El sync solo clasifica;
   un comando cron crea el ticket y responde. Aísla fallos de GLPI/SMTP.
2. **Rename `autocierre` → `autoarchivo`** (Fase 0), con migración de datos en vivo.
   El nombre "autocierre" queda libre para otro uso futuro.
3. **Config del destino por regla, con defaults globales** (tipo de ticket, categoría,
   entidad, solicitante, contenedores).
4. **Solicitante = ID de usuario GLPI fijo** (por regla, con default global). No hay
   resolución por email en esta fase.
5. **Lista blanca obligatoria:** un correo dispara solo si el remitente (post-ForwardParser)
   —o el destinatario en modo reenvío— está en la lista blanca de la regla.
6. **Datos faltantes → bandeja "requiere revisión", sin crear ticket.**
7. **Formato del cuerpo (Fase 1, sin IA): `Campo: valor` por línea.**
8. **IA = opción global aparte (Fase 2)** que afina detección de intención y extracción.

---

## 2. Componentes reutilizados (nada se reinventa)

| Necesidad | Símbolo existente | Módulo |
|---|---|---|
| Crear 1 ticket síncrono | `TicketBulkImporter::createOne($containerIds,$row,$opts)` → `data['ticketId']` (`service('serviceDeskImporter')`) | ServiceDesk |
| Ejemplo de llamada mínima | `WidgetTicketService::createTicket()` | ServiceDesk |
| Catálogos (categorías/tabs) | `GlpiSchemaIntrospector` (`categories()`, `containerOptions()`, `buildPlan()`); tipos en `Config\ServiceDesk::$ticketTypes` | ServiceDesk |
| Responder por SMTP del módulo | `SmtpReplyService::reply()` (reenvío/IMAP) / `ReplyService::reply()` (Graph) | MailDispatch |
| Embudo de ingest / disparo | `ConversationService::createConversation()` + `matchRule()` | MailDispatch |
| Remitente real en reenvío | `ForwardParser` | MailDispatch |
| Settings KV + toggles | `maildispatch_settings` + `MailDispatchSettings` (patrón `treat_as_forwards`) | MailDispatch |
| Reglas + editor admin | `maildispatch_rules` + `RuleModel` + `MailDispatchSettings::saveRules()` | MailDispatch |
| Bandeja/categoría/pill | `Dispatch::FILTERS`, `ConversationModel::forQueue/counts`, `Views/inbox.php` tabs+pill | MailDispatch |
| IA por tool-use | patrón de `TicketCreatorService` (SDK Anthropic directo, `askTool/proposeTool`) | ServiceDesk |
| Cifrado de secretos | `CredentialCipher` (requiere `encryption.key`) | Provisioning |

**Acoplamiento:** MailDispatch introducirá su **primera** llamada a ServiceDesk/GLPI.
Se encapsula en un único servicio nuevo `AutogenTicketService` (MailDispatch) que envuelve
`service('serviceDeskImporter')->createOne()` y `service('glpiSchemaIntrospector')`.
Cross-module solo vía servicios (regla de arquitectura del proyecto).

---

## 3. Modelo de datos

### 3.1 Nueva tabla `maildispatch_autogen_rules`
- `id`, `name` VARCHAR(150), `is_active` TINYINT default 1, `sort_order` INT default 0
- **Disparo:** `subject_pattern` VARCHAR(255), `subject_match_mode` ENUM('contains','exact') default 'contains', `recipient_pattern` VARCHAR(255) default '' (para disparo por destinatario en reenvío)
- **Destino GLPI (null = hereda default global):** `glpi_ticket_type` ENUM('INCIDENCIA','REQUERIMIENTO') null, `glpi_category_id` INT null, `glpi_entities_id` INT null, `glpi_requester_user_id` INT null, `request_source_id` INT null, `container_ids` VARCHAR(255) default '' (CSV)
- **Mapeo de campos:** `field_map` JSON — lista de `{label, target, required}` donde
  `target ∈ { 'title', 'description', 'plugin:<containerId>:<fieldKey>', 'ignore' }`.
  Aquí viven los "datos mínimos" que defina el usuario por regla.
- **Respuesta:** `reply_subject` VARCHAR(255), `reply_body` TEXT (con variables)
- **IA:** `ai_enabled` TINYINT default 0
- timestamps

### 3.2 Nueva tabla `maildispatch_autogen_whitelist`
- `id`, `rule_id` INT (FK → rules), `type` ENUM('sender','recipient') default 'sender',
  `value` VARCHAR(255) (correo completo o `@dominio`, mismo criterio que `matchRule`),
  `is_active` TINYINT default 1, timestamp
- **Obligatoria:** una regla sin whitelist activa nunca dispara.

### 3.3 Columnas nuevas en `maildispatch_conversations`
(vía migración nueva + agregar a `ConversationModel::allowedFields`)
- `autogen_rule_id` INT unsigned null
- `auto_ticket_id` INT unsigned null (id del ticket GLPI creado)
- `autogen_state` VARCHAR(20) null — `pending` | `created` | `review` | `failed`
- `autogen_payload` JSON null (campos parseados, para el worker y la vista de revisión)
- `autogen_attempts` INT default 0, `autogen_error` VARCHAR(255) null
- `autogen_reply_sent` TINYINT default 0
- Reutiliza `verified_by` / `verified_at` (ya existen por autocierre)

### 3.4 Enum de estado
- Migración raw (patrón `...130001`) que agrega `'autogenerado'` a
  `maildispatch_conversations.status`.
- Fase 0: renombrar `'autocierre'` → `'autoarchivo'` en el mismo enum (ver §8).

### 3.5 Nuevas settings en `maildispatch_settings`
Toggles (patrón `'0'/'1'`): `autogestion_enabled`, `autogestion_ai_enabled`.
Defaults globales: `autogen_default_ticket_type`, `autogen_default_category_id`,
`autogen_default_entities_id`, `autogen_default_requester_user_id`,
`autogen_default_request_source_id`, `autogen_default_container_ids`,
`autogen_system_user_id` (actor para eventos / `verified_by` / autor de la respuesta).
IA (Fase 2): `autogen_ai_model`, `autogen_ai_api_key` (cifrada; o reusar la de ServiceDesk
como hace HelpdeskSupervisor), `autogen_ai_system_prompt`, `autogen_ai_max_tokens`.

---

## 4. Contrato del correo (Fase 1, sin IA)

- **Asunto:** debe cumplir `subject_pattern` según `subject_match_mode`.
- **Cuerpo:** líneas `Campo: valor`. El parser recorre el `field_map` de la regla:
  por cada entrada busca la línea cuya etiqueta coincide (case-insensitive, tolerando
  espacios) y toma el valor.
- **Composición del ticket:** los valores con `target='title'` forman el `TITULO`
  (concatenados), los `target='description'` el `DESCRIPCION`, y los
  `target='plugin:...'` van a campos del contenedor. Si `field_map` no define título,
  se usa el asunto limpio; si no define descripción, el cuerpo completo.
- **Validación:** si falta algún `required` → `autogen_state='review'` (no se crea).

---

## 5. Máquina de estados y flujo

```
correo entra
   │  (SyncMailbox → MailboxSyncService/ImapSyncService → ConversationService::ingest → createConversation)
   ▼
¿match de regla autogen?  (asunto + whitelist obligatoria; o recipient_pattern en reenvío)
   │ no → flujo normal (o autocierre/autoarchivo)
   │ sí
   ▼
status='autogenerado'; guarda autogen_rule_id + autogen_payload (parseado)
   ├─ faltan required  → autogen_state='review'   (a bandeja, sin crear)
   └─ datos completos  → autogen_state='pending'
   ▼
WORKER  maildispatch:process-autogen   (cron)
   toma 'pending':
     1. si auto_ticket_id ya existe → salta creación (idempotente)
     2. AutogenTicketService.create(regla, payload) → createOne(...)
          éxito → set auto_ticket_id; sigue
          fallo → autogen_attempts++, autogen_error;  si attempts≥N → state='failed'
     3. si !autogen_reply_sent → responde por SMTP con la plantilla; set autogen_reply_sent=1
     4. state='created'
```

**Idempotencia:** el `Message-ID` ya deduplica en `ingest`. La creación solo ocurre en
`createConversation` (mensaje nuevo). Guardar `auto_ticket_id` **antes** de responder evita
recrear si el reply falla y se reintenta.

---

## 6. Reglas: matching y prioridad

- Reglas activas ordenadas por `sort_order, id` (como `RuleModel::active()`).
- Una regla hace match si: `subject` cumple el patrón **Y** el remitente
  (post-ForwardParser) está en su whitelist tipo `sender`; **o** el destinatario está en
  whitelist/`recipient_pattern` tipo `recipient` (caso reenvío).
- **Primera regla que hace match gana.** Se registra evento `autogenerado` con el nombre
  de la regla (como el `events->log(...,'autoclose',...)` de autocierre).

---

## 7. Creación del ticket (mapeo a `createOne`)

```php
$baseHeader = []; // de GlpiSchemaIntrospector::buildPlan([], false)
$row = [
  $baseHeader['name']              => $titulo,
  $baseHeader['content']           => $descripcion,
  $baseHeader['type']              => $regla->ticketType ?? $defaultType,   // INCIDENCIA/REQUERIMIENTO
  $baseHeader['status']            => 'NUEVO',
  $baseHeader['date']              => date('Y-m-d H:i:s'),
  $baseHeader['itilcategories_id'] => $categoryCompleteName, // resolver id→nombre
];
$res = service('serviceDeskImporter')->createOne($containerIds, $row, [
  'requester'       => $requesterGlpiUserId,   // fijo por regla/default
  'entities'        => $entitiesId,
  'requesttypes_id' => $requestSourceId,
]);
$ticketId = (int) ($res->data['ticketId'] ?? 0);
```
- La **categoría** se guarda como id GLPI en la regla y se resuelve a `completename`
  (createOne la resuelve por nombre).
- Campos de plugin (`container_ids`) se agregan al `$row` con sus headers de
  `buildPlan($ids, true)`.

---

## 8. Bandeja "autogenerados" (UI)

Mirror de autocierre:
- `Dispatch::FILTERS` += `'autogenerados'`.
- `ConversationModel::forQueue()` += `case 'autogenerado'`; y excluir `status !=
  'autogenerado'` en unassigned/mine/all (igual que autocierre). `counts()` con conteo
  accionable = `state in ('review','failed')` o (`created` y `verified_at IS NULL`).
- `Views/inbox.php`: nuevo tab + rama del pill:
  - `created` → muestra folio `#<ticketId>` + botón **Verificar** (reusa `verify`).
  - `review` → **Requiere revisión** + acción **Completar** (form con los campos
    faltantes → crear).
  - `failed` → **Error** + acción **Reintentar**.
- `show.php`/`preview.php`: banner del bucket + acciones.
- Rutas web + API espejo (como `verify`/`to-inbox`).

---

## 9. Rename `autocierre` → `autoarchivo` (Fase 0, aislada)

**Riesgo:** toca datos vivos de una feature recién commiteada. Se hace primero y se verifica.

Migración de datos (segura):
1. `ALTER` ampliando el enum para incluir **ambos** valores (`autocierre` y `autoarchivo`).
2. `UPDATE maildispatch_conversations SET status='autoarchivo' WHERE status='autocierre'`.
3. `ALTER` quitando `autocierre` del enum.

Código/UI a renombrar: `Config/MailDispatch.php` (`statusLabels`, `statusTones`,
`manualStatuses`), `ConversationModel::forQueue/counts`, `Dispatch::FILTERS`,
`Views/inbox.php`/`show.php`/`preview.php` (branch `status==='autocierre'`),
`ConversationService::verify()` (guard) y el evento `'autoclose'`. Las **reglas de
autocierre** (`maildispatch_rules`) siguen igual; solo cambia el nombre del estado/bucket.

---

## 10. Fase IA (opcional, Fase 2)

- Toggle global `autogestion_ai_enabled` + `ai_enabled` por regla.
- Cuando activa, en vez del parser `Campo: valor` se usa tool-use (SDK Anthropic directo,
  patrón `TicketCreatorService`): una tool con `inputSchema` derivado del `field_map`
  de la regla. Salida estructurada = campos + intención + confianza.
- Si confianza baja o falta `required` → `autogen_state='review'` (no crea).
- Costo/uso se puede loguear como `servicedesk_ai_usage`.

---

## 11. Seguridad / anti-abuso / idempotencia

- **Whitelist obligatoria** por regla → nadie externo dispara con solo saber la palabra clave.
- **Rate-limit por remitente** (opcional, config): máx N auto-tickets/hora por email.
- **Spoofing del From:** en modo reenvío el remitente real lo da ForwardParser; se
  recomienda confiar en el buzón de reenvío (correo ya autenticado por el relay).
- **Idempotencia:** dedupe por `Message-ID` (ya existe) + `auto_ticket_id` como guardia.
- **Actor sistema:** `autogen_system_user_id` para eventos y autoría de la respuesta.

---

## 12. Fases de entrega

- **Fase 0 — Rename autocierre → autoarchivo.** ✅ HECHA y verificada (2026-08-05).
  Migración `2026-08-06-100001_RenameAutocierreToAutoarchivo` (enum + bitácora) + rename en
  Config/Model/Controller/Service/Views/Routes. Enum final sin `autocierre`; bucket probado
  end-to-end; `db:verify-schema` OK.
- **Fase 1 — Autogestión estructurada (sin IA):**
  - 1a Backend: settings+toggle, tablas `autogen_rules`+`whitelist`, columnas de conversación,
    enum `autogenerado`, matcher en `createConversation`, parser `Campo: valor`,
    `AutogenTicketService`, worker `maildispatch:process-autogen`, plantilla de respuesta.
  - 1b UI: bandeja "autogenerados" (tab, pills, verificar/completar/reintentar) + editor de
    reglas admin (con pickers de tipo/categoría/entidad/contenedores desde ServiceDesk).
- **Fase 2 — IA opcional:** tool-use de extracción + confianza, cae a `review`.

**Criterios de aceptación Fase 1:** un correo con asunto y whitelist válidos crea el ticket,
responde con el folio por SMTP, aparece en "autogenerados" como `created` verificable; uno con
datos incompletos aparece como `review` sin crear; `db:verify-schema` OK.

---

## 13. Huecos — RESUELTOS (2026-08-05)

- **Datos mínimos (`field_map`):** configurable por regla (no hardcodeado). Se siembra una
  regla de ejemplo; el usuario define los suyos en el editor. NO bloquea el build.
- **Entidad:** campo de **id a mano** en el editor de reglas (ServiceDesk no enumera entidades).
- **"Completar" en revisión:** **formulario en la propia bandeja** de dispatch → crea el ticket
  ahí mismo reusando `AutogenTicketService`.
- **Reintentos del worker:** **máx. 3** con backoff creciente (~2/10/30 min); tras el 3.º →
  `failed` (reintento manual desde la bandeja).
- **Rate-limit:** **sí, en Fase 1**, configurable (N auto-tickets por remitente/hora, default
  generoso).
- **Verificar:** **marca verificado + link directo al ticket en GLPI** (no edita/reasigna).
