# MailDispatch — Pendientes por concluir

> Documento de referencia del estado del módulo **MailDispatch** al momento de
> hacer commit del avance. El scope completo está en
> [`modulo-maildispatch.md`](../modulos/maildispatch/spec.md). Aquí solo se registra lo
> que **queda pendiente** para dar el módulo por terminado.
>
> **Fecha de corte:** 2026-07-28

---

## 1. Resumen del estado

El código de las **tres fases** (F1 sincronización/despacho, F2 métricas, F3
respuesta desde Nexus) está **escrito, migrado a nivel de definición y con API
espejo + Postman actualizados**. El módulo NO está terminado porque **no se ha
podido verificar en vivo contra Microsoft 365**: la cuenta / App Registration aún
no tiene concedidos los permisos de aplicación ni la *Application Access Policy*,
por lo que ninguna llamada real a Graph (token, delta sync, prueba de conexión,
reply) ha sido ejercitada.

En otras palabras: el bloqueo es de **infraestructura/permisos M365**, no de
lógica. Aun así quedan algunos huecos de código y de convención, listados abajo.

---

## 2. Bloqueador principal: permisos de Microsoft 365

Hasta que el administrador de M365 no complete el prerrequisito documentado en
`app/Modules/MailDispatch/README.md`, nada de lo siguiente puede validarse:

- [ ] **App Registration en Entra ID** con client secret.
- [ ] Permisos de aplicación de Graph con consentimiento de administrador:
      `Mail.Read` (F1–F2) y `Mail.Send` (F3).
- [ ] **Application Access Policy** en Exchange Online restringida SOLO al buzón
      de la mesa de ayuda (comandos PowerShell de referencia en el README).

Una vez concedidos, hay que **verificar en vivo** (todo el código existe, falta
ejercitarlo):

- [ ] Obtención y cacheo del token client-credentials (`GraphMailService::token`).
- [ ] Botón **Probar conexión** del admin (`GraphMailService::testConnection`).
- [ ] `php spark maildispatch:sync-mailbox` — delta sync de Inbox + Enviados,
      persistencia y reuso del `deltaLink`, idempotencia (correrlo dos veces no
      duplica), y `--full`.
- [ ] **Criterio de aceptación de Fase 1** completo (ver §8 del scope): dos
      correos nuevos → dos conversaciones; claim atómico con el aviso "ya fue
      tomada por X" al segundo agente; respuesta desde Outlook → pasa sola a
      `respondida` en el siguiente sync; réplica del usuario → `esperando_agente`;
      cierre con disposición "Ticket GLPI" capturando folio; ningún hilo
      existente reaparece como nuevo.
- [ ] **Fase 3** en vivo: `ReplyService::reply` envía al hilo vía Graph `/reply`,
      registra el saliente sintético (`graph_id` con prefijo `nexus:`) y marca
      `respondida` sin esperar sync.

---

## 3. Pendiente por PROGRAMAR (huecos de código reales)

### 3.1 Listado de nombres de adjuntos — sin implementar
El scope (§5) pide: *"los adjuntos no se descargan en fase 1; solo se indica su
existencia y se listan por nombre si es viable."* Hoy solo se guarda el **flag**
`has_attachments`. La columna `attachment_names` (TEXT) ya existe en la migración
`...100005_CreateMailDispatchMessagesTable.php:35` y en `MessageModel`, pero:

- `GraphMailService::initialDeltaUrl()` no pide `attachments` en el `$select`.
- `ConversationService::extract()` nunca puebla `attachment_names`.

**Pendiente:** obtener los nombres de adjuntos (p. ej. expandir
`attachments($select=name)` por mensaje con adjuntos, o `$expand`) y persistirlos,
o bien decidir explícitamente dejarlo fuera de alcance y quitar la columna.

### 3.2 Convención de tipografía en frontend (violaciones)
Regla del proyecto: **sin em-dashes (—) ni emojis** en el frontend (usar `:`,
`·`, `-` o íconos SVG).

- [ ] `Views/show.php:60` usa el emoji 📎 para "con adjuntos" → reemplazar por
      ícono SVG o texto/`·`.
- [ ] **13 ocurrencias de em-dash `—`** repartidas en `show.php`, `inbox.php`,
      `metrics.php` y `admin/settings.php` (varias como placeholder de valor
      vacío `?? '—'`) → sustituir por `-` / `·`.
- [ ] `Views/show.php:202` usa `→`; validar contra la convención (preferir SVG o
      un separador permitido).

---

## 4. Pendiente por VERIFICAR / OPERAR (no es código, pero cierra el módulo)

- [ ] Aplicar migraciones en el entorno destino: `php spark migrate --all`.
- [ ] `php spark db:verify-schema` en verde (las 9 tablas `maildispatch_`).
- [ ] Ejecutar los seeders (`MailDispatchModuleSeeder`) — ya están en `setup.sh`
      y `public/setup.php`.
- [ ] Alta del cron de `maildispatch:sync-mailbox` en el servidor (entrada ya
      documentada en `docs/operacion/cronjobs.md`).
- [ ] Confirmar visibilidad del módulo en el sidebar y acceso por rol
      (`mail_dispatch`) y la sección de admin restringida a SuperAdmin.

---

## 5. Limitaciones conocidas / deuda técnica (aceptadas por ahora)

- **Duplicado benigno en F3:** al responder desde Nexus se inserta un mensaje
  saliente sintético (`nexus:*`) y, en el siguiente sync, llega la copia real de
  *Enviados* con su propio `graph_id`. El hilo mostrará dos salientes. Documentado
  en el README; evaluar si se de-duplica (p. ej. casando por asunto+timestamp) o
  se deja como está.
- **Token cacheado solo por proceso:** `GraphMailService` cachea el token en
  memoria durante la corrida; no hay refresh proactivo si expira a mitad de una
  corrida muy larga (el token de Graph dura ~60–75 min y el sync es corto, así que
  en la práctica no debería alcanzarse; revisar si se observa en producción).
- **Adjuntos no se descargan** (por diseño de F1): solo existencia; ver §3.1 para
  el listado por nombre.

---

## 6. Cambios incluidos en este commit (contexto)

Archivos tocados fuera del módulo, todos necesarios para registrarlo:

- `app/Config/Autoload.php` — namespace `App\Modules\MailDispatch`.
- `app/Config/Routes.php` — carga de las rutas del módulo.
- `app/Config/Services.php` — factories de servicios (`graphMailService`,
  `mailDispatchSettings`, `conversationService`, `mailDispatchMetrics`, etc.).
- `app/Modules/Core/Views/partials/sidebar.php` — entrada "Despacho de Correo".
- `docs/referencia/tt-apps.postman_collection.json` — endpoints `/api/v1/dispatch/*`.
- `public/setup.php` y `setup.sh` — alta del `MailDispatchModuleSeeder`.
- `docs/operacion/cronjobs.md` — cron de `maildispatch:sync-mailbox`.

> Los cambios en `app/Modules/ServiceDesk/*` presentes en el árbol de trabajo son
> ajenos a MailDispatch; revisarlos por separado antes del commit si no se quieren
> incluir.
</content>
</invoke>
