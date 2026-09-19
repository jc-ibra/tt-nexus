<?php
/**
 * Help Center content: qué es el informe ejecutivo mensual y de dónde sale
 * cada número. Rendered inside App\Modules\Core\Views\help\show.
 */
?>

<section id="que-es">
  <h2>Qué es el informe ejecutivo mensual</h2>
  <p>
    Cada mes cerrado se congela en un <strong>informe</strong>: un conjunto fijo de números que ya no
    cambia, aunque los tickets sigan actualizándose en GLPI después. Así, cuando dirección compara
    agosto contra septiembre, ambos meses reflejan el mismo momento de corte.
  </p>
  <p>
    El informe reúne cinco fuentes: tickets de GLPI, la tendencia contra meses anteriores, la mesa de
    ayuda por correo (MailDispatch), la calidad documental (HelpdeskSupervisor) y el desempeño de
    agentes (AgentKpis). Si una fuente no está configurada o no tiene datos ese mes, esa sección se
    muestra como no disponible en vez de detener todo el informe.
  </p>
</section>

<section id="congelado-vs-vivo">
  <h2>Congelado vs. en vivo</h2>
  <p>
    El dashboard y el PPTX/XLSX siempre muestran el snapshot congelado. La pantalla
    <strong>Detalle en vivo</strong> es la única excepción: consulta GLPI en el momento, para revisar
    el estado actual de un rango de fechas sin esperar al cierre del siguiente mes.
  </p>
  <p>
    Un SuperAdmin puede regenerar un mes ya cerrado desde Configuración; la versión anterior no se
    pierde, queda archivada para auditoría.
  </p>
</section>

<section id="modo-presentacion">
  <h2>Modo presentación y descargas</h2>
  <p>
    "Presentar" abre un deck de pantalla completa navegable con flechas o la barra espaciadora, listo
    para proyectar en una junta. "Descargar PPTX" genera el mismo contenido como archivo de PowerPoint;
    "Descargar XLSX" entrega los datos crudos de cada sección para quien quiera pivotearlos.
  </p>
</section>

<section id="resumen-ia">
  <h2>Resumen ejecutivo con IA</h2>
  <p>
    El botón "Generar borrador con IA" le pide a Claude que redacte un resumen a partir de las cifras
    ya calculadas del informe, nunca de tickets crudos. El resultado siempre se guarda como borrador
    editable: nadie lo ve hasta que alguien lo revisa y lo guarda.
  </p>
</section>
