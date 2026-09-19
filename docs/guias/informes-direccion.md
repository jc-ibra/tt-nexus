# Informe ejecutivo mensual

> Espejo de `app/Modules/Reports/Views/help/informes.php` (Ayuda > Informe ejecutivo
> mensual). Si cambias el cálculo o el flujo, actualiza los dos.

## Qué es el informe ejecutivo mensual

Cada mes cerrado se congela en un **informe**: un conjunto fijo de números que ya no
cambia, aunque los tickets sigan actualizándose en GLPI después. Así, cuando dirección
compara agosto contra septiembre, ambos meses reflejan el mismo momento de corte.

El informe reúne cinco fuentes: tickets de GLPI, la tendencia contra meses anteriores,
la mesa de ayuda por correo (MailDispatch), la calidad documental (HelpdeskSupervisor) y
el desempeño de agentes (AgentKpis). Si una fuente no está configurada o no tiene datos
ese mes, esa sección se muestra como no disponible en vez de detener todo el informe.

## Congelado vs. en vivo

El dashboard y el PPTX/XLSX siempre muestran el snapshot congelado. La pantalla
**Detalle en vivo** es la única excepción: consulta GLPI en el momento, para revisar el
estado actual de un rango de fechas sin esperar al cierre del siguiente mes.

Un SuperAdmin puede regenerar un mes ya cerrado desde Configuración; la versión anterior
no se pierde, queda archivada para auditoría.

## Modo presentación y descargas

"Presentar" abre un deck de pantalla completa navegable con flechas o la barra
espaciadora, listo para proyectar en una junta. "Descargar PPTX" genera el mismo
contenido como archivo de PowerPoint; "Descargar XLSX" entrega los datos crudos de cada
sección para quien quiera pivotearlos.

## Resumen ejecutivo con IA

El botón "Generar borrador con IA" le pide a Claude que redacte un resumen a partir de
las cifras ya calculadas del informe, nunca de tickets crudos. El resultado siempre se
guarda como borrador editable: nadie lo ve hasta que alguien lo revisa y lo guarda.

## Configuración (SuperAdmin)

En `/admin/reports/settings`:

- **Campos GLPI:** para regional, estado, municipio, sucursal e IDS (técnico), qué
  contenedor del plugin Additional Fields y qué campo técnico le corresponde a cada
  uno. No comparten necesariamente el mismo contenedor: IDS suele vivir aparte. Sin
  esto configurado, el informe sigue mostrando los KPIs a nivel ticket pero no puede
  desglosar por zona o técnico. "Proyecto" y "Cliente" no se mapean: esa información
  ya viene dada por la categoría del ticket.
- **Umbrales:** horas de SLA y cuántos meses hacia atrás incluye la tendencia.
- **IA:** habilitar el resumen ejecutivo, el modelo, y si se reusa la clave de
  HelpdeskSupervisor o una propia.
- **Correo:** destinatarios y nombre del remitente para "Enviar por correo".
