# Anexo F. Casos de uso: Control de Activos y Control de Envíos
 
**Manual de Uso de GLPI para Agentes de Mesa de Ayuda**
Trantor Technologies | Service Desk
 
---
 
Estos dos casos de uso son ficticios y muestran el llenado y seguimiento de las categorías de Administración que sí opera el agente MAC. A diferencia de Clientes Externos, estas categorías **no tienen plantillas de seguimiento configuradas**, por lo que las actualizaciones se documentan con **texto libre**.
 
---
 
## Caso 1. Control de Activos (pedido SAE)
 
Recordatorio: esta categoría solo se usa cuando Almacén genera un pedido en Aspel SAE y se le da trazabilidad hasta que se surte. No lleva IDS ni plantillas. No se mezcla con Control de Envíos.
 
### Ficha del ticket
 
| Dato | Valor |
|---|---|
| Título | CONTROL DE ACTIVOS - PEDIDO SAE EQUIPO DE CÓMPUTO |
| Tipo | Solicitud |
| Categoría | AD > Almacén > Control de Activos |
| Estado inicial | En curso (asignada) |
| Origen | Correo electrónico |
| ID Externo | NO APLICA |
| Solicitante | Agente MAC que registra |
| Asignado a | Automático: Antonio Hernández Bermúdez |
 
### Tab Control de Activos
 
| Campo | Valor | Nota |
|---|---|---|
| Equipo | CPU | Obligatorio (lista). |
| Modelo | OPTIPLEX 780 | Obligatorio. |
| Serie | NXY112-1A | Obligatorio. |
| SIAF | 111485 | Número de inventario del cliente. |
| CC | 302 | Centro de costos. |
| SAE | 2254 | Número de pedido en Aspel SAE. |
 
La tab IDS se deja **sin tocar**: esta categoría no registra atención en sitio.
 
### Seguimiento (texto libre)
 
**Pedido creado**
> Se genera el pedido SAE 2254 para surtir 1 equipo CPU Optiplex 780 (serie NXY112-1A), CC 302. En espera de surtido por Almacén.
 
**En espera por surtido (opcional)**
> El pedido SAE 2254 continúa en proceso de surtido. Se da seguimiento con Almacén.
 
**Pedido surtido y resolución**
> El pedido SAE 2254 fue surtido correctamente por Almacén. Equipo CPU Optiplex 780 (serie NXY112-1A) disponible. Se cierra la trazabilidad del pedido.
>
> El ticket queda en estado Resuelto.
 
El ticket pasa a **Resuelto** (botón Resolver) y GLPI lo cierra automáticamente a las 48 horas.
 
> Nota: si este equipo debiera trasladarse a otro estado y requiere guía, ese envío se registra en un **ticket aparte de Control de Envíos** (ver Caso 2).
 
---
 
## Caso 2. Control de Envíos (guía de traslado)
 
Este caso es el **envío del teclado de refacción** que quedó pendiente en el ticket 42 (Anexo E). Ilustra la regla de que la refacción viaja en un ticket propio de Control de Envíos, ligado por el campo "Ticket asociado".
 
### Ficha del ticket
 
| Dato | Valor |
|---|---|
| Título | CONTROL DE ENVÍOS - ENVÍO REFACCIÓN TECLADO T490 |
| Tipo | Solicitud |
| Categoría | AD > Servicios Generales > Control de Envíos |
| Estado inicial | En curso (asignada) |
| Origen | Correo electrónico |
| ID Externo | NO APLICA |
| Solicitante | Agente MAC que registra |
| Asignado a | Automático: Gloria Deyanira Guerrero Palomares |
 
### Tab Control de Envíos
 
| Campo | Valor | Obligatorio |
|---|---|---|
| Guía | 3111074702 | Sí |
| Carrier | DHL | Sí |
| Proyecto | Actinver | Sí |
| Solicitante | Yael Levi Gallardo Sánchez | Sí |
| CC | 123 | Sí |
| Remitente Nombre | Rolando David Abrego Ramírez | Sí |
| Remitente Estado | Sonora | Sí |
| Remitente Localidad | Hermosillo | Sí |
| Destinatario Nombre | José Fernando Aguilar Fuerte | Sí |
| Destinatario Estado | Jalisco | Sí |
| Destinatario Localidad | Guadalajara | Sí |
| Fecha de envío | 04-07-2026 | Sí |
| Costo guía | 250 | No |
| Ticket asociado | 42, 35 | No |
| Serie | NX322254 | No |
| SIAF | NO APLICA | No |
| Fecha de recolección | (pendiente) | No |
 
La tab IDS **sí se llena** en esta categoría: se registra a quien coordina o entrega el envío.
 
### Seguimiento (texto libre)
 
**Envío generado**
> Se genera la guía DHL 3111074702 para el traslado de refacción (teclado T490, serie NX322254) del remitente Rolando David Abrego Ramírez (Hermosillo, Sonora) al destinatario José Fernando Aguilar Fuerte (Guadalajara, Jalisco). Proyecto Actinver, CC 123. Tickets asociados: 42 y 35.
 
**En tránsito (opcional)**
> Envío en tránsito con DHL. Guía 3111074702. Se da seguimiento a la entrega.
 
**Entregado y resolución**
> La guía DHL 3111074702 fue entregada al destinatario. Refacción recibida correctamente. Se cierra la trazabilidad del envío.
>
> El ticket queda en estado Resuelto.
 
El ticket pasa a **Resuelto** (botón Resolver) y GLPI lo cierra automáticamente a las 48 horas.
 
---
 
## Qué ilustran estos casos
 
- **Cada tab documenta una cosa distinta.** Control de Activos documenta un pedido SAE; Control de Envíos documenta una guía de traslado. No se mezclan.
- **Sin plantillas, el seguimiento es texto libre**, pero igual debe registrar cada hito: creación, avance y cierre. La regla de propiedad del ticket aplica igual.
- **Control de Activos no lleva IDS**; Control de Envíos sí.
- **El campo Ticket asociado liga los casos:** el envío del teclado (ticket de envíos) queda vinculado al ticket de atención 42 y al de activos 35, dando trazabilidad completa.
- **ID Externo NO APLICA:** son procesos internos sin número de cliente.
---
 
*Fin del Anexo F. Casos de uso: Control de Activos y Control de Envíos.*
 