# MailDispatch

Capa de despacho (dispatcher) sobre el buzón compartido de Microsoft 365 de la
mesa de ayuda. Mantiene en Nexus una copia **viva de solo lectura** del buzón,
agrupada por **conversaciones** (hilos), donde cada conversación es un elemento
de trabajo asignable con estados, bitácora, disposición de cierre y referencia
opcional a un folio GLPI.

- **Key del módulo:** `mail_dispatch`
- **Rutas web:** `/dispatch/*` · **API:** `/api/v1/dispatch/*` · **Admin:** `/admin/dispatch/*`
- **UI:** "Despacho de Correo" · **Prefijo de tablas:** `maildispatch_`

Principio rector (Fase 1–2): **Nexus lee el buzón, nunca lo modifica.** Los
agentes responden desde Outlook. Si Nexus falla, la operación sigue igual que
hoy. En Fase 3 se habilita responder al hilo desde Nexus.

El backend de lectura es intercambiable mediante un **selector de proveedor** en
la configuración: **Microsoft Graph** (permisos de aplicación) o **IMAP** (un
buzón que recibe todo por regla de reenvío). Ver «Proveedores» abajo.

---

## Arquitectura

| Pieza | Rol |
|---|---|
| `GraphMailService` | Token client-credentials (cacheado), delta queries sobre Inbox/Sent, prueba de conexión y reply (F3). No lee BD ni `.env`; recibe credenciales inyectadas. |
| `MailboxSyncService` | Orquesta la sincronización delta por carpeta (Inbox + Enviados), idempotente y resumible. Registra estado y corridas. |
| `ConversationService` | Núcleo: ingesta con hilado (conversationId + fallback In-Reply-To/References), dirección in/out, máquina de estados, **claim atómico**, asignación, cierre, reapertura y bitácora. |
| `MailDispatchMetrics` | Analítica de solo lectura para el tablero (F2) y export CSV. |
| `BusinessCalendar` | Reloj del SLA: convierte cualquier par de fechas a **minutos hábiles** según el horario de servicio y las excepciones. Ver "Horario de servicio" abajo. |
| `ReplyService` | Envío de respuesta al hilo vía Graph (F3), gated por el toggle de admin. |
| `MailDispatchSettings` | Accesor tipado sobre `maildispatch_settings`; el secret se cifra con `CredentialCipher`. |

Comando de sincronización (cron, cada 1–2 min sugerido):

```bash
php spark maildispatch:sync-mailbox           # incremental (delta)
php spark maildispatch:sync-mailbox --full     # resincronización completa
php spark maildispatch:sync-mailbox --debug    # progreso por carpeta
```

```cron
*/2 * * * * cd /ruta/al/proyecto && php spark maildispatch:sync-mailbox >> /dev/null 2>&1
```

---

## Proveedores (Graph / IMAP)

La configuración (Administración → Despacho de Correo) tiene un selector **Tipo de
conexión**. El resto del módulo (bandeja, hilado, máquina de estados, métricas,
API) es idéntico para ambos; solo cambia el backend de lectura/envío.

| | Microsoft Graph | IMAP |
|---|---|---|
| Lectura | Delta queries sobre Inbox + Enviados del buzón compartido. | UID fetch incremental sobre una carpeta (por defecto `INBOX`) de una cuenta que recibe **todo** por regla de reenvío (entrantes y copia de las respuestas de los agentes). |
| Detección de dirección | Carpeta Enviados o `From == buzón`. | `From == dirección de la mesa de ayuda` (por eso la copia de las respuestas debe reenviarse a la cuenta IMAP). |
| Hilado | `conversationId` de Graph + fallback `In-Reply-To`/`References`. | Solo `In-Reply-To`/`References` (IMAP no tiene id de hilo); mensajes sin referencias abren hilo nuevo con su `Message-ID`. |
| Envío (Fase 3) | Acción `/reply` de Graph. | SMTP con cabeceras `In-Reply-To`/`References` para mantener el hilo. |
| Requisito | App Registration + `Mail.Read`/`Mail.Send` + Application Access Policy. | Cuenta IMAP + (para responder) credenciales SMTP. Sin permisos de tenant. |

**Motivación del modo IMAP:** operar sin esperar la aprobación de la App de
Microsoft (permisos de lectura de todos los buzones). Se apunta a un buzón que
recibe todo por reenvío; cuando se otorguen los permisos, se puede cambiar el
selector a Graph sin perder datos.

**Librería:** `webklex/php-imap` (PHP puro; **no** requiere la extensión
`ext-imap`, así que no hay cambios de infraestructura en host ni Docker).

**Cursor incremental:** `maildispatch_sync_state.delta_link` guarda
`UIDVALIDITY:<v>;UID:<lastUid>` por (buzón, `imap`). Si cambia el `UIDVALIDITY`
(la carpeta se reconstruyó en el servidor), el cursor se reinicia a un barrido
completo. Cada corrida lee páginas acotadas server-side (`UID next:*` + `limit`),
así que solo descarga los cuerpos de los mensajes nuevos. El `\Seen` nunca se
modifica (`leaveUnread`).

> Sugerencia: habilita la sincronización IMAP sobre una cuenta **nueva** (recién
> creada para el reenvío). Enchufarla a un buzón con un histórico enorme hace un
> primer barrido grande (idempotente, pero pesado).

El comando de sincronización es el mismo; despacha al backend según el proveedor:

```bash
php spark maildispatch:sync-mailbox           # usa Graph o IMAP según configuración
```

---

## Hilado y agrupación de duplicados

El buzón suele estar copiado en cadenas de correo, y los **reenvíos** (`RV:`/`FW:`)
llegan con un `In-Reply-To` que apunta a message-ids externos que no tenemos,
por lo que se partían en varias conversaciones del mismo hilo.

**Solución — solapamiento de referencias:** cada mensaje guarda sus *tokens* de
hilo (su propio `Message-ID` + cada id de `In-Reply-To` y `References`) en
`maildispatch_message_refs`. Al ingerir un correo sin `conversationId` de Graph,
si **cualquiera de sus tokens** ya existe contra otro mensaje, se **anexa** a esa
conversación. Como los message-ids son únicos, el riesgo de agrupar de más es
mínimo (no hay ventana de tiempo). Si la conversación ya está **asignada**, el
nuevo correo la pasa a "esperando agente" y el agente asignado lo ve.

**Fusión de duplicados ya existentes** (una vez, tras habilitar esto):

```bash
php spark maildispatch:merge-threads --dry-run   # previsualiza
php spark maildispatch:merge-threads             # aplica
```

Agrupa por referencias compartidas y fusiona cada grupo en una superviviente
(la **asignada** más antigua; si ninguna, la más antigua). La superviviente
conserva su agente; los agentes de las otras quedan anotados en la bitácora.
Mueve mensajes, eventos y adjuntos, y reabre la superviviente si estaba cerrada.

---

## Adjuntos

Los correos entrantes se ingieren **con** sus adjuntos y los agentes pueden
**adjuntar archivos en la respuesta**.

- **Tabla `maildispatch_attachments`** (metadatos) + archivos en disco bajo
  `WRITEPATH/maildispatch/attachments/{message_id}/` (ignorado en git).
- **Ingesta:** `ImapMailService` extrae cada adjunto (nombre, tipo, tamaño,
  contenido, content-id, inline) y `ConversationService` los persiste al crear el
  mensaje. `AttachmentService` centraliza almacenamiento y validación. (Graph:
  pendiente en fase B.)
- **Ver/descargar:** ruta autenticada `GET /dispatch/attachments/{id}` (+ espejo
  API). Tipos seguros (imágenes, PDF, texto) se sirven `inline`; el resto y las
  extensiones peligrosas se fuerzan a descarga (`X-Content-Type-Options: nosniff`,
  guarda anti path-traversal).
- **Imágenes embebidas (cid:):** en el detalle, las referencias `cid:` del HTML se
  reescriben a la URL autenticada del adjunto, así se ven dentro del iframe. Los
  adjuntos **no** referenciados por `cid:` se listan como *chips* descargables.
- **Responder con adjuntos:** el form es `multipart/form-data`; en modo IMAP se
  envían por SMTP (`Email::attach`), se guardan en el hilo como mensaje saliente.
- **Límites** (en `Config/MailDispatch`): 25 MB por respuesta, 15 archivos,
  extensiones ejecutables bloqueadas.

---

## Máquina de estados

```
nueva → asignada → en_atencion → respondida ⇄ esperando_agente → cerrada
```

- Un mensaje **entrante** con `conversationId` nuevo crea una conversación en `nueva`.
- Un mensaje con `conversationId` ya registrado se **anexa** al hilo (nunca crea un elemento nuevo).
- Fallback de hilado: `In-Reply-To`/`References` → un `internetMessageId` ya almacenado.
- Si el remitente es el **propio buzón** (respuesta desde Outlook, carpeta Enviados) → la conversación pasa sola a `respondida` y se registra la primera respuesta.
- Un mensaje entrante sobre una conversación `respondida`/`en_atencion` → `esperando_agente`.
- Una conversación `cerrada` que recibe un entrante se **reabre** en `esperando_agente` conservando su asignación.
- **Claim atómico:** tomar una conversación es un `UPDATE ... WHERE agent_id IS NULL`; si dos agentes la toman a la vez, solo uno gana y el otro ve "ya fue tomada por X".

---

## Búsqueda (incluye el cuerpo del correo)

El buscador de la bandeja cubre asunto, solicitante, correo, folio GLPI **y el
contenido de los mensajes**. Las dos mitades funcionan distinto a propósito:

| Campo | Cómo busca |
|---|---|
| Asunto, solicitante, correo, folio | `LIKE` por coincidencia parcial en cualquier posición. Tabla chica, sin costo. |
| Cuerpo del mensaje | Índice `FULLTEXT` sobre `body_text`, por palabra completa con comodín al final. |

**Por qué no se busca sobre `body`.** El cuerpo guardado es HTML de Outlook: 85 KB
promedio, de los cuales apenas una quinta parte es contenido. Un `LIKE` sobre esa
columna no puede usar índice (escanea la tabla entera en cada búsqueda, decenas
de GB en un buzón real) y además encontraría coincidencias dentro del CSS y del
markup. Por eso se indexa `body_text`, la proyección en texto plano que produce
`ForwardParser::plainText()`, recortada a 64 000 caracteres por mensaje.

`MessageModel` la deriva en un callback `beforeInsert`, así que la escriben por
igual la ingesta y los dos servicios de respuesta, sin que ninguna ruta pueda
olvidarla.

**Lo que cambia para el agente:** en el cuerpo se buscan palabras completas.
`pinpad` encuentra `pinpads` (comodín al final) pero no existe comodín al inicio,
y los términos de menos de 3 letras se ignoran (`innodb_ft_min_token_size`).
Varias palabras se combinan con AND: escribir más términos acota. Es insensible a
mayúsculas y acentos por la colación de la columna. En asunto y solicitante no
cambia nada. Una búsqueda se resuelve como máximo a 2 000 conversaciones; más
allá de eso conviene acotar el término.

Cuando la coincidencia viene del cuerpo y no se ve en la fila, la bandeja muestra
el extracto con el término resaltado, para que el agente sepa por qué salió.

### Backfill (mensajes anteriores)

Los mensajes nuevos traen su texto desde la ingesta; los ya almacenados se
procesan con:

```bash
php spark maildispatch:backfill-body-text                      # todo, lotes de 200
php spark maildispatch:backfill-body-text --batch 100 --sleep 250   # más suave en producción
php spark maildispatch:backfill-body-text --limit 1000         # una probada corta
php spark maildispatch:backfill-body-text --dry-run            # medir sin escribir
```

Es idempotente y reanudable: solo toma filas con `body_text` en NULL, así que
interrumpirlo no pierde nada. Mientras corre, la búsqueda por cuerpo solo
encuentra lo ya procesado; la búsqueda por asunto y solicitante funciona normal.

> **Orden en producción:** detén el cron de `maildispatch:sync-mailbox`, corre la
> migración (el primer índice FULLTEXT reconstruye la tabla y no admite
> escrituras concurrentes), vuelve a encender el cron y corre el backfill con
> `--sleep` mientras el equipo trabaja. Detalle completo en el encabezado de
> `2026-08-13-110001_AddBodyTextSearchToMessages`.

---

## Horario de servicio (reloj del SLA)

Los umbrales de SLA (Administración → Despacho de Correo → **Conexión**) se
cuentan por omisión a reloj corrido. Con la pestaña **Horario** el conteo se
limita al horario real de la mesa, de modo que un correo que llega el viernes a
las 18:55 no consume su SLA durante el fin de semana.

| Concepto | Dónde vive |
|---|---|
| Interruptor + horario semanal | `maildispatch_settings`: `business_hours_enabled`, `business_hours_schedule` (JSON, 1 = lunes … 7 = domingo) |
| Festivos y cierres puntuales | Tabla `maildispatch_business_exceptions` (una fila por fecha: cerrado todo el día u horario reducido) |
| Cálculo | `BusinessCalendar` |

`BusinessCalendar` expone dos operaciones y con ellas se resuelve todo:

- `elapsedMinutes($desde)`: minutos hábiles consumidos. Lo usan el tablero de
  equipo y sus fichas por hilo.
- `cutoff($minutos)`: el instante de reloj tal que `received_at < cutoff`
  equivale a "ya excedió $minutos hábiles". Permite que la consulta de
  incumplimientos y el semáforo de la bandeja sigan siendo una comparación de
  fechas indexada, sin recorrer el calendario fila por fila.

Con el interruptor apagado ambas funciones devuelven minutos de reloj corrido,
es decir el comportamiento previo a la existencia del calendario.

Alcance del calendario cuando está activo: semáforo SLA de la bandeja, espera de
la bandeja sin asignar, "Fuera de SLA" y medidores del tablero de equipo, y los
promedios de primera asignación / primera respuesta en Métricas. No altera
fechas almacenadas: `received_at`, `first_response_at` y compañía siguen siendo
instantes reales; solo cambia cómo se mide la distancia entre ellos.

API espejo (SuperAdmin): `GET|POST /api/v1/admin/dispatch/schedule`.

---

## Prerrequisito de infraestructura (Microsoft 365 / Entra ID)

MailDispatch usa **permisos de aplicación** (client credentials). El
administrador de M365 debe:

1. **Crear una App Registration** en Entra ID y un **client secret**.
2. Conceder permisos de aplicación de Microsoft Graph y otorgar consentimiento de administrador:
   - `Mail.Read` (Fase 1–2)
   - `Mail.Send` (Fase 3, para responder desde Nexus)
3. **Restringir el acceso al buzón de la mesa de ayuda** con una *Application Access Policy* en Exchange Online, para que la app **no** pueda leer otros buzones.

Luego, en Nexus → Administración → Configuración → **Despacho de Correo**, capturar
Tenant ID, Client ID, Client Secret (se guarda cifrado) y la dirección del buzón,
y usar **Probar conexión** antes de habilitar la sincronización.

### Application Access Policy (PowerShell de referencia)

```powershell
# Conéctate a Exchange Online
Connect-ExchangeOnline

# 1) Grupo de seguridad con correo que contiene SOLO el buzón de mesa de ayuda
New-DistributionGroup -Name "MailDispatch-Scope" -Type Security `
  -PrimarySmtpAddress maildispatch-scope@tudominio.com
Add-DistributionGroupMember -Identity "MailDispatch-Scope" `
  -Member mesadeayuda@tudominio.com

# 2) Restringe la App (usa el Application/Client ID de la App Registration)
New-ApplicationAccessPolicy `
  -AppId "<CLIENT_ID>" `
  -PolicyScopeGroupId "maildispatch-scope@tudominio.com" `
  -AccessRight RestrictAccess `
  -Description "Nexus MailDispatch: solo buzón mesa de ayuda"

# 3) Verifica el alcance
Test-ApplicationAccessPolicy -Identity mesadeayuda@tudominio.com -AppId "<CLIENT_ID>"
Test-ApplicationAccessPolicy -Identity otrousuario@tudominio.com  -AppId "<CLIENT_ID>"  # debe DENEGAR
```

> La política puede tardar hasta ~30 min en propagarse.

---

## Fases

- **Fase 1 — Sincronización y despacho:** bandeja con filtros (Sin asignar / Mías / Todas / Cerradas), detalle de hilo, claim/asignación/reasignación, cambio de estado, cierre con disposición + folio, notas internas, bitácora, y API espejo. **Implementada.**
- **Fase 2 — Métricas:** tablero (backlog, tiempos promedio, volumen por agente, disposiciones, volumen diario), alertas de SLA en la bandeja, export CSV, API espejo. **Implementada.**
- **Fase 3 — Respuesta desde Nexus:** reply al hilo vía Graph desde el buzón compartido, catálogo de plantillas, toggle global en admin. **Implementada** (deshabilitada por defecto; requiere `Mail.Send`).

### Nota sobre Fase 3

El envío usa la acción `/reply` de Graph (mantiene el hilo). El mensaje saliente
se registra de inmediato en la conversación; la copia real en *Enviados* llega en
el siguiente sync con su propio id de Graph (registro duplicado benigno,
diferenciable porque el inmediato lleva `graph_id` con prefijo `nexus:`).

---

## Seguridad

- Ninguna credencial de Graph vive en `.env`: todo se edita desde la UI y el secret se guarda **cifrado**.
- El secret nunca se muestra en claro ni se registra en logs.
- Toda la configuración está restringida a **SuperAdmin**; el área operativa exige acceso al módulo `mail_dispatch` y estar registrado como agente para poder tomar/asignar.
