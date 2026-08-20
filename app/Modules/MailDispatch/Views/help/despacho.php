<?php
/**
 * Help Center content: cómo trabajar el despacho de correo.
 *
 * Rendered inside App\Modules\Core\Views\help\show. Each <section id="..."> must
 * match an entry in HelpCenter::topics()['despacho-correo']['sections'] so la
 * tabla de contenidos y el scrollspy coincidan.
 *
 * Los límites de adjuntos y las variables de plantilla se leen de la
 * configuración real para que la guía no se desfase del sistema.
 */
$cfg      = new \App\Modules\MailDispatch\Config\MailDispatch();
$settings = service('mailDispatchSettings');
$maxMb    = (int) round($cfg->maxTotalReplyBytes / 1048576);
$vars     = \App\Modules\MailDispatch\Services\TemplateRenderer::VARIABLES;
?>

<section id="que-es">
  <h2>Qué es el despacho de correo</h2>
  <p>
    El buzón compartido de la mesa de ayuda es de todos y de nadie: cuando cinco personas miran la
    misma bandeja de Outlook, dos contestan el mismo correo y un tercero se queda sin contestar
    porque supuso que alguien más lo haría.
  </p>
  <p>
    Este módulo convierte ese buzón en una cola con dueño. Cada hilo de correo se vuelve una
    <strong>conversación</strong> que alguien toma, trabaja, responde y cierra. Nexus sincroniza el
    buzón en ambos sentidos: lee lo que llega y registra lo que sale, así que el correo sigue siendo
    correo normal para el solicitante, que no se entera de nada.
  </p>
  <div class="help-callout help-callout-info">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <p>
      Puedes seguir usando Outlook y todo se sincroniza. Pero el trabajo hecho desde Nexus es el
      único que queda registrado con tu nombre. En
      <a href="<?= base_url('help/metricas-despacho') ?>">Cómo se miden tus métricas</a> está
      explicado con detalle por qué.
    </p>
  </div>
</section>

<section id="bandeja">
  <h2>La bandeja</h2>
  <p>
    Es la pantalla principal, en <strong>Despacho de Correo</strong>. A la izquierda la lista de
    conversaciones, a la derecha el panel de lectura: al hacer clic en una fila se abre ahí mismo,
    sin perder la lista.
  </p>

  <h3>Los filtros de arriba</h3>
  <table class="table" style="width:100%;">
    <thead><tr><th>Filtro</th><th>Qué muestra</th></tr></thead>
    <tbody>
      <tr><td><strong>Sin asignar</strong></td><td>Lo que llegó y nadie ha tomado. Es de donde sale el trabajo</td></tr>
      <tr><td><strong>Mías</strong></td><td>Tus conversaciones abiertas. Es tu lista de pendientes</td></tr>
      <tr><td><strong>Todas</strong></td><td>Todo lo abierto del equipo, tenga dueño o no</td></tr>
      <tr><td><strong>Autoarchivo</strong></td><td>Correo que una regla archivó sola, para revisión</td></tr>
      <tr><td><strong>Autogenerados</strong></td><td>Correo que se convirtió en ticket de GLPI automáticamente</td></tr>
      <tr><td><strong>Cerradas</strong></td><td>El histórico de lo ya resuelto</td></tr>
    </tbody>
  </table>

  <h3>Buscar</h3>
  <p>
    El buscador cubre el asunto, el nombre y el correo del solicitante, el folio de GLPI y
    <strong>el texto de los mensajes</strong>. Cuando el resultado sale por el cuerpo del correo y no
    por el asunto, la fila te muestra el fragmento donde coincidió, para que no tengas que abrirla
    para saber por qué apareció.
  </p>

  <h3>Cómo leer una fila</h3>
  <ul>
    <li>El <strong>estado</strong> a la derecha del asunto dice en qué punto va la conversación.</li>
    <li>Un <strong>clip</strong> indica que el hilo trae archivos adjuntos.</li>
    <li>Una marca de <strong>SLA</strong> aparece cuando lleva demasiado tiempo sin que nadie la
      tome. El tiempo se cuenta en horas hábiles si el horario de servicio está activo.</li>
    <li>La hora de la derecha es la de la <strong>última actividad</strong>, no la de llegada.</li>
  </ul>
</section>

<section id="tomar">
  <h2>Tomar y devolver</h2>

  <h3>Tomar una conversación</h3>
  <p>
    Desde la fila de la bandeja o desde el detalle, el botón <strong>Tomar</strong> te la asigna. A
    partir de ahí es tuya: aparece en <em>Mías</em> y en tu tarjeta del tablero de equipo.
  </p>
  <div class="help-callout help-callout-tip">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/></svg>
    <p>
      <strong>No hay forma de tomar dos veces la misma conversación.</strong> Si un compañero te
      ganó por un segundo, el sistema te lo dice con su nombre en lugar de dejar que ambos
      contesten. Es a propósito: es justo el problema que este módulo vino a resolver.
    </p>
  </div>

  <h3>Devolverla a la bandeja</h3>
  <p>
    Si la tomaste y resulta que no te toca, el botón <strong>Liberar</strong> (o
    <em>Devolver a la bandeja</em>) la regresa a <em>Sin asignar</em> para que alguien más la agarre.
    No tienes que pedirle a un despachador que la quite.
  </p>

  <h3>Asignar a otra persona</h3>
  <p>
    Reasignar a un compañero es una acción de <strong>despachador</strong>. Si tienes ese rol,
    encuentras el selector de agentes en el detalle de la conversación y también puedes hacerlo desde
    la pantalla <strong>Equipo</strong> sin salir de ahí.
  </p>
</section>

<section id="responder">
  <h2>Responder</h2>
  <p>
    El recuadro <strong>Responder desde Nexus</strong> está en el detalle de la conversación. Solo
    aparece si la conversación es tuya o si eres despachador: no se contesta el trabajo de otro por
    accidente.
  </p>

  <h3>A quién le llega</h3>
  <p>
    La respuesta se envía <strong>solo al solicitante</strong>. Si necesitas copiar a alguien más,
    escríbelo en <strong>Copia</strong>, o haz clic en cualquier dirección que aparezca en el hilo y
    se agrega sola.
  </p>

  <h3>Plantillas</h3>
  <p>
    Si contestas seguido lo mismo, guárdalo como plantilla en
    <a href="<?= base_url('dispatch/templates') ?>">Plantillas</a> y selecciónala al responder. Se
    inserta <strong>donde tengas el cursor</strong>, así que puedes escribir un saludo propio,
    insertar el bloque de siempre y seguir escribiendo.
  </p>
  <?php if ($vars !== []): ?>
    <p>Las variables se sustituyen solas con los datos del hilo:</p>
    <table class="table" style="width:100%;">
      <thead><tr><th>Variable</th><th>Se reemplaza por</th></tr></thead>
      <tbody>
        <?php foreach ($vars as $token => $label): ?>
          <tr><td><code><?= esc($token) ?></code></td><td><?= esc($label) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h3>Adjuntos</h3>
  <p>
    Puedes anexar hasta <strong><?= (int) $cfg->maxReplyAttachments ?> archivos</strong> por
    respuesta, con un total máximo de <strong><?= $maxMb ?> MB</strong>. Los adjuntos que llegan en
    los correos entrantes se descargan desde el mismo hilo.
  </p>

  <h3>Tu firma</h3>
  <p>
    Cada agente configura la suya en <a href="<?= base_url('dispatch/signature') ?>">Mi firma</a> y
    se agrega sola al final de tus respuestas. Si la dejas vacía, no se agrega nada.
  </p>

  <h3>Reenviar un mensaje</h3>
  <p>
    Cuando ya contestaste y te acuerdas de que faltaba copiar a alguien, no hace falta rehacer la
    respuesta: cada mensaje del hilo tiene <strong>Reenviar</strong>, que lo manda tal cual a quien
    indiques y lo deja registrado en la bitácora.
  </p>

  <?php if (! $settings->isSendEnabled()): ?>
    <div class="help-callout help-callout-warning">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <p>
        En este momento el <strong>envío desde Nexus está apagado</strong> en la configuración del
        módulo, así que el recuadro de responder no aparece. Mientras tanto se contesta desde
        Outlook, con la limitación de registro que eso implica.
      </p>
    </div>
  <?php endif; ?>
</section>

<section id="estados">
  <h2>Estados y notas</h2>

  <h3>Los estados que mueves tú</h3>
  <p>
    Casi nunca necesitas tocarlos a mano, porque el sistema los mueve solo: al responder pasa a
    <em>Respondida</em>, y cuando el solicitante contesta vuelve a <em>Esperando agente</em>. Aun
    así, puedes ponerlos manualmente cuando la realidad no coincida con lo que dice el hilo:
  </p>
  <ul>
    <?php foreach ($cfg->manualStatuses as $st): ?>
      <li><strong><?= esc($cfg->statusLabels[$st] ?? $st) ?></strong></li>
    <?php endforeach; ?>
  </ul>
  <p>
    El más útil es <strong>En atención</strong>: le avisa al resto del equipo que ya estás encima de
    ese caso aunque todavía no salga una respuesta.
  </p>

  <h3>Notas internas</h3>
  <p>
    La <strong>Nota interna</strong> se queda en Nexus: <strong>no se le envía al solicitante</strong>.
    Sirve para dejar dicho qué averiguaste, a quién escalaste o por qué el caso está detenido.
  </p>
  <div class="help-callout help-callout-tip">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/></svg>
    <p>
      Si te vas a tardar en un caso, una nota es la mejor inversión de treinta segundos del día:
      quien lo retome sabe en qué ibas, y de paso queda constancia de que trabajaste el asunto
      aunque todavía no salga respuesta.
    </p>
  </div>

  <h3>La bitácora</h3>
  <p>
    Abajo del hilo está la bitácora completa de la conversación: quién la tomó, quién la reasignó,
    cada cambio de estado, las notas y el cierre, con hora. Cuando algo no cuadra, ahí está la
    respuesta.
  </p>
</section>

<section id="cerrar">
  <h2>Cerrar y reabrir</h2>

  <h3>Cerrar</h3>
  <ol class="help-steps">
    <li><strong>Elige la disposición</strong>
      <p>Es obligatoria. Clasifica en qué terminó el caso y es lo que alimenta la gráfica de disposiciones de Métricas.</p></li>
    <li><strong>Pon el folio de GLPI si te lo pide</strong>
      <p>Algunas disposiciones lo exigen, y el sistema no te deja cerrar sin él. Es lo que permite después encontrar la conversación buscando el folio.</p></li>
    <li><strong>Agrega un comentario de cierre</strong>
      <p>Opcional, pero es lo que va a leer quien revise el caso dentro de tres meses.</p></li>
  </ol>

  <h3>Reabrir</h3>
  <p>
    Una conversación cerrada se reabre a mano con <strong>Reabrir</strong>, y también
    <strong>se reabre sola</strong> si el solicitante responde el correo. En ambos casos vuelve a
    <em>Esperando agente</em> conservando a su dueño: si era tuya, sigue siendo tuya.
  </p>
</section>

<section id="automaticos">
  <h2>Autoarchivo y autogenerados</h2>
  <p>
    Dos buzones aparte para el correo que el sistema procesó sin intervención humana. Ninguno de los
    dos cuenta como carga de nadie, pero <strong>los dos se revisan</strong>: son automatismos, y los
    automatismos se equivocan.
  </p>

  <h3>Autoarchivo</h3>
  <p>
    Correo que una regla identificó como ruido conocido (notificaciones, avisos automáticos,
    publicidad) y archivó sin molestar a nadie. Tu trabajo ahí es una pasada rápida:
  </p>
  <ul>
    <li><strong>Verificar</strong>: sí era ruido. Queda firmado con tu nombre y desaparece de la lista de pendientes por revisar.</li>
    <li><strong>Mover a la bandeja</strong>: la regla se equivocó y esto sí requiere atención. Vuelve a la cola normal como cualquier otro correo.</li>
  </ul>

  <h3>Autogenerados</h3>
  <p>
    Correo que se convirtió solo en ticket de GLPI. Cada uno muestra en qué punto va:
  </p>
  <table class="table" style="width:100%;">
    <thead><tr><th>Lo que ves</th><th>Qué significa</th><th>Qué haces</th></tr></thead>
    <tbody>
      <tr><td><strong>En cola</strong></td><td>Todavía no se crea el ticket</td><td>Nada, espera</td></tr>
      <tr><td><strong>Revisión</strong></td><td>Faltaron datos para crearlo</td><td>Completa título y descripción; se reencola solo</td></tr>
      <tr><td><strong>#12345</strong></td><td>El ticket se creó</td><td>Confirma que quedó bien y da <strong>Verificar</strong></td></tr>
      <tr><td><strong>Error</strong></td><td>Falló al crearlo en GLPI</td><td><strong>Reintentar</strong>; si vuelve a fallar, repórtalo</td></tr>
    </tbody>
  </table>
</section>

<section id="flujo">
  <h2>El día a día</h2>
  <p>Una rutina que funciona, si no tienes una propia:</p>
  <ol class="help-steps">
    <li><strong>Empieza por Mías, no por Sin asignar</strong>
      <p>Primero cierra o mueve lo que ya te comprometiste a atender. Tomar trabajo nuevo con seis casos tuyos parados no ayuda a nadie.</p></li>
    <li><strong>Atiende lo que está en Esperando agente</strong>
      <p>Ahí el solicitante ya respondió y está esperando. Es lo que más rápido se enfría.</p></li>
    <li><strong>Toma de Sin asignar, empezando por lo más viejo</strong>
      <p>La bandeja muestra arriba lo que lleva más tiempo esperando dueño. Ese orden ya es la prioridad.</p></li>
    <li><strong>Toma una a la vez</strong>
      <p>Apartar cinco conversaciones para trabajarlas después las esconde del resto del equipo sin que avancen. Toma la que vas a trabajar ahora.</p></li>
    <li><strong>Cierra en cuanto termines</strong>
      <p>Cerrar al final del día, en bloque y de memoria, es como se pierden los folios y las disposiciones correctas.</p></li>
    <li><strong>Da una pasada a Autoarchivo</strong>
      <p>Un minuto al día basta para que una regla mal calibrada no te esconda un caso real.</p></li>
  </ol>
  <p>
    Para ver cómo vas, tienes <a href="<?= base_url('dispatch/my-metrics') ?>">Mis métricas</a>: la
    tarjeta de arriba te dice qué traes en este momento y el resto, cómo te fue en el periodo.
  </p>
</section>

<section id="faq">
  <h2>Preguntas frecuentes</h2>

  <h3>¿Puedo ver conversaciones de otros agentes?</h3>
  <p>
    Sí, con el filtro <em>Todas</em>, y puedes abrirlas y leerlas completas. Lo que no puedes es
    responderlas ni reasignarlas si no son tuyas. Transparencia para consultar, dueño único para
    actuar.
  </p>

  <h3>Tomé una conversación por error, ¿cómo la suelto?</h3>
  <p>Con <strong>Liberar</strong>. Vuelve a Sin asignar de inmediato, sin pedirle permiso a nadie.</p>

  <h3>El solicitante escribió otra vez sobre un caso cerrado</h3>
  <p>
    No hagas una conversación nueva. El hilo se reabre solo y vuelve a ti en <em>Esperando agente</em>,
    con todo el historial anterior a la vista.
  </p>

  <h3>Mandé la respuesta al que no era</h3>
  <p>
    La respuesta siempre va al solicitante del hilo. Si el correcto era otro, usa
    <strong>Reenviar</strong> sobre el mensaje para hacérselo llegar, y deja una nota interna
    explicando qué pasó.
  </p>

  <h3>¿Puedo contestar desde Outlook?</h3>
  <p>
    Sí, y la conversación se actualiza sola. Pero esa respuesta no queda registrada con tu nombre,
    porque el correo no dice quién de la cuenta compartida la escribió. Si vas a contestar desde
    Nexus o desde Outlook es tu decisión; solo ten claro que la segunda no se ve en tus métricas.
  </p>

  <h3>No aparece el recuadro para responder</h3>
  <p>Casi siempre es una de tres: la conversación no es tuya, está cerrada, o el envío desde Nexus está apagado en la configuración del módulo.</p>

  <h3>No me deja cerrar</h3>
  <p>
    La disposición que elegiste exige folio de GLPI y está vacío. Ponlo y el cierre pasa.
  </p>

  <h3>¿Qué pasa si dos agentes abren la misma conversación al mismo tiempo?</h3>
  <p>
    Leerla no la aparta. Al primero que dé <strong>Tomar</strong> se le asigna, y al segundo se le
    avisa quién se la llevó. Nadie duplica trabajo sin enterarse.
  </p>
</section>
