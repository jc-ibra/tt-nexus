# Anexo D. Guía rápida de creación de ticket
 
**Manual de Uso de GLPI para Agentes de Mesa de Ayuda**
Trantor Technologies | Service Desk
 
---
 
Resumen operativo para crear un ticket sin errores. Para el detalle, ver la Parte 3.
 
## El flujo del agente MAC en 5 pasos
 
1. Recibir la solicitud (área o líder de proyecto).
2. Registrar el ticket en GLPI y clasificarlo.
3. Asignar al coordinador (o dejar que GLPI asigne según la categoría).
4. Reabrir el ticket, validar y llenar los campos personalizados.
5. Dar seguimiento hasta que el servicio quede en Resuelto.
## Checklist de creación (en orden)
 
| # | Campo | Qué poner |
|---|---|---|
| 1 | Título | Clientes externos: `CLIENTE - SUCURSAL - DESCRIPCIÓN`. Categorías internas (sin sucursal): `NOMBRE DE CATEGORÍA - DESCRIPCIÓN`. Todo en mayúsculas. |
| 2 | Descripción | Detalle claro pensando en el IDS. Adjuntos hasta 10 MB. |
| 3 | Fecha de apertura | Manual: la de la solicitud o, si no hay, la del correo recibido. Nunca por default. |
| 4 | Tipo | Incidente (algo falló) o Solicitud (petición estándar). |
| 5 | Categoría | La hoja correcta del árbol. Dispara reglas: verifícala. |
| 6 | Estado | En curso (asignada). |
| 7 | Origen de la solicitud | El canal real (correo, llamada, Telegram, WhatsApp, otro). |
| 8 | Urgencia + Impacto | Según criterio. GLPI calcula la Prioridad. |
| 9 | ID Externo | Número del cliente / `NO PROPORCIONADO` / `NO APLICA`. |
| 10 | Solicitante | El propio agente MAC. |
| 11 | Observador | Vacío. |
| 12 | Asignado a | Coordinador por estado (CE dinámico) o automático (responsable fijo). |
 
## Después de guardar (no olvidar)
 
1. **Reabrir** el ticket.
2. **Validar** que la categoría y la asignación quedaron correctas (doble check).
3. Llenar **solo la tab de la categoría** + la tab **IDS**.
   - Excepción: Control de Activos no lleva IDS.
## Reglas de oro
 
- Título siempre en mayúsculas y con la nomenclatura.
- Mayúsculas **solo** en el título y en `NO APLICA` / `NO PROPORCIONADO`; el resto (descripción y campos de las tabs) con altas y bajas.
- Fecha de apertura manual, nunca por default.
- Categoría bien elegida = reglas de negocio correctas.
- Texto libre sin dato: `NO PROPORCIONADO` (debía ir) o `NO APLICA` (no corresponde).
- Desplegables obligatorios: se seleccionan de la lista, no admiten esas convenciones.
- CC admite `NO PROPORCIONADO`, nunca `NO APLICA`: todo lleva centro de costos.
- IDS Nombre y Número: seleccionar bien, no admite convenciones.
- El agente nunca cierra: GLPI cierra a las 48 h en Resuelto.
- ¿Falta un valor en una lista? Se pide al administrador, no se crea por cuenta propia.
## Matriz de prioridad (la calcula GLPI)
 
| Urgencia \ Impacto | Bajo | Medio | Alto |
|---|---|---|---|
| **Alta** | Media | Alta | Alta |
| **Media** | Baja | Media | Alta |
| **Baja** | Baja | Baja | Media |
 
## Estados que usa el MAC
 
En curso (asignada) → En curso (planificada) → En espera → Resuelto.
(Cerrado lo hace GLPI automáticamente.)
 
## SLA estándar (automático por tipo, calendario 5x8)
 
| Tipo | Asignación (TTO) | Solución (TTR) |
|---|---|---|
| Incidente | 3 horas | 10 horas |
| Solicitud | 6 horas | 30 horas |
 
## Escalación (Clientes Externos)
 
Coordinador de zona → Gerente de la zona (DTN / DTS) → Director de Operaciones.
 
## Siglas
 
MAC (Mesa de Ayuda Central), IDS (Ingeniero de Servicio), CE (Clientes Externos), AI (Áreas Internas), OP (Operaciones), AD (Administración), CC (Centro de Costos), SIAF (etiqueta de inventario), SAE (pedido Aspel SAE), SLA (Acuerdo de Nivel de Servicio), TTO (tiempo de asignación), TTR (tiempo de solución).
 
## Dónde consultar el detalle
 
- Coordinadores por estado: Parte 3.5.
- Tabla maestra de categorías: Anexo A.
- SLA: Anexo B.
- Escalación: Anexo C.
---
 
*Fin del Anexo D. Guía rápida de creación de ticket.*
 