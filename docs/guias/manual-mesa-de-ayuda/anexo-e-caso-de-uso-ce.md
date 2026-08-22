# Anexo E. Caso de uso resuelto: ticket de Clientes Externos
 
**Manual de Uso de GLPI para Agentes de Mesa de Ayuda**
Trantor Technologies | Service Desk
 
---
 
Este caso de uso es ficticio y sirve como ejemplo completo de cómo se registra y se da seguimiento a un ticket de Clientes Externos, aplicando las plantillas de seguimiento de principio a fin. Los datos son de ejemplo.
 
## Ficha del ticket
 
| Dato | Valor |
|---|---|
| Título | AFIRME - HEB GUADALUPE LIVAS - FALLA EN TECLADO |
| Número (referencia) | 42 |
| Tipo | Incidente |
| Categoría | OP > CE > Afirme > Multivendor |
| Fecha de apertura | 29-06-2026 12:00 |
| Origen | Correo electrónico |
| Urgencia / Impacto / Prioridad | Media / Baja / Baja |
| ID Externo | REPXNORTESA |
| Solicitante | Pérez Salgado Juan Carlos (agente MAC) |
| Asignado a | Ocampo Morales Emmanuel (coordinador, Nuevo León) |
 
Datos de la tab Clientes Externos: Regional DTN - Zona 1, Estado Nuevo León, Municipio Monterrey, Sucursal HEB Guadalupe Livas, Local, Usuario final María del Carmen Jiménez Torres, Equipo Laptop, Modelo T490, Serie NX-12257, SIAF NO PROPORCIONADO, CC 332. IDS: Abrego Ramírez Rolando David (9383).
 
SLA aplicado (automático, Incidente): asignación (TTO) 3 horas, solución (TTR) 10 horas, calendario 5x8.
 
> Nota de lectura: la variable `{{ ticket.ref }}` de las plantillas inserta sola el número de ticket. En este caso, se muestra como 42.
 
---
 
## Línea de tiempo
 
### 1. Creación del ticket
**29-06-2026 12:00 | Estado: En curso (asignada)**
 
El agente MAC recibe la solicitud por correo, registra el ticket, lo clasifica como Afirme Multivendor y lo asigna al coordinador de Nuevo León (Emmanuel Ocampo), porque es una categoría de atención dinámica por zona. Reabre el ticket y completa la tab Clientes Externos e IDS.
 
---
 
### 2. IDS asignado
**29-06-2026 12:40 | Botón: Responder | Plantilla: Soporte - IDS Asignado**
 
El coordinador asigna al IDS Rolando David Abrego Ramírez. El agente documenta:
 
> El ticket 42 ha sido asignado a:
> Rolando David Abrego Ramírez (No. 9383).
 
Con IDS y fecha/hora de atención definidos, el ticket pasa a **En curso (planificada)**.
 
---
 
### 3. IDS en camino
**29-06-2026 13:15 | Botón: Responder | Plantilla: Soporte - IDS En camino**
 
> Se inicia traslado hacia el sitio de atención. Arribo estimado a las 14:00 hrs.
> Técnico asignado: Rolando David Abrego Ramírez.
 
---
 
### 4. IDS en sitio
**29-06-2026 14:05 | Botón: Responder | Plantilla: Soporte - IDS En sitio**
 
> Rolando David Abrego Ramírez se ha presentado en sitio a las 14:05 hrs. Se da inicio a la atención del ticket 42.
 
---
 
### 5. Diagnóstico
**29-06-2026 14:50 | Botón: Responder | Plantilla: Soporte - Equipo / Servicio Diagnosticado**
 
> Se ha concluido el diagnóstico del ticket 42.
>
> Diagnóstico realizado:
> Varias teclas del equipo Laptop T490 (serie NX-12257) no responden por daño físico del teclado interno. El equipo requiere reemplazo de teclado. No es una falla de configuración.
 
---
 
### 6. Pendiente por refacción (pausa del SLA)
**29-06-2026 15:00 | Botón: Responder | Plantilla: Soporte - Pendiente por refacción**
 
> El ticket 42 se encuentra en espera de las refacciones requeridas para su resolución.
>
> Refacciones requeridas: teclado de reemplazo Lenovo ThinkPad T490 (distribución español latinoamérica).
>
> Se notificará en cuanto estén disponibles.
 
El ticket pasa a **En espera**. Al aplicar la plantilla de Pendiente, **GLPI pausa automáticamente el reloj del SLA**: el tiempo de solución deja de correr mientras se espera la refacción.
 
> Nota operativa: el traslado de la refacción se gestiona en un **ticket aparte de Control de Envíos**, no dentro de este ticket. Aquí solo se documenta que el caso quedó en espera de la refacción.
 
---
 
### 7. Refacción disponible y reactivación
**30-06-2026 10:30 | Botón: Responder | Plantilla: Soporte - IDS En camino**
 
Llega la refacción. El agente reactiva el ticket, con lo que el **reloj del SLA se reanuda**, y documenta la nueva visita:
 
> Se inicia traslado hacia el sitio de atención. Arribo estimado a las 11:30 hrs.
> Técnico asignado: Rolando David Abrego Ramírez.
 
El ticket regresa a **En curso (planificada)**.
 
---
 
### 8. IDS en sitio (segunda visita)
**30-06-2026 11:35 | Botón: Responder | Plantilla: Soporte - IDS En sitio**
 
> Rolando David Abrego Ramírez se ha presentado en sitio a las 11:35 hrs. Se da inicio a la atención del ticket 42.
 
---
 
### 9. Resolución con refacción
**30-06-2026 12:10 | Botón: Resolver | Plantilla: Soporte - Resolución - Con refacción**
 
> El ticket 42 ha sido resuelto satisfactoriamente con uso de refacciones.
>
> Pasos realizados:
> Se retiró el teclado dañado del equipo T490, se instaló el teclado de reemplazo, se probaron todas las teclas y se validó el funcionamiento con la usuaria.
>
> Refacciones utilizadas: teclado Lenovo ThinkPad T490.
>
> Fecha y hora de inicio: 30-06-2026 11:40
> Fecha y hora de término: 30-06-2026 12:05
> Visto Bueno: María del Carmen Jiménez Torres
>
> El ticket queda en estado Resuelto. Se solicita confirmar la conformidad del servicio.
> De no recibirse respuesta en un plazo de 48 horas, el ticket pasará automáticamente al estado Cerrado.
 
El ticket pasa a **Resuelto**.
 
---
 
### 10. Cierre automático
**02-07-2026 (48 horas después) | Estado: Cerrado**
 
Al no recibirse respuesta en contra dentro de las 48 horas, GLPI cierra el ticket automáticamente. El agente no realiza ninguna acción: nunca cierra a mano.
 
---
 
## Qué ilustra este caso
 
- **El agente MAC orquesta todo el ciclo** aunque no atienda en sitio: documenta cada movimiento del IDS con la plantilla correcta.
- **La plantilla de Pendiente es lo que pausa el SLA.** El tiempo que el ticket estuvo En espera por la refacción (del 29-06 a las 15:00 al 30-06 a las 10:30) no contó contra el reloj de solución. Sin esa pausa, el ticket habría consumido el SLA mientras esperaba algo externo.
- **La refacción viaja en un ticket aparte** de Control de Envíos: no se mezcla con este ticket de atención.
- **El agente resuelve, no cierra.** Aplica la plantilla de Resolución (botón Resolver) y el ticket queda en Resuelto; el cierre a las 48 horas lo hace GLPI.
- **La prioridad cuadra con la matriz:** urgencia Media e impacto Baja dan prioridad Baja, calculada por GLPI.
---
 
*Fin del Anexo E. Caso de uso resuelto: ticket de Clientes Externos.*