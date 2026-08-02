# Fase 2: Notificaciones IA y envío de reportes

> **Documento:** Especificación técnica para Claude Code  
> **Módulo:** HelpdeskSupervisor (extensión)  
> **Fase:** 2 de 3  
> **Prerrequisitos:** Fase 1 completada y funcional. Leer `NEXUS.md` y `CONEXIONES.md`.

---

## 1. Objetivo

Agregar al módulo HelpdeskSupervisor la capacidad de:

1. Generar un borrador de correo por agente usando Claude API (Haiku), redactado a nombre del supervisor, con el resumen de desviaciones y acciones correctivas.
2. Permitir al supervisor revisar y editar el borrador antes de enviar.
3. Adjuntar un archivo Excel con el detalle de desviaciones del agente.
4. Enviar el correo vía el SMTP ya configurado (reutilizando `MailerService` de Communications).

La IA no analiza tickets ni decide qué es desviación. Las desviaciones ya están calculadas (Fase 1). La IA solo redacta el correo con los datos que el módulo le pasa.

---

## 2. Dependencias a reutilizar

| Componente | Dueño | Uso |
|---|---|---|
| `MailerService` | Communications | Envío SMTP. Se invoca como servicio. |
| Claude API (Anthropic SDK) | ServiceDesk | Llamada a Haiku para redacción. Se reutiliza la config de `servicedesk_settings` (api key, o se agrega config propia). |
| `AppSettingsService` | Core | Lectura de configuración SMTP. |
| PhpSpreadsheet | (dependencia Composer) | Generación del archivo Excel adjunto. Verificar si ya está instalado en el proyecto; si no, agregarlo. |

---

## 3. Tablas adicionales

### 3.1 `helpdesk_supervisor_notifications`

Registro de cada notificación generada y/o enviada.

| Columna | Tipo | Descripción |
|---|---|---|
| id | INT UNSIGNED AI PK | |
| audit_run_id | INT UNSIGNED FK | Auditoría de la que provienen los datos |
| glpi_user_id | INT UNSIGNED | Agente destinatario (GLPI ID) |
| nexus_user_id | INT UNSIGNED NULL | Agente destinatario (Nexus ID) |
| agent_name | VARCHAR(150) | Nombre del agente |
| agent_email | VARCHAR(255) | Email del agente al que se enviará |
| period_start | DATE | Período auditado (inicio) |
| period_end | DATE | Período auditado (fin) |
| total_deviations | INT UNSIGNED | Total de desviaciones incluidas |
| ai_draft_body | TEXT NULL | Borrador HTML generado por IA |
| final_body | TEXT NULL | Cuerpo final (editado por el supervisor o igual al draft) |
| excel_path | VARCHAR(500) NULL | Ruta del archivo Excel generado |
| status | ENUM('draft','ready','sent','failed') DEFAULT 'draft' | |
| sent_at | DATETIME NULL | Fecha/hora de envío |
| sent_by_user_id | INT UNSIGNED NULL | Usuario Nexus que dio enviar |
| error_message | TEXT NULL | Si status = failed |
| ai_tokens_input | INT UNSIGNED DEFAULT 0 | Tokens de entrada consumidos |
| ai_tokens_output | INT UNSIGNED DEFAULT 0 | Tokens de salida consumidos |
| created_at | DATETIME | |
| updated_at | DATETIME | |

### 3.2 Settings adicionales en `helpdesk_supervisor_settings`

| Key | Valor por defecto | Cifrado | Descripción |
|---|---|---|---|
| `ai_api_key` | (vacío) | Sí | API key de Anthropic. Si vacío, reutiliza `servicedesk_settings.ai_api_key`. |
| `ai_api_key_reuse_servicedesk` | 1 | No | Si es 1, reutiliza la API key de ServiceDesk. |
| `ai_model` | claude-haiku-4-5 | No | Modelo a usar. Solo Haiku para mantener costo bajo. |
| `ai_max_tokens` | 2048 | No | Tokens máximos por generación. |
| `notification_sender_name` | (vacío) | No | Nombre del remitente en el correo. Si vacío, usa el nombre del usuario logueado. |
| `notification_sender_email` | (vacío) | No | Email del remitente. Si vacío, usa el from del SMTP. |
| `notification_cc` | (vacío) | No | CC opcional para todas las notificaciones (ej. el propio supervisor). |

---

## 4. Flujo detallado

### Paso 1: el supervisor inicia la notificación

Desde la vista de detalle por agente (`/helpdesk-supervisor/agents/{id}`), hay un botón **"Preparar notificación"**. También puede haber una acción masiva desde el dashboard para preparar notificaciones de todos los agentes con desviaciones.

Al presionar, el sistema:

1. Recopila todas las desviaciones del agente en el run activo del período.
2. Si no hay desviaciones, muestra aviso y no genera nada.
3. Si hay desviaciones, pasa al Paso 2.

### Paso 2: generación del borrador con IA

El sistema llama a Claude API (Haiku) con el siguiente prompt:

```
System prompt:
Eres un asistente que redacta correos profesionales en español para el Gerente
de Service Desk de Trantor Technologies. Tu tarea es redactar un correo dirigido
a un agente de mesa de ayuda informándole sobre las desviaciones encontradas en
su operación de tickets en GLPI durante el período indicado.

El correo debe:
- Ser profesional, directo y constructivo. No es un regaño; es una notificación
  con el objetivo de que el agente corrija y mejore.
- Incluir un saludo con el nombre del agente.
- Resumir las desviaciones agrupadas por tipo de regla.
- Para cada grupo, indicar cuántas ocurrencias hubo y dar un ejemplo concreto
  (con número de ticket).
- Incluir la acción correctiva esperada, referenciando la sección del manual
  que aplica.
- Cerrar indicando que se adjunta un archivo Excel con el detalle completo.
- Firmar a nombre del supervisor (nombre proporcionado).
- No usar emojis ni el símbolo de raya em. Usar guion simple o dos puntos.
- Formato HTML sencillo (párrafos, listas, negritas). Sin imágenes.

User message:
Redacta el correo con los siguientes datos:

Agente: {agent_name}
Período: {period_start} a {period_end}
Total de tickets auditados: {total_tickets}
Total de desviaciones: {total_deviations}
Firma del remitente: {supervisor_name}, {supervisor_title}

Desviaciones por regla:
{deviations_grouped_json}
```

Donde `{deviations_grouped_json}` es un JSON con la estructura:

```json
[
  {
    "rule_name": "Título mal formado",
    "rule_key": "title_format",
    "manual_reference": "Parte 3.3 - Título",
    "count": 5,
    "severity": "warning",
    "examples": [
      {
        "ticket_id": 1234,
        "ticket_title": "afirme - heb guadalupe livas - falla",
        "field_affected": "Título",
        "expected": "AFIRME - HEB GUADALUPE LIVAS - FALLA...",
        "actual": "afirme - heb guadalupe livas - falla",
        "detail": "Título no está en mayúsculas"
      }
    ]
  }
]
```

Se incluyen máximo 3 ejemplos por regla para no inflar el prompt. El Excel tiene el detalle completo.

### Paso 3: generación del Excel

Simultáneamente (o justo antes), el sistema genera un archivo Excel con PhpSpreadsheet:

**Hoja 1: Resumen**

| Dato | Valor |
|---|---|
| Agente | Nombre del agente |
| Período | dd/mm/yyyy a dd/mm/yyyy |
| Tickets auditados | N |
| Desviaciones encontradas | N |
| Cumplimiento | XX% |

Tabla resumen por regla:

| Regla | Ocurrencias | Severidad | Referencia del manual |
|---|---|---|---|
| Título mal formado | 5 | Warning | Parte 3.3 |
| Completitud de campos | 3 | Crítica | Parte 3.7 |

**Hoja 2: Detalle de desviaciones**

| # | Ticket GLPI | Título del ticket | Regla | Campo afectado | Valor esperado | Valor encontrado | Severidad | Ref. manual | KPI |
|---|---|---|---|---|---|---|---|---|---|
| 1 | 1234 | AFIRME - HEB... | Título mal formado | Título | MAYÚSCULAS | minúsculas | Warning | Parte 3.3 | - |

El archivo se guarda en `writable/uploads/helpdesk_supervisor/notifications/` con nombre `desviaciones_{agent_name}_{period}.xlsx`.

### Paso 4: revisión del borrador

El sistema muestra la pantalla de revisión:

- **Destinatario:** email del agente (editable).
- **CC:** si está configurado, pre-llenado (editable).
- **Asunto:** pre-llenado con "Reporte de desviaciones - {período} - {agent_name}" (editable).
- **Cuerpo del correo:** editor HTML con el borrador de Haiku. El supervisor puede editar libremente.
- **Adjunto:** nombre del archivo Excel (no editable, ya generado).
- **Botones:** "Enviar", "Regenerar borrador" (vuelve a llamar a Haiku), "Descartar".

### Paso 5: envío

Al presionar "Enviar":

1. El sistema guarda el `final_body` (lo que quedó después de la edición).
2. Llama a `MailerService` para enviar el correo con:
   - From: el nombre/email configurado o del supervisor logueado.
   - To: email del agente.
   - CC: si está configurado.
   - Subject: el asunto.
   - Body: HTML del `final_body`.
   - Attachment: el archivo Excel.
3. Actualiza el status a `sent` con fecha/hora, o `failed` con error.

### Paso 6 (opcional): envío masivo

Desde el dashboard, botón **"Preparar notificaciones masivas"**:

1. Genera borradores para todos los agentes con desviaciones en el período.
2. Muestra una lista de borradores generados con preview resumido.
3. El supervisor puede revisar cada uno individualmente o enviar todos de un golpe con un botón "Enviar todos".
4. Cada envío individual sigue el mismo flujo.

---

## 5. Nuevas rutas

### Web

```
POST /helpdesk-supervisor/notifications/prepare/{nexusUserId}     -> Generar borrador para un agente
POST /helpdesk-supervisor/notifications/prepare-all               -> Generar borradores masivos
GET  /helpdesk-supervisor/notifications/{id}/review               -> Pantalla de revisión del borrador
POST /helpdesk-supervisor/notifications/{id}/regenerate           -> Regenerar borrador con IA
POST /helpdesk-supervisor/notifications/{id}/send                 -> Enviar correo
DELETE /helpdesk-supervisor/notifications/{id}                     -> Descartar
GET  /helpdesk-supervisor/notifications                            -> Historial de notificaciones
POST /helpdesk-supervisor/notifications/send-all                  -> Enviar todos los pendientes
```

### API

Mismas rutas bajo `/api/v1/helpdesk-supervisor/notifications/`.

---

## 6. Nuevos archivos

```
app/Modules/HelpdeskSupervisor/
  Services/
    NotificationDraftService.php      # Llama a Claude API, genera borrador
    NotificationExcelService.php      # Genera el Excel con PhpSpreadsheet
    NotificationSenderService.php     # Orquesta envío vía MailerService
  Controllers/
    Notifications.php                 # Controller web
    Api/
      NotificationsApiController.php  # Controller API
  Views/
    notifications/
      review.php                      # Pantalla de revisión/edición del borrador
      index.php                       # Historial de notificaciones
      prepare_all.php                 # Vista masiva
```

---

## 7. Servicio `NotificationDraftService`

```php
class NotificationDraftService
{
    /**
     * Genera el borrador del correo usando Claude API.
     *
     * @param int $auditRunId
     * @param int $nexusUserId  (del agente)
     * @return array ['draft_html' => string, 'tokens_input' => int, 'tokens_output' => int]
     */
    public function generateDraft(int $auditRunId, int $nexusUserId): array;
}
```

Internamente:

1. Lee las desviaciones del agente en ese run.
2. Las agrupa por `rule_key`.
3. Selecciona hasta 3 ejemplos por regla.
4. Construye el prompt (system + user).
5. Llama a la API de Anthropic con el modelo configurado.
6. Extrae el HTML de la respuesta.
7. Registra tokens consumidos.

**Manejo de errores:**
- Si la API falla, el sistema permite redactar el correo manualmente (el campo de edición queda vacío con un mensaje).
- Si la API key no está configurada (ni propia ni de ServiceDesk), muestra error en la pantalla de settings.

---

## 8. Servicio `NotificationExcelService`

```php
class NotificationExcelService
{
    /**
     * Genera el archivo Excel con las desviaciones del agente.
     *
     * @param int $auditRunId
     * @param int $nexusUserId
     * @return string  Ruta del archivo generado
     */
    public function generateExcel(int $auditRunId, int $nexusUserId): string;
}
```

**Formato del Excel:**
- Fuente Calibri, texto negro, sin colores de fondo en encabezados.
- Tablas con bordes simples en gris claro.
- Sin sombreados ni bloques de color.
- Columnas con ancho auto-ajustado.
- Encabezados en negrita, sin fondo de color.

---

## 9. Consideraciones

- **Costo de IA:** Haiku es el modelo más económico. Con ~2048 tokens por correo y 5 agentes, el costo mensual es mínimo. Se registra el consumo en la tabla de notificaciones para seguimiento.
- **No duplicar envíos:** antes de generar, verificar si ya existe una notificación `sent` para ese agente, run y período. Si existe, advertir al supervisor (puede enviar de nuevo si quiere, pero conscientemente).
- **Email del agente:** se necesita el email del agente. Opciones: (a) leerlo de la tabla `users` de Nexus si tiene email, (b) leerlo de `glpi_users.name` o campos de GLPI si está mapeado, (c) campo `agent_email` en la tabla de notificaciones, editable manualmente. La opción (a) es la más limpia. Si el usuario de Nexus no tiene email, se pide al supervisor que lo capture.
- **Adjuntos en MailerService:** verificar que `MailerService` soporte adjuntos. La clase Email de CI4 soporta `$email->attach($filepath)`. Si `MailerService` no expone ese método, agregar un parámetro opcional `$attachments` al método de envío sin modificar la firma existente (sobrecarga o nuevo método `sendMailWithAttachments`).

---

## 10. Pantalla de settings (extensión)

Agregar a la pantalla de settings de Fase 1:

**Sección "Notificaciones IA":**
- Toggle "Reutilizar API key de ServiceDesk" (default: sí).
- Campo API key propia (cifrada, solo si toggle = no).
- Modelo (selector: `claude-haiku-4-5` recomendado).
- Nombre del remitente para correos.
- Email del remitente (o "usar SMTP default").
- CC por defecto.

---

## 11. Entregables de la Fase 2

- [ ] Migración para tabla `helpdesk_supervisor_notifications`.
- [ ] Migración para settings adicionales.
- [ ] `NotificationDraftService` con integración a Claude API.
- [ ] `NotificationExcelService` con PhpSpreadsheet.
- [ ] `NotificationSenderService` con integración a `MailerService`.
- [ ] Controller y vistas de notificaciones (review, historial, masivo).
- [ ] Rutas web y API.
- [ ] Botón "Preparar notificación" en vista de detalle por agente.
- [ ] Botón "Preparar notificaciones masivas" en dashboard.
- [ ] Extensión de settings (sección IA).
- [ ] Soporte de adjuntos en `MailerService` si no existe.
- [ ] Endpoints API en Postman collection.

---

*Fin de la Fase 2.*
