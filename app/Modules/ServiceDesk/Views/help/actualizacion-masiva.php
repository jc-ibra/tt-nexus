<?php
/**
 * Help Center content: Service Desk, actualización y cierre masivo.
 *
 * Rendered inside App\Modules\Core\Views\help\show. Each <section id="..."> must
 * match an entry in HelpCenter::topics()['actualizacion-masiva']['sections'] so
 * the table of contents and scrollspy line up.
 */
$cfg = config('App\Modules\ServiceDesk\Config\ServiceDesk');
?>

<section id="intro">
  <h2>Para qué sirve</h2>
  <p>
    A veces los tickets se generan con datos mínimos, o con la categoría equivocada, y después
    llega un Excel con la información correcta y la instrucción de cerrarlos. Esta sección hace
    justo eso: tomas <strong>el mismo archivo del importador</strong>, le pones el número de ticket
    en la columna <code><?= esc($cfg->ticketIdHeader) ?></code>, y Nexus corrige en GLPI los datos
    que traiga cada fila y cierra los que lo pidan.
  </p>
  <p>La diferencia con el alta masiva es de una línea:</p>
  <ul>
    <li><strong>Alta masiva</strong> (<em>Service Desk</em>): dejas <code><?= esc($cfg->ticketIdHeader) ?></code> vacío y el sistema crea los tickets.</li>
    <li><strong>Actualizar y cerrar</strong>: llenas <code><?= esc($cfg->ticketIdHeader) ?></code> y el sistema corrige tickets que ya existen.</li>
  </ul>
  <div class="help-callout help-callout-tip">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/></svg>
    <p>
      El archivo de resultado que descargas después de una importación ya trae
      <code><?= esc($cfg->ticketIdHeader) ?></code> lleno. Puedes editarlo y volver a subirlo aquí
      sin preparar nada: es el camino más corto para corregir un lote que acabas de importar.
    </p>
  </div>
</section>

<section id="reglas">
  <h2>Cómo se llena el archivo</h2>
  <p>
    Solo hay una regla que memorizar: <strong>lo que llenas se escribe, lo que dejas vacío no se
    toca</strong>. Por eso puedes entregar un archivo con dos columnas corregidas y el resto en blanco.
  </p>
  <table class="table" style="width:100%;">
    <thead>
      <tr><th>Si en la celda pones</th><th>Nexus hace</th></tr>
    </thead>
    <tbody>
      <tr>
        <td><code><?= esc($cfg->ticketIdHeader) ?></code> con el id del ticket</td>
        <td>Identifica qué ticket corregir. Es la única columna obligatoria.</td>
      </tr>
      <tr>
        <td>Un valor en cualquier columna</td>
        <td>Lo escribe en GLPI encima de lo que hubiera.</td>
      </tr>
      <tr>
        <td>La celda vacía</td>
        <td>No toca ese campo del ticket.</td>
      </tr>
      <tr>
        <td><code><?= esc($cfg->clearSentinel) ?></code></td>
        <td>Borra el valor que el ticket tenga en ese campo.</td>
      </tr>
      <tr>
        <td><strong>ESTATUS</strong> = RESUELTO o CERRADO</td>
        <td>Registra la solución en GLPI y cierra el ticket.</td>
      </tr>
      <tr>
        <td><strong><?= esc($cfg->solutionHeader) ?></strong></td>
        <td>Usa ese texto como solución. Si va vacía, usa el texto default del administrador.</td>
      </tr>
    </tbody>
  </table>
  <div class="help-callout help-callout-warning">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <p>
      Escribir <strong>N/A</strong>, <strong>NAN</strong> o <strong>NONE</strong> en una celda
      equivale a dejarla vacía: Nexus lo lee como "no tocar", no como el texto literal. Si de verdad
      quieres borrar el campo, usa <code><?= esc($cfg->clearSentinel) ?></code>.
    </p>
  </div>
</section>

<section id="proceso">
  <h2>Cómo se aplica un lote</h2>
  <ol class="help-steps">
    <li>
      <strong>Prepara el archivo</strong>
      <p>
        Usa el template descargado en Service Desk o el archivo de resultado de una importación
        previa. Llena <code><?= esc($cfg->ticketIdHeader) ?></code> y las columnas que quieras corregir.
      </p>
    </li>
    <li>
      <strong>Súbelo con la casilla Simular activada</strong>
      <p>
        Es la opción recomendada y viene marcada por defecto. Nexus lee todos los tickets, calcula
        cambio por cambio y te entrega el reporte completo <strong>sin escribir nada en GLPI</strong>.
      </p>
    </li>
    <li>
      <strong>Revisa el archivo de resultado</strong>
      <p>
        Descárgalo desde el detalle de la carga. Trae dos columnas nuevas: <strong>RESULTADO</strong>,
        con lo que pasó en esa fila, y <strong>CAMBIOS</strong>, con el detalle campo por campo
        (por ejemplo <em>CATEGORIA: "Soporte" a "Redes"</em>).
      </p>
    </li>
    <li>
      <strong>Pulsa Aplicar en GLPI</strong>
      <p>
        En el detalle de la simulación terminada aparece el botón <strong>Aplicar en GLPI</strong>. Corre
        el mismo archivo que acabas de revisar, ahora escribiendo de verdad: no tienes que volver a
        subir nada. La simulación se conserva como evidencia de lo que revisaste.
      </p>
    </li>
  </ol>
  <p>
    Las cargas se procesan en cola, una a la vez y en el orden en que se encolaron, compartiendo turno
    con las altas masivas. Si acabas de encolar y el estado dice <em>En cola</em>, solo está esperando
    su turno.
  </p>
</section>

<section id="resultados">
  <h2>Qué significa cada resultado</h2>
  <table class="table" style="width:100%;">
    <thead>
      <tr><th>RESULTADO</th><th>Qué pasó</th></tr>
    </thead>
    <tbody>
      <tr><td><strong>ACTUALIZADO</strong></td><td>Se escribieron los datos de la fila y se verificaron.</td></tr>
      <tr><td><strong>RESUELTO</strong> o <strong>CERRADO</strong></td><td>Además de escribir los datos, se registró la solución y el ticket quedó en ese estatus. La etiqueta es el mismo que pusiste en la columna ESTATUS.</td></tr>
      <tr><td><strong>SIN CAMBIOS</strong></td><td>El ticket ya está como dice el Excel. No había nada que cambiar.</td></tr>
      <tr><td><strong>SIMULADO (CAMBIARIA)</strong></td><td>Ensayo: ese ticket sí cambiaría. La columna CAMBIOS dice exactamente qué. No se tocó nada.</td></tr>
      <tr><td><strong>SIMULADO (CAMBIARIA Y LO DEJARIA EN ...)</strong></td><td>Igual que el anterior y, además, el ticket pasaría al estatus que indica.</td></tr>
      <tr><td><strong>DESVIACION</strong></td><td>Se escribió el valor pero GLPI no lo conservó. Ver abajo.</td></tr>
      <tr><td><strong>ERROR</strong></td><td>La fila no se pudo aplicar. El motivo viene en la misma celda y en la bitácora.</td></tr>
    </tbody>
  </table>
  <div class="help-callout help-callout-info">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <p>
      <strong>DESVIACION</strong> casi siempre significa que una <em>regla de negocio</em> de GLPI
      reescribió el valor justo después de que Nexus lo guardó. No es un error de la carga: es GLPI
      imponiendo su propia regla, y el mensaje te dice qué valor quedó en su lugar. El caso más común
      son las reglas "si la categoría es X, asigna al técnico Y", en
      <em>Administración &gt; Reglas &gt; Reglas de tickets</em>. Revísalas antes de reintentar, porque
      volver a subir el mismo archivo dará el mismo resultado mientras la regla siga activa.
      Lo demás de la fila sí se aplicó: la bitácora lista qué se guardó y qué no.
    </p>
  </div>
</section>

<section id="cierre">
  <h2>Qué pasa al cerrar</h2>
  <p>
    Cerrar un ticket no es solo cambiarle el estatus. Nexus registra primero una
    <strong>solución</strong> en GLPI y después fija el estatus y las fechas, que es como GLPI espera
    que se cierre un ticket. Un ticket cerrado sin solución sale marcado como anómalo en los reportes
    de GLPI, y por eso este flujo nunca lo hace de la forma corta.
  </p>
  <ul>
    <li>El texto de la solución sale de la columna <strong><?= esc($cfg->solutionHeader) ?></strong> de esa fila; si va vacía, del texto default que configuró el administrador.</li>
    <li>Si el ticket <strong>ya tenía una solución</strong>, no se le agrega otra, salvo que la fila traiga su propio texto.</li>
    <li>
      La fecha de cierre sale de <strong>FECHA_CIERRE</strong>; si la dejas vacía se conserva la
      que ya tenía el ticket y, si no tenía, se usa la fecha de aplicación. Sirve para cerrar
      tickets viejos con la fecha en que realmente se atendieron.
    </li>
  </ul>

  <h3>Tickets que ya estaban cerrados</h3>
  <p>
    GLPI no deja editar un ticket cerrado. Cuando una fila tiene que corregir uno, Nexus lo
    <strong>reabre</strong>, le escribe los datos y lo <strong>vuelve a cerrar conservando su fecha de
    cierre original</strong>. Todo eso queda en el historial del ticket, así que si alguien revisa
    después verá el paso completo. Es el comportamiento normal para corregir tickets cerrados con
    datos mínimos.
  </p>
</section>

<section id="cuidados">
  <h2>Antes de correr un lote grande</h2>
  <div class="help-callout help-callout-warning">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <p>
      <strong>Cerrar tickets dispara las notificaciones por correo de GLPI a los solicitantes.</strong>
      Cerrar cincuenta tickets significa cincuenta correos saliendo. Si vas a aplicar un lote grande,
      pide a Sistemas que apague las notificaciones de tickets en GLPI mientras corre y las vuelva a
      encender al terminar.
    </p>
  </div>
  <p>Lista corta antes de aplicar:</p>
  <ul>
    <li>Corriste la simulación y revisaste el archivo de resultado.</li>
    <li>Las filas con <strong>ERROR</strong> están corregidas o quitadas.</li>
    <li>Sabes cuántos tickets se van a cerrar y avisaste si eso genera correos.</li>
    <li>No hay dos filas con el mismo <code><?= esc($cfg->ticketIdHeader) ?></code>: la validación las rechaza, pero es más rápido detectarlo antes.</li>
  </ul>
</section>

<section id="faq">
  <h2>Preguntas frecuentes</h2>
  <div class="help-faq">
    <details>
      <summary>
        No veo la sección Actualizar y cerrar
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          Viene deshabilitada de fábrica porque sobrescribe datos en GLPI. Un administrador la
          habilita en Configuración de Service Desk, pestaña Actualización masiva.
        </p>
      </div>
    </details>
    <details>
      <summary>
        Cambié la categoría pero el título del ticket sigue con el cliente anterior
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          Es a propósito. El título se arma como <em>CLIENTE - SUCURSAL - TITULO</em> y solo se
          rearma si llenas la columna <strong>TITULO</strong>. Si cambias nada más la categoría,
          Nexus no adivina cómo rehacer el título que ya tenía. Llena TITULO y se rearma completo,
          o pide al administrador que active la rehomologación automática.
        </p>
      </div>
    </details>
    <details>
      <summary>
        Cambié el técnico y el ticket quedó con el técnico anterior, ¿por qué?
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          Si el ticket quedó con el técnico de la columna <strong>ASIGNADO_A</strong> pero además
          conservó al anterior, era un defecto ya corregido: vuelve a correr el mismo Excel y quedará
          solo el técnico que pediste.
        </p>
        <p>
          Si en cambio la fila salió como <strong>DESVIACION</strong> diciendo que quedó otro técnico,
          es una <strong>regla de negocio de GLPI</strong>. Muchas instalaciones tienen reglas del tipo
          "si la categoría es X, asigna al técnico Y", y esa regla corre después de que Nexus escribe,
          así que gana. Revísalas en GLPI en <em>Administración &gt; Reglas &gt; Reglas de tickets</em>.
          Volver a subir el archivo dará el mismo resultado mientras la regla siga activa.
        </p>
      </div>
    </details>
    <details>
      <summary>
        ¿Puedo dejar un ticket sin técnico asignado?
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          Sí: pon <code><?= esc($cfg->clearSentinel) ?></code> en <strong>ASIGNADO_A</strong>. Ten en
          cuenta que si hay una regla de negocio que asigna por categoría, GLPI puede volver a
          asignarlo en la siguiente edición del ticket.
        </p>
      </div>
    </details>
    <details>
      <summary>
        Subí el archivo y todas las filas dicen SIN CAMBIOS
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          Significa que lo que traía el archivo ya coincidía con lo que tienen los tickets en GLPI.
          Revisa que estés editando la copia correcta y que los valores nuevos estén en las columnas
          que crees. Si una fila solo trae el número de ticket, la validación te lo avisa antes de
          encolar.
        </p>
      </div>
    </details>
    <details>
      <summary>
        La simulación terminó, ¿ya se cambió algo en GLPI?
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          No. Una simulación solo lee. Los tickets siguen exactamente igual que antes, aunque la
          bitácora liste cambios: esos son los que <em>se harían</em>. Nada se escribe hasta que pulsas
          <strong>Aplicar en GLPI</strong>.
        </p>
      </div>
    </details>
    <details>
      <summary>
        Cerré tickets viejos, ¿quedan cerrados con la fecha que puse o con la de hoy?
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          Con la que pusiste en <strong>FECHA_CIERRE</strong>. GLPI, por su cuenta, sella todo
          cierre con la hora en que ocurre, así que Nexus la corrige justo después para que tus
          reportes de cierre reflejen cuándo se atendió el ticket de verdad y no el día de la
          carga. La columna CAMBIOS te lo indica cuando pasa.
        </p>
        <p>
          Si más adelante corriges algún dato de ese ticket ya cerrado, se reabre y se vuelve a
          cerrar conservando esa misma fecha.
        </p>
      </div>
    </details>
    <details>
      <summary>
        No me dejó cambiar la FECHA_APERTURA de un ticket
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          GLPI no acepta una fecha de apertura posterior a la <strong>fecha límite del SLA</strong> del
          ticket. Si el ticket vence el 12 de agosto y le pones una apertura del 17, GLPI descarta ese
          campo, guarda todo lo demás y la fila sale como <strong>DESVIACION</strong> explicándolo.
        </p>
        <p>
          Para arreglarlo: pon una fecha anterior al vencimiento del SLA, o ajusta el SLA del ticket
          en GLPI antes de correr la carga.
        </p>
      </div>
    </details>
    <details>
      <summary>
        Dice que hay filas con problema, ¿cómo sé cuáles son?
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          En el detalle de la carga aparece la tarjeta <strong>Filas con problema</strong>, que lista
          solo esas. Cada línea empieza con la fila del Excel entre corchetes y el número de ticket,
          por ejemplo <code>[2] #19: DESVIACION...</code>. El mismo detalle viene en la columna
          <strong>RESULTADO</strong> del archivo de resultado, por si prefieres filtrarlo en Excel.
        </p>
      </div>
    </details>
    <details>
      <summary>
        ¿Se puede deshacer una carga aplicada?
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          No hay un botón de deshacer. Lo que sí queda es el rastro: la columna CAMBIOS del archivo de
          resultado y la bitácora de la carga guardan el valor anterior y el nuevo de cada campo, así
          que puedes armar un archivo de reversa con los valores originales. Por eso conviene correr
          siempre la simulación primero.
        </p>
      </div>
    </details>
  </div>
</section>
