# Service Desk: papelera masiva

Extensión de ServiceDesk (no es un módulo nuevo) que manda a la papelera de GLPI, en un solo lote, los tickets listados por `TICKET_ID`. Reusa la tabla `servicedesk_imports`, el worker `servicedesk:process-imports`, el historial y la vista de detalle. El modo del job (`servicedesk_imports.mode`, VARCHAR) elige el motor.

## Decisiones

- **Solo papelera, nunca purga.** `DELETE Ticket/{id}` sin `force_purge` deja `is_deleted = 1`. No hay camino de borrado definitivo en Nexus.
- **Cualquier estado.** Los tickets abiertos se aceptan; la validación los devuelve en `openIds` como advertencia.
- **Reversible.** Un lote `trash` aplicado se restaura desde su detalle: se encola un job `restore` solo con los tickets cuyo `RESULTADO` fue exactamente `PAPELERA`.
- **Por la API REST.** GLPI aplica permisos, historial y notificaciones, igual que una baja manual. El usuario de la API necesita el permiso Ticket: Eliminar.
- **Se relee `is_deleted` tras escribir.** GLPI puede responder 200 sin hacer nada; ese caso sale como `DESVIACION`, nunca como `PAPELERA`.
- **Simulación marcada por defecto**, con el mismo patrón que la actualización: "Aplicar en GLPI" re-encola el mismo archivo con `dry_run = 0` y la simulación queda como evidencia.
- **Apagado por defecto.** Ajuste `trash_enabled` (SuperAdmin, pestaña de actualización masiva). Sin fila en `servicedesk_settings` se lee como apagado, por eso no hay migración.

## Piezas

| Pieza | Dónde |
|---|---|
| Métodos GLPI `trashTicket` / `restoreTicket` | `Provisioning/Connectors/GlpiConnector.php` |
| Validación (`validateTrashFile`, solo `TICKET_ID`, sin `_META`) | `ServiceDesk/Services/TicketImportValidator.php` |
| Motor del worker | `ServiceDesk/Services/TicketBulkTrasher.php` (servicio `serviceDeskTrasher`) |
| Encolado, aplicar simulación, restaurar, plantilla (web y API) | `ServiceDesk/Services/TrashJobService.php` (servicio `trashJobService`) |
| Selección de motor | `ServiceDesk/Commands/ProcessImports.php` (`match` por modo) |
| Vista | `ServiceDesk/Views/trash.php`, ajustes en `Views/settings.php`, ayuda `Views/help/papelera-masiva.php` |

## Resultados por fila

`PAPELERA`, `RESTAURADO`, `YA EN PAPELERA` / `YA ACTIVO` (omitidos), `SIMULADO (IRIA A PAPELERA)` / `SIMULADO (SE RESTAURARIA)`, `DESVIACION` y `ERROR`. El archivo `_resultado.xlsx` agrega las columnas `RESULTADO` y `ESTADO_PREVIO`.

## Rutas

Web (`servicedesk`): `GET papelera`, `GET papelera/plantilla`, `POST papelera`, `POST imports/{id}/restaurar`; `POST imports/{id}/aplicar` acepta también jobs `trash`.

API (`api/v1/servicedesk`): `GET|POST trash`, `GET trash/{id}`, `POST trash/{id}/apply`, `POST trash/{id}/restore`.
