# Service Desk — Actualización y cierre masivo de tickets

> Estado: **CONSTRUIDO** (2026-08-18), F1 + F2 + F3. Sin commitear.
> Verificado en frío (21 pruebas de lógica pura, migraciones, rutas, DI y render de
> todas las pantallas). **Pendiente la prueba en vivo contra GLPI**: la BD de GLPI
> (MAMP, `host.docker.internal:8889`) estaba apagada al construir. Ver §11.
> Objetivo: subir el **mismo Excel del importador** pero con `TICKET_ID` lleno, para
> que el sistema **planche** en GLPI los valores que traiga la fila (categoría, tipo,
> asignado, campos del plugin) y, cuando `ESTATUS` sea `RESUELTO`/`CERRADO`, además
> **cierre** el ticket con solución.
> Caso de uso real: tickets generados con datos mínimos o con categoría equivocada,
> que llegan en un Excel con los datos correctos y la instrucción de cerrarlos.

Es una **extensión de ServiceDesk**, no un módulo nuevo. Reusa el template, el plan
de columnas, la tabla de trabajos, el worker y las vistas de historial.

---

## 1. Decisiones cerradas (con el usuario, 2026-08-18)

1. **Celda vacía = no tocar el campo.** Solo se escribe lo que venga lleno. Para borrar
   un valor mal puesto se usa el centinela explícito `[VACIAR]`. Esto es lo que permite
   subir un Excel con solo dos columnas corregidas.
2. **Cierre = `ITILSolution` + estatus.** Se crea la solución (GLPI mueve el ticket a
   Resuelto solo) y luego se fija el estatus final con `solvedate`/`closedate`. No se
   cierra con un `PUT` pelón: dejaría tickets cerrados sin solución y los reportes de
   GLPI los marcarían como anómalos.
3. **Tickets ya cerrados: reabrir → planchar → volver a cerrar.** GLPI bloquea la
   edición de cerrados. Se reabre a `EN CURSO`, se escribe y se re-cierra conservando
   la fecha de cierre original. Queda rastro en el historial del ticket.
4. **Notificaciones: se documenta, no se evade.** Nexus escribe vía API respetando
   reglas de negocio e historial. La pantalla advierte antes de encolar y la guía
   documenta cómo apagar notificaciones en GLPI durante el lote.
5. **`TICKET_ID` vacío = error de validación.** Este flujo nunca crea tickets; para
   crear ya está `/servicedesk`.
6. **Simulación obligatoria como opción.** Checkbox "Simular (no aplica cambios)" en
   la carga: corre el diff completo y entrega Excel y log de lo que *habría* cambiado.
   **Viene marcado por default.** Al terminar, el detalle ofrece **Aplicar en GLPI**, que
   reencola el MISMO archivo guardado con `dry_run = 0`: lo que se aplica es exactamente
   lo que se revisó y el operador no vuelve a subir nada. La simulación se conserva como
   evidencia.

---

## 2. Componentes reutilizados (nada se reinventa)

| Necesidad | Símbolo existente | Módulo |
|---|---|---|
| Mismo template / hoja `DATOS` / `_META` | `TicketTemplateBuilder::readMetaContainers()` | ServiceDesk |
| Mapeo columna Excel → campo GLPI/plugin | `GlpiSchemaIntrospector::buildPlan()` | ServiceDesk |
| Validación de fechas, números y catálogos | `TicketImportValidator` (se extiende con modo) | ServiceDesk |
| Normalización de celdas | `ValueParser::str()/date()/norm()/isEmpty()` | ServiceDesk |
| Tabla de trabajos, contadores, historial | `servicedesk_imports` + `ServiceDeskImportModel` | ServiceDesk |
| Worker + lock serializado | `servicedesk:process-imports` + `RunLock` | ServiceDesk |
| Vistas de historial / detalle / polling / descarga | `Views/imports.php`, `Views/show.php` | ServiceDesk |
| Sesión API de GLPI reusable en lote | `GlpiConnector::openApiSession()/closeApiSession()` | Provisioning |
| Precedente de solución en GLPI | `GlpiFieldService::addSolution()` (a subir al conector) | TechBot |
| Escritura directa a la BD de GLPI | `GlpiDbConnection::connection()` | Provisioning |
| Catálogos y autocreación de valores | `GlpiCatalogService::findByName()/ensureValue()` | Provisioning |

---

## 3. La regla de oro del archivo

- **`TICKET_ID` lleno** → la fila actualiza ese ticket. Vacío → error de validación.
- **Celda llena = se plancha. Celda vacía = no se toca.** `[VACIAR]` borra el valor.
- En modo `update` se **relajan los `required`** del template (hoy `TITULO`, `TIPO`,
  `ESTATUS`, `CATEGORIA`, `FECHA_APERTURA` son obligatorios). Solo `TICKET_ID` lo es.
- El archivo `*_con_tickets.xlsx` que ya produce el importador se puede reeditar y
  volver a subir sin tocar nada: el ciclo alta → corrección queda cerrado.

**Gotcha heredado:** `ValueParser::str()` devuelve `null` para `N/A`, `NAN` y `NONE`.
En modo `update` eso significa "no tocar", no "escribir el literal N/A". Se documenta
en la hoja `INSTRUCCIONES`.

---

## 4. Orden de operaciones por fila

El orden importa: GLPI bloquea la edición de tickets cerrados y las reglas de negocio
corren también en `update`.

1. **Leer el ticket vivo** (`GET /Ticket/{id}`): estatus, categoría, título, y la fila
   del contenedor del plugin. Sirve para diffear y para el log de auditoría.
2. **Si está cerrado y hay campos que planchar** → `PUT` estatus `EN CURSO` (reapertura).
   Se guarda el `closedate` original para restaurarlo al final.
3. **`PUT /Ticket/{id}`** con **solo** los campos base presentes en la fila, **sin el
   estatus todavía**.
4. **Upsert de la fila del contenedor** en la BD de GLPI: `SELECT` por
   `(items_id, itemtype='Ticket', plugin_fields_containers_id)`; si existe, `UPDATE`
   de las columnas llenas; si no, `INSERT`. Hoy `writePluginRow()` solo hace `INSERT`.
   No hay índice único garantizado sobre `items_id`: si aparecen duplicados se toma el
   `id` más bajo y se registra un aviso.
5. **Cierre (si aplica):** `POST /ITILSolution` con el texto de `SOLUCION` (o el default
   configurable), luego `PUT` estatus final + `solvedate`/`closedate`.
7. **Verificación:** releer el ticket y comparar contra lo enviado. Si un valor no se
   quedó, se reporta `DESVIACION` en la salida. Atrapa el caso de que las **reglas de
   negocio de GLPI reescriban** la categoría recién corregida.

Toda la fila corre dentro de una sola sesión API compartida por el lote
(`openApiSession()` una vez, como ya hace el importador).

### El título homologado

El importador arma `CLIENTE - SUCURSAL - TITULO` en mayúsculas, donde `CLIENTE` sale
del mapeo categoría → cliente del SuperAdmin y `SUCURSAL` del campo `sucursalfield`.

Regla en modo `update`: **el título se rearma solo si `TITULO` viene lleno**, usando la
categoría y sucursal de la fila y, si vienen vacías, las del ticket vivo. Si cambias la
categoría pero no el título, el título **no se toca**: reescribir el prefijo de un
título existente sin que se pida es adivinar. Queda como toggle de SuperAdmin
(`update_rehomologate_title`, por default apagado).

---

## 5. Validación previa (modo `update`)

`TicketImportValidator` gana un parámetro de modo. No se duplica: las validaciones de
fecha, número y pertenencia a catálogo son idénticas. En modo `update` cambia:

- `required` de columnas base se ignora; `TICKET_ID` pasa a obligatorio y numérico.
- **Existencia en GLPI:** consulta directa a `glpi_tickets` por lote de ids (barata, sin
  API). Los inexistentes se listan como error.
- **Duplicados de `TICKET_ID`** dentro del archivo → error.
- **Aviso informativo** de cuáles tickets ya están cerrados (se van a reabrir) y de
  cuántas celdas se plancharán por columna, para ver el alcance antes de aceptar.
- Cierre sin `FECHA_CIERRE` → aviso, no error: se usa `now()`.
- Se respeta `importMaxRows()` igual que en alta.

---

## 6. Persistencia y worker

`servicedesk_imports` gana tres columnas (migración con `fieldExists`, patrón del módulo):

| Columna | Tipo | Para qué |
|---|---|---|
| `mode` | `ENUM('create','update')` default `create` | Discrimina el motor. `source` sigue diciendo de dónde vino (`import` / `ai_creator`) |
| `skipped_rows` | `INT UNSIGNED` default 0 | Filas sin cambios; hoy el importador las omite sin contarlas |
| `dry_run` | `TINYINT(1)` default 0 | Simulación |

`ProcessImports` no cambia de estructura: despacha por `mode` a `serviceDeskImporter`
o a `serviceDeskUpdater`. Mismo `RunLock`, así las escrituras a GLPI siguen serializadas.

---

## 7. Resultado que se entrega

El workbook de salida gana dos columnas:

- `RESULTADO`: `ACTUALIZADO` · `RESUELTO`/`CERRADO` (la etiqueta es el estatus que pidió el
  operador, no un genérico: decir "CERRADO" en una fila que pedía RESUELTO hace desconfiar del
  reporte entero) · `SIN CAMBIOS` · `DESVIACION` · `ERROR: msg`
- `CAMBIOS`: `CATEGORIA: "Soporte" -> "Redes"; ESTATUS: En curso -> Cerrado`

La pantalla de detalle muestra además una tarjeta **Filas con problema** que aísla las
líneas `WARN`/`ERROR` de la bitácora: con un lote de cientos, buscarlas a mano dentro del
log completo no es viable.

El log por trabajo registra una línea por campo cambiado, con valor anterior y nuevo.
Una fila con `DESVIACION` lista **las dos cosas**: qué no se quedó y qué sí se aplicó; sin
eso no hay forma de saber qué quedó escrito en un ticket parcialmente actualizado.
Sirve como evidencia del lote.

---

## 8. Archivos

**Nuevos**

| Archivo | Qué hace |
|---|---|
| `Database/Migrations/…_AddUpdateModeToServiceDeskImports.php` | `mode`, `skipped_rows`, `dry_run` |
| `Database/Migrations/…_AddUpdateSettingsDefaults.php` | Toggles y textos default |
| `Services/TicketBulkUpdater.php` | El motor: diff, plancha, upsert de plugin, cierre |
| `Services/GlpiValueResolver.php` | Cachés de categoría/usuario/dropdown/cliente extraídos de `TicketBulkImporter`, compartidos por ambos motores |
| `Views/partials/update_form.php` | Tarjeta de carga en `/servicedesk` |

**Modificados**

| Archivo | Cambio |
|---|---|
| `Provisioning/Connectors/GlpiConnector.php` | `getTicket()`, `updateTicket()`, `addSolution()`. Hoy solo tiene `createTicket()`; TechBot los duplicó en `GlpiFieldService` |
| `ServiceDesk/Services/TicketBulkImporter.php` | Delegar resolución a `GlpiValueResolver` (refactor sin cambio de conducta) |
| `ServiceDesk/Services/TicketImportValidator.php` | Parámetro de modo |
| `ServiceDesk/Services/ServiceDeskSettings.php` | `updateEnabled()`, `updateSolutionText()`, `updateReopenClosed()`, `updateRehomologateTitle()`, `saveUpdate()` |
| `ServiceDesk/Commands/ProcessImports.php` | Despacho por `mode` |
| `ServiceDesk/Controllers/ServiceDesk.php` | `uploadUpdate()`, `applySimulation()`; historial y detalle con etiquetas por modo |
| `ServiceDesk/Controllers/Api/ServiceDeskApiController.php` | Espejo API |
| `ServiceDesk/Routes.php` | `GET/POST /servicedesk/actualizar`, `POST /servicedesk/imports/{id}/aplicar` + los espejos `/api/v1/servicedesk/updates[/{id}/apply]` |
| `ServiceDesk/Views/index.php`, `imports.php`, `show.php` | Tarjeta de carga, columna MODO, textos por modo |
| `ServiceDesk/Config/ServiceDesk.php` | `updateResultHeader`, `solutionHeader`, centinela `[VACIAR]` |
| `Services/TicketTemplateBuilder.php` | Columna opcional `SOLUCION` + nota en `INSTRUCCIONES` |
| `docs/tt-apps.postman_collection.json` | Endpoints nuevos |

---

## 8.bis Decisiones tomadas al construir (no estaban en el diseño)

1. **`SOLUCION` es una columna del template, no un template aparte.** Se agrega en
   `TicketTemplateBuilder`, no en `buildPlan()`: el plan es el contrato del alta masiva
   y una columna extra en la hoja es simplemente ignorada por el importador. Marcada
   como "Solo al actualizar" en `INSTRUCCIONES`, junto a una nota que explica los dos
   modos del mismo archivo.
2. **No se agrega una segunda solución a un ticket que ya la tenía.** Reabrir un ticket
   resuelto para corregirle la categoría no debe ensuciarlo con una solución automática.
   Solo se agrega si el ticket no tiene ninguna, o si la fila trae su propio texto.
3. **Reasignar es `_users_id_assign` + `DELETE /Ticket_User/{id}`, no un solo PUT.**
   GLPI no tiene una entrada de "reemplaza el técnico": `_users_id_assign` **AGREGA** un
   actor y deja los anteriores. Verificado en vivo: un ticket con el técnico A recibió B
   y quedó con **A y B**. El reemplazo real se cierra después del PUT quitando la relación
   sobrante por API (queda en el historial del ticket). Eso además habilita `[VACIAR]`
   para desasignar, que antes se rechazaba.

   La regla al quitar: **solo se quita a quien ya estaba asignado ANTES de escribir, y solo
   si el técnico pedido quedó efectivamente puesto.** Si una regla de negocio de GLPI asignó
   a alguien durante el mismo PUT, Nexus no deshace en silencio lo que la regla decidió: no
   quita nada y reporta `DESVIACION` diciendo quién quedó.
4. **`DESVIACION` cuenta en `failed_rows`.** Significa "el ticket no quedó como dice el
   Excel", que para el operador es un fallo. La columna `RESULTADO` distingue el tipo.
5. **Restauración tras reapertura.** Si se reabrió un ticket y la fila NO pidió cambiar
   el estatus, se devuelve a su estatus original reenviando `solvedate`/`closedate`
   (GLPI las limpia al reabrir), y la verificación comprueba esa restauración.
6. **Las columnas de salida no cuentan como contenido de fila.** Al reprocesar un archivo
   de resultado, una fila vacía con `SIN CAMBIOS` pegado de la corrida anterior se leería
   como fila real y saldría como ERROR.
7. **`content` queda fuera de la verificación.** GLPI lo reescribe (HTML, saltos) y
   compararlo produciría falsos positivos constantes. El resto se compara normalizado.
8. **Los banners informativos no llevan `role="status"`.** `public/js/app.js` auto-oculta
   `.banner[role="status"]` a los 5 segundos porque ese rol marca los mensajes flash
   transitorios. Un banner explicativo permanente con ese rol desaparece solo y deja al
   operador sin el contexto (detectado en la primera prueba real).
9. **Las reglas de negocio de GLPI ganan, y eso se reporta, no se pelea.** En el primer
   lote aplicado de verdad, 6 de 10 filas salieron `DESVIACION` en el técnico. Causa: la
   regla de tickets *"Actinver Data Center"* (`itilcategories_id = 7` -> `_users_id_assign = 20`)
   pisa el técnico del Excel durante el mismo `PUT`. **No es un fallo del módulo: es el paso
   de verificación (§4.7) haciendo exactamente su trabajo.** El mensaje ahora nombra quién
   quedó asignado y apunta a Administración > Reglas > Reglas de tickets.
10. **GLPI rechaza campos con HTTP 200.** Verificado: un `PUT` con una fecha de apertura
    inválida responde `200` con el cuerpo
    `[{"19": true, "message": "Datos no válidos. Actualización cancelada"}]`, **guarda los
    demás campos y descarta el ofensivo**. Leer solo el código HTTP daba el rechazo por
    éxito. `GlpiConnector::updateTicket()` ahora devuelve ese `glpiMessage` al llamador
    (sin interpretarlo: el texto viene en el idioma de GLPI) y el actualizador lo acumula
    por fila y lo reporta con las palabras exactas de GLPI. Comprobado que un `PUT` válido
    devuelve `message` vacío, así que no genera falsos problemas.
11. **La fecha de apertura no puede pasar del límite del SLA.** Acotado empíricamente sobre
    un ticket con `time_to_resolve = 2026-08-12 09:00`: `08:00` se acepta, `10:00` se
    rechaza. La desviación de `date` lo dice explícitamente en vez de dejar al operador
    adivinando.
12. **La simulación NO puede cubrir el camino de escritura.** Un ensayo corta en el paso 5
    de §4, antes del primer `PUT`, así que un error en la mitad que escribe solo aparece al
    aplicar. Pasó de verdad: un tipo de retorno sin importar (`ConnectorResult` se resolvía
    al namespace del propio archivo) tumbó las 11 filas del primer lote de cierre, con la
    simulación en verde. `php -l` tampoco lo detecta. Por eso ese camino se prueba ahora
    contra un ticket desechable que el propio test crea y purga (§11).
13. **GLPI no acepta fechas de cierre históricas al ACTUALIZAR.** Mapeado exhaustivamente:

    | Operación | ¿Respeta la fecha enviada? |
    |---|---|
    | `status = 5` (Resuelto) con `solvedate` | sí |
    | `status = 6` (Cerrado) con `closedate`, en un PUT | no: sella la hora actual |
    | `status = 6` y luego `closedate` en un PUT aparte | no: sella la hora actual |
    | `closedate` con el ticket abierto | sí |
    | `closedate` en el **alta** (lo que hace el importador) | sí |

    Es decir: `solvedate` conserva la fecha del Excel y `closedate` no. **Decisión del usuario
    (2026-08-18): restituir `closedate` escribiendo esa sola columna en la BD de GLPI justo
    después del cierre por API.** El cierre en sí (solución, estatus, historial y reglas) sigue
    pasando por la API; solo se corrige esa columna, con el mismo criterio con el que ya se
    escriben las filas del contenedor del plugin. Sin esto, cerrar en masa tickets viejos los
    marcaría a todos como cerrados el día de la carga y los reportes de cierre saldrían mal.
    La restitución aplica también al re-cerrar un ticket que se reabrió para corregirlo.
14. **El vocabulario de la simulación es condicional, no pasado.** Un ensayo que reporta
   "10 aplicados" hace creer que ya escribió. La bitácora dice "cambiarían", la columna
   `RESULTADO` dice `SIMULADO (CAMBIARIA)` / `SIMULADO (CAMBIARIA Y CERRARIA)` y el
   resumen final aclara que no se escribió nada.

---

## 9. Fases

| Fase | Alcance |
|---|---|
| **F1** | Migraciones, `GlpiValueResolver`, validador en modo `update`, `TicketBulkUpdater` con plancha de campos base + upsert de plugin, simulación, UI de carga/historial/detalle/salida |
| **F2** | Cierre real: `ITILSolution`, estatus, `solvedate`/`closedate`, reapertura de cerrados, verificación post escritura y reporte de `DESVIACION` |
| **F3** | Espejo en `/api/v1/servicedesk/updates`, Postman, ajustes de SuperAdmin y guía en el Centro de ayuda |

---

## 10. Riesgos

1. **Notificaciones masivas de GLPI.** Cerrar cientos de tickets vía API dispara correos
   a los solicitantes. Mitigación: advertencia en pantalla antes de encolar + guía para
   apagar notificaciones durante el lote. Decisión 4.
2. **Reglas de negocio de GLPI** pueden revertir la categoría planchada. Mitigación: paso
   de verificación (§4.6) que reporta `DESVIACION` en vez de mentir "ACTUALIZADO".
3. **Resolver una solución requiere técnico asignado** en algunas configuraciones de GLPI.
   Mitigación: si el ticket no tiene asignado y la fila no lo trae, se reporta error de
   fila con mensaje explícito en vez de fallar opaco.
4. **Operación destructiva.** Mitigación: simulación, validación previa contra GLPI vivo,
   toggle `update_enabled` del SuperAdmin y auditoría por `uploaded_by`.
5. **Duplicados en la tabla del plugin** (§4.4). Mitigación: tomar el `id` más bajo y avisar.

---

## 11. Estado de verificación

**Verificado:**

- Migraciones aplicadas y `db:verify-schema` en verde (75 tablas).
- Columnas `mode` / `skipped_rows` / `dry_run` y las 5 claves `update_*` en la BD.
- Rutas web, API y admin registradas (`spark routes`).
- El contenedor de servicios arma `glpiValueResolver`, `serviceDeskUpdater`,
  `serviceDeskImporter`, `ticketImportValidator` y `ticketTemplateBuilder`.
- 21 pruebas de la lógica pura del actualizador: centinela `[VACIAR]`, celdas
  "llenas" (incluido que `N/A`/`NAN`/`NONE` cuentan como vacías), normalización de
  texto contra el HTML de GLPI, ids de estatus cerrados, nombre del archivo de
  salida, split de columnas del plan, idempotencia de las columnas de salida y
  rechazo del validador en ambos modos ante un archivo sin `_META`.
- Render autenticado sin errores de `/servicedesk/actualizar`, `/servicedesk/imports`
  (con y sin filtro), `/admin/servicedesk/settings` y la guía del Centro de ayuda.

**Probado en vivo (2026-08-18, lote de 10 tickets):**

- Simulación completa contra GLPI real. El archivo traía llenas SOLO 4 columnas de
  contenedor (`SIAF`, `CC`, `IDS NOMBRE`, `IDS NUMERO DE EMPLEADO`) y `TICKET_ID`;
  el motor reportó exactamente esas 4 por fila y ninguna columna base, que es la
  regla "celda vacía = no tocar" funcionando.
- Guardas de `Aplicar en GLPI`: aplicar un trabajo que no es simulación se rechaza y
  no encola nada.

**Aplicado de verdad (2026-08-18, lotes #7 y #9 sobre 10 tickets):**

- `PUT` de campos base (categoría, título rehomologado, id externo) y upsert de las filas
  de los dos contenedores del plugin: correcto, verificado contra `glpi_tickets` y
  `glpi_logs`.
- Rehomologación del título: intercambió el prefijo CLIENTE donde correspondía y **se
  abstuvo** en el ticket cuyo título no empezaba con el cliente anterior, avisando en la
  bitácora. Tal cual se diseñó.
- `DESVIACION` reportada correctamente en 6 filas por la regla de negocio de GLPI
  (ver §8.bis.9).
- Reemplazo de técnico: probado con las dos ramas de `reconcileAssignees()` sobre un
  ticket real, restaurando su estado exacto al terminar.

**Aplicado de verdad (2026-08-18, lote #13 sobre 11 tickets):**

- Reemplazo de técnico ya corregido: 10 de 11 tickets quedaron con **un solo** técnico,
  el del Excel, incluidos los que arrastraban dos o tres asignados.
- Rehomologación de títulos en cadena (`ACTINVER DATA CENTER - ...` a
  `SELLCOM BBVA CAJEROS - ...`) en los 11.
- La única fila con problema fue un rechazo real de GLPI por el SLA (§8.bis.11), detectado
  por la verificación.

**Camino de escritura probado de punta a punta (2026-08-18)** contra un ticket desechable
creado y purgado por el propio test, 11 aserciones en verde:

- Cierre: `ITILSolution` creada, estatus 6, `solvedate` con la fecha del Excel.
- Corregir un ticket **ya cerrado**: se reabre, se escribe el título y se vuelve a cerrar,
  **sin** agregar una segunda solución.
- Idempotencia: reaplicar la misma fila da `SIN CAMBIOS`.
- El aviso de `closedate` (§8.bis.13) aparece en el reporte.

Re-verificado tras implementar la restitución de `closedate`, **13 aserciones en verde**:
cerrar con fecha histórica la conserva en `solvedate` y en `closedate`, y corregir después
ese ticket ya cerrado lo reabre, escribe y re-cierra **sin perder ninguna de las dos fechas**.

**Lotes de cierre reales desde la pantalla (2026-08-18):**

- 12 tickets a `RESUELTO` con solución registrada, sin una sola incidencia.
- 4 tickets a `CERRADO` con fecha histórica: `solvedate` y `closedate` quedaron en
  `2026-08-17 19:00:00` (la del Excel), confirmando la restitución de §8.bis.13 en producción.
  Las 4 filas salieron `DESVIACION` **solo** por `FECHA_APERTURA`: el Excel pedía
  `2026-08-17` sobre tickets cuyo SLA vencía el `2026-08-04` (§8.bis.11). Todo lo demás de
  esas filas se aplicó, y la bitácora lo lista.
- Confirmado en vivo que las filas vacías que solo arrastran `RESULTADO`/`CAMBIOS` de una
  corrida anterior se ignoran (§8.bis.6): la hoja tenía 12 filas y solo se procesaron las 4
  con datos.

**Pendiente:** nada bloqueante.
- Los tickets 16, 17 y 18 quedaron con **dos técnicos** por el bug de §8.bis.3 (aplicado
  antes de la corrección). Se arreglan solos volviendo a correr el mismo Excel.
- Confirmar que GLPI 11 acepta `_users_id_assign` en un `PUT /Ticket/{id}`. Si no lo
  aceptara, la verificación lo reporta como desviación en lugar de fallar en silencio,
  pero habría que cambiar a `_actors`.
- `/servicedesk` (alta masiva) responde 500 con GLPI abajo. Es previo a este trabajo
  (verificado contra el código sin los cambios), no una regresión.
