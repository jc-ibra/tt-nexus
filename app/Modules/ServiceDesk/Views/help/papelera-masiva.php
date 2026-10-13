<?php
/**
 * Help Center content: Service Desk, papelera masiva.
 *
 * Rendered inside App\Modules\Core\Views\help\show. Each <section id="..."> must
 * match an entry in HelpCenter::topics()['papelera-masiva']['sections'] so the
 * table of contents and scrollspy line up.
 */
$cfg = config('App\Modules\ServiceDesk\Config\ServiceDesk');
?>

<section id="intro">
  <h2>Para qué sirve</h2>
  <p>
    Cuando hay tickets duplicados, de prueba o creados por error, mandarlos uno por uno a la papelera de
    GLPI es lento. Aquí subes un Excel con la columna <code><?= esc($cfg->ticketIdHeader) ?></code> y Nexus
    los manda a la papelera todos juntos.
  </p>
  <div class="help-callout help-callout-tip">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/></svg>
    <p>
      Los tickets <strong>no se borran</strong>: quedan en la papelera de GLPI y puedes devolverlos con
      <em>Restaurar lote</em>. Nexus nunca elimina un ticket de forma definitiva.
    </p>
  </div>
</section>

<section id="archivo">
  <h2>Cómo se llena el archivo</h2>
  <p>
    Solo necesitas una columna: <code><?= esc($cfg->ticketIdHeader) ?></code>, con el id numérico del ticket en
    GLPI, una fila por ticket. <code>MOTIVO</code> es opcional y sirve de referencia para quien revise el
    archivo; no se envía a GLPI. Puedes descargar la plantilla desde la pantalla de Papelera, o usar el
    archivo de resultado de un lote anterior.
  </p>
</section>

<section id="proceso">
  <h2>Cómo se aplica un lote</h2>
  <ol>
    <li>Sube el archivo con la casilla <strong>Simular primero</strong> marcada.</li>
    <li>Cuando la simulación diga <em>Completada</em>, revisa cuántos tickets irían a la papelera y descarga el resultado.</li>
    <li>Si todo está bien, pulsa <strong>Aplicar en GLPI</strong>. Se corre el mismo archivo, ahora de verdad.</li>
  </ol>
  <p>La simulación no toca GLPI y queda en el historial como evidencia de lo que se revisó.</p>
</section>

<section id="resultados">
  <h2>Qué significa cada resultado</h2>
  <table class="table" style="width:100%;">
    <thead>
      <tr><th>Resultado</th><th>Qué pasó</th></tr>
    </thead>
    <tbody>
      <tr><td><code>PAPELERA</code></td><td>El ticket se movió a la papelera y Nexus lo comprobó en GLPI.</td></tr>
      <tr><td><code>SIMULADO (IRIA A PAPELERA)</code></td><td>Ensayo: el ticket se movería, pero no se tocó.</td></tr>
      <tr><td><code>YA EN PAPELERA</code></td><td>El ticket ya estaba en la papelera. Se omite y cuenta como sin cambios.</td></tr>
      <tr><td><code>DESVIACION</code></td><td>GLPI respondió sin error pero el ticket no cambió. Casi siempre es que al usuario de la API le falta el permiso de eliminar tickets.</td></tr>
      <tr><td><code>ERROR</code></td><td>El ticket no existe o GLPI rechazó la operación. El mensaje viene en la misma celda.</td></tr>
    </tbody>
  </table>
</section>

<section id="restaurar">
  <h2>Cómo deshacer un lote</h2>
  <p>
    Abre el detalle del lote ya aplicado y pulsa <strong>Restaurar lote</strong>. Nexus saca de la papelera
    solo los tickets que ese lote movió (los marcados <code>PAPELERA</code>); los que ya estaban en la
    papelera antes, o que dieron error, no se tocan.
  </p>
</section>

<section id="cuidados">
  <h2>Antes de un lote grande</h2>
  <ul>
    <li>Si GLPI tiene activa la notificación de ticket eliminado, enviará un correo por cada ticket. Apágala mientras corre el lote.</li>
    <li>Los tickets que siguen abiertos también van a la papelera. La validación te avisa cuántos son para que lo confirmes.</li>
    <li>Corre siempre una simulación primero.</li>
  </ul>
</section>

<section id="faq">
  <h2>Preguntas frecuentes</h2>
  <h3>¿Por qué me dice que la papelera está deshabilitada?</h3>
  <p>Un administrador la activa en Configuración de Service Desk. Está apagada por defecto.</p>
  <h3>¿Puedo borrar tickets de forma definitiva?</h3>
  <p>No desde Nexus. Si hace falta purgar, se hace desde la papelera de GLPI.</p>
  <h3>Todo salió como DESVIACION, ¿qué reviso?</h3>
  <p>Pide a quien administra GLPI que el perfil del usuario de la API tenga el permiso Ticket: Eliminar.</p>
</section>
