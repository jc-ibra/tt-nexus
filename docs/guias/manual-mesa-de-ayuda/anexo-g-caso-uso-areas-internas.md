# Anexo G. Caso de uso: Áreas Internas (Laboratorio)
 
**Manual de Uso de GLPI para Agentes de Mesa de Ayuda**
Trantor Technologies | Service Desk
 
---
 
Este caso de uso es ficticio y muestra el llenado y seguimiento de un ticket de Áreas Internas, usando como ejemplo la categoría Laboratorio. Los datos son de ejemplo.
 
La tab **Áreas Internas** la comparten cuatro categorías: Laboratorio, Sistemas Internos, Servicios Internos y Documentación Interna. El llenado de la tab es igual para todas; lo que cambia es cómo se documenta el seguimiento (ver nota al final).
 
## Ficha del ticket
 
| Dato | Valor |
|---|---|
| Título | LABORATORIO - CONFIGURACIÓN DE EQUIPO PARA DESPLIEGUE |
| Tipo | Solicitud |
| Categoría | OP > AI > Laboratorio |
| Estado inicial | En curso (asignada) |
| Origen | Correo electrónico |
| ID Externo | NO APLICA |
| Solicitante | Agente MAC que registra |
| Asignado a | Automático: Raúl López Balbuena |
 
## Tab Áreas Internas
 
| Campo | Valor | Obligatorio |
|---|---|---|
| Equipo | Laptop | Sí |
| Modelo | Latitude 5420 | No |
| Serie | LB-4471 | No |
| SIAF | 100982 | No |
| Categoría | Preparación / configuración | No |
 
En los campos de texto libre aplica la regla general de datos faltantes (`NO PROPORCIONADO` / `NO APLICA`).
 
## Tab IDS
 
| Campo | Valor |
|---|---|
| IDS Nombre | Mendoza Ríos Andrés (resolutor de laboratorio) |
| IDS Número de empleado | 100640 |
 
La tab IDS **sí se llena**: registra a quien resolvió la solicitud en laboratorio.
 
## Seguimiento (texto libre)
 
Laboratorio no tiene plantillas de seguimiento configuradas, por lo que se documenta con texto libre. Los hitos mínimos:
 
**Solicitud recibida**
> Se recibe solicitud de configuración de 1 equipo Laptop Latitude 5420 (serie LB-4471) para despliegue. Equipo ingresa a laboratorio.
 
**En proceso (opcional)**
> Configuración en curso: instalación de imagen corporativa, aplicaciones y validación de funcionamiento.
 
**Resuelto**
> Equipo Laptop Latitude 5420 (serie LB-4471) configurado y validado. Listo para entrega/despliegue.
>
> El ticket queda en estado Resuelto.
 
El ticket pasa a **Resuelto** (botón Resolver) y GLPI lo cierra automáticamente a las 48 horas.
 
---
 
## Nota: diferencia entre Laboratorio y Sistemas Internos
 
Ambas categorías usan la tab Áreas Internas y llevan IDS, pero se documentan distinto:
 
- **Laboratorio, Servicios Internos y Documentación Interna:** no tienen plantillas. El seguimiento es de **texto libre**, como en este ejemplo.
- **Sistemas Internos:** sí tiene plantillas de seguimiento configuradas. Su seguimiento se documenta con las mismas plantillas de Soporte que Clientes Externos (IDS Asignado, En camino, En sitio, Diagnosticado, Pendiente, Resolución), aplicadas desde los botones Responder y Resolver. Ver la Parte 4 y el caso de uso del Anexo E.
---
 
*Fin del Anexo G. Caso de uso: Áreas Internas (Laboratorio).*