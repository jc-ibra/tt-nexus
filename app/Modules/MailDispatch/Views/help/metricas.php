<?php
/**
 * Help Center content: cómo se miden las métricas del despacho de correo.
 *
 * Rendered inside App\Modules\Core\Views\help\show. Each <section id="..."> must
 * match an entry in HelpCenter::topics()['metricas-despacho']['sections'] so the
 * table of contents and the scrollspy line up.
 *
 * The prose mirrors docs/guias/maildispatch-metricas.md, which is the
 * printable version handed out as PDF. Keep both in step when the rules change.
 */
$calendar = service('mailDispatchCalendar');
$settings = service('mailDispatchSettings');
?>

<section id="origen">
  <h2>De dónde salen los números</h2>
  <p>
    No existe un cronómetro aparte ni un sistema de vigilancia. Todo se calcula con dos cosas que el
    módulo ya guarda mientras trabajas.
  </p>

  <h3>Las marcas de tiempo de cada conversación</h3>
  <p>Cada hilo guarda cinco fechas:</p>
  <table class="table" style="width:100%;">
    <thead>
      <tr><th>Marca</th><th>Cuándo se pone</th><th>Se vuelve a poner</th></tr>
    </thead>
    <tbody>
      <tr><td><strong>Recibido</strong></td><td>Cuando llega el primer correo del solicitante</td><td>No</td></tr>
      <tr><td><strong>Asignado</strong></td><td>La primera vez que alguien la toma o te la asignan</td><td>No, queda la primera</td></tr>
      <tr><td><strong>Primera respuesta</strong></td><td>La primera vez que sale una respuesta del hilo</td><td>No, queda la primera</td></tr>
      <tr><td><strong>Última actividad</strong></td><td>Cada vez que entra o sale un mensaje</td><td>Sí, siempre la más reciente</td></tr>
      <tr><td><strong>Cerrado</strong></td><td>Cuando se cierra con una disposición</td><td>Se borra si se reabre</td></tr>
    </tbody>
  </table>

  <h3>La bitácora de acciones</h3>
  <p>
    Cada vez que haces algo deliberado en el módulo se guarda un renglón con tu nombre, la
    conversación y la hora. Se registran: responder, reenviar, tomar, asignar o reasignar, liberar,
    cambiar el estado, cerrar, reabrir, escribir una nota interna, y verificar un autoarchivo o un
    ticket autogenerado.
  </p>
  <p>
    Es la misma bitácora que ves en el detalle de cada conversación. Las pantallas de métricas
    simplemente la cuentan agrupada por persona y por periodo.
  </p>

  <div class="help-callout help-callout-warning">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <p>
      <strong>Lo que queda con tu nombre es lo que hiciste en Nexus.</strong> Si respondes un correo
      directamente desde Outlook, el módulo lo detecta al sincronizar y sí actualiza la conversación,
      pero la acción se guarda a nombre del sistema, no tuyo. Está explicado a detalle en las
      preguntas frecuentes.
    </p>
  </div>
</section>

<section id="reloj">
  <h2>El reloj: horas hábiles</h2>
  <p>
    Los tiempos de estas pantallas pueden medirse en <strong>horas hábiles</strong> en lugar de horas
    de reloj, si el horario de servicio está activado en la configuración del módulo.
  </p>

  <?php if ($calendar->isEnabled()): ?>
    <div class="help-callout help-callout-info">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <p>
        En este momento el horario de servicio <strong>está activo</strong>:
        <?= esc($calendar->summary()) ?>. Todos los tiempos que veas están contados dentro de esa
        ventana.
      </p>
    </div>
  <?php else: ?>
    <div class="help-callout help-callout-info">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <p>
        En este momento el horario de servicio <strong>está apagado</strong>, así que los tiempos se
        cuentan de reloj corrido: las noches y los fines de semana sí suman.
      </p>
    </div>
  <?php endif; ?>

  <p>Con el horario activo:</p>
  <ul>
    <li>Un correo que llega el viernes a las 18:50 y se contesta el lunes a las 09:10 no consume el
      fin de semana. Consume los minutos hábiles del viernes más los del lunes.</li>
    <li>El reloj del SLA se detiene fuera del horario. Si son las 22:00, tu contador de
      <em>fuera de SLA</em> no está creciendo.</li>
    <li>Un "día" significa un <strong>día de servicio</strong>, no 24 horas. Si tu jornada es de 10
      horas, <code>2 d</code> son 20 horas hábiles, no 48.</li>
    <li>Los días festivos y las excepciones cargadas en el calendario tampoco cuentan.</li>
  </ul>
  <p>
    Puedes confirmarlo siempre en el encabezado de <strong>Equipo</strong>: si aparece la línea
    "Tiempos en horas hábiles", está activo.
  </p>
</section>

<section id="estados">
  <h2>Estados de una conversación</h2>
  <p>Entender los estados evita la mayoría de las dudas sobre los conteos.</p>
  <table class="table" style="width:100%;">
    <thead><tr><th>Estado</th><th>Qué significa</th></tr></thead>
    <tbody>
      <tr><td><strong>Nueva</strong></td><td>Llegó y nadie la ha tomado</td></tr>
      <tr><td><strong>Asignada</strong></td><td>Tiene dueño, todavía sin trabajarse</td></tr>
      <tr><td><strong>En atención</strong></td><td>El agente está trabajando en ella</td></tr>
      <tr><td><strong>Respondida</strong></td><td>Salió una respuesta y esperamos al solicitante</td></tr>
      <tr><td><strong>Esperando agente</strong></td><td>El solicitante contestó y ahora la pelota es nuestra</td></tr>
      <tr><td><strong>Cerrada</strong></td><td>Se cerró con una disposición</td></tr>
      <tr><td><strong>Autoarchivo</strong></td><td>Una regla la archivó sin intervención humana</td></tr>
      <tr><td><strong>Autogenerado</strong></td><td>Se convirtió en ticket de GLPI automáticamente</td></tr>
    </tbody>
  </table>

  <h3>Movimientos que ocurren solos</h3>
  <ul>
    <li>Si respondes, la conversación pasa a <strong>Respondida</strong>.</li>
    <li>Si el solicitante contesta un hilo respondido, asignado o en atención, pasa sola a
      <strong>Esperando agente</strong>. No es un castigo: es la señal de que vuelve a requerir tu
      atención.</li>
    <li>Si el solicitante contesta un hilo <strong>cerrado</strong>, se reabre en Esperando agente
      conservando a su dueño, y se borra su fecha de cierre.</li>
  </ul>

  <div class="help-callout help-callout-tip">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/></svg>
    <p>
      <strong>Autoarchivo y Autogenerado no cuentan como carga de nadie.</strong> No aparecen en tus
      conversaciones en curso ni en el trabajo del equipo, porque nadie las está trabajando. Solo
      aplican a conversaciones que no tenían dueño.
    </p>
  </div>
</section>

<section id="equipo">
  <h2>La pantalla Equipo</h2>
  <p>
    Es la vista en vivo del despachador: qué trae cada quien <strong>en este momento</strong>. Se
    actualiza sola cada 30 segundos. Casi todo aquí es estado actual, no histórico. La misma tarjeta
    que el despachador ve de ti aparece arriba de tus propias métricas, en
    <strong>Mis métricas</strong>.
  </p>

  <h3>Barra superior</h3>
  <table class="table" style="width:100%;">
    <thead><tr><th>Indicador</th><th>Qué cuenta</th></tr></thead>
    <tbody>
      <tr><td><strong>Abiertas</strong></td><td>Todas las conversaciones activas: las de los agentes, las sin asignar y las de agentes dados de baja</td></tr>
      <tr><td><strong>Sin responder</strong></td><td>De las abiertas con dueño, las que todavía no tienen primera respuesta</td></tr>
      <tr><td><strong>Fuera de SLA</strong></td><td>De las sin responder, las que ya pasaron el tiempo de primera respuesta acordado<?= $settings->slaFirstResponseMinutes() > 0 ? ' (' . (int) $settings->slaFirstResponseMinutes() . ' min)' : '' ?></td></tr>
      <tr><td><strong>Sin actividad</strong></td><td>Agentes que llevan 4 horas hábiles o más sin registrar ninguna acción</td></tr>
      <tr><td><strong>Agentes</strong></td><td>Agentes activos en el módulo</td></tr>
    </tbody>
  </table>

  <h3>Tarjeta "Sin asignar"</h3>
  <p>
    Conversaciones que llegaron a la bandeja y nadie ha tomado. No incluye los hilos que nosotros
    iniciamos hacia afuera, ni los autoarchivados, ni los autogenerados. "La más antigua" es cuánto
    lleva esperando la más vieja del montón, y se pone en ámbar o en rojo según el tiempo de
    asignación acordado<?= $settings->slaUnassignedMinutes() > 0 ? ' (' . (int) $settings->slaUnassignedMinutes() . ' min)' : '' ?>.
  </p>

  <h3>Tarjeta de cada agente</h3>
  <table class="table" style="width:100%;">
    <thead><tr><th>Dato</th><th>Qué cuenta exactamente</th></tr></thead>
    <tbody>
      <tr><td><strong>En curso</strong></td><td>Tus conversaciones abiertas ahora mismo. Excluye cerradas, autoarchivo y autogenerado</td></tr>
      <tr><td><strong>Sin responder</strong></td><td>De esas, las que aún no tienen primera respuesta</td></tr>
      <tr><td><strong>En espera</strong></td><td>De esas, las que están en estado Esperando agente</td></tr>
      <tr><td><strong>Fuera de SLA</strong></td><td>De las sin responder, las que ya rebasaron el tiempo de primera respuesta</td></tr>
      <tr><td><strong>Cerradas hoy</strong></td><td>Conversaciones tuyas cerradas desde las 00:00 de hoy</td></tr>
      <tr><td><strong>Sin movimiento</strong></td><td>Hace cuánto se movió por última vez la más rezagada de tus conversaciones abiertas</td></tr>
      <tr><td><strong>Sin actividad</strong></td><td>Hace cuánto registraste tú tu última acción, en cualquier conversación</td></tr>
    </tbody>
  </table>

  <p>Las dos últimas responden preguntas distintas y es normal que no coincidan:</p>
  <ul>
    <li><strong>Sin movimiento</strong> habla de los hilos. Puedes haber contestado diez
      conversaciones hoy y aun así traer una desde hace tres días sin tocar. Eso es lo que marca.</li>
    <li><strong>Sin actividad</strong> habla de la persona. Es la única de las dos que dice algo si
      no traes nada abierto.</li>
  </ul>
  <p>
    El color de <em>Sin actividad</em> es verde con menos de 2 horas hábiles, ámbar de 2 a 4, y rojo
    de 4 en adelante. El color de la tarjeta completa se decide por la carga: rojo si tienes algo
    fuera de SLA, ámbar si acumulas 5 o más sin responder, azul si traes trabajo, gris si estás
    libre.
  </p>

  <h3>Actividad reciente</h3>
  <p>
    El listado de la derecha muestra las últimas asignaciones, reasignaciones, liberaciones, cierres
    y reaperturas del equipo, con quién las hizo. Las respuestas y las notas internas no aparecen ahí
    para no saturar el listado, pero sí cuentan en tus métricas. Cuando dice "El sistema", el
    movimiento lo hizo la sincronización de correo y no una persona.
  </p>
</section>

<section id="metricas">
  <h2>La pantalla Métricas</h2>
  <p>Aquí sí es histórico: todo se calcula sobre un rango de fechas.</p>

  <h3>Rango y filtros</h3>
  <ul>
    <li>Si no eliges fechas, el rango son los <strong>últimos 30 días</strong>.</li>
    <li>"Desde" empieza a las 00:00 de ese día y "Hasta" termina a las 23:59.</li>
    <li>El filtro de agente afecta a los indicadores de arriba, a las disposiciones y a la gráfica
      diaria. <strong>No afecta</strong> a Destacados ni a la tabla de actividad por agente: esas dos
      siempre muestran a todo el equipo, porque su función es comparar.</li>
  </ul>

  <h3>Indicadores superiores</h3>
  <table class="table" style="width:100%;">
    <thead><tr><th>Indicador</th><th>Qué mide</th></tr></thead>
    <tbody>
      <tr><td><strong>Backlog sin asignar</strong></td><td>Conversaciones sin dueño ahora mismo. No depende del rango</td></tr>
      <tr><td><strong>Recibidas</strong></td><td>Conversaciones cuyo primer correo llegó dentro del rango</td></tr>
      <tr><td><strong>Cerradas</strong></td><td>Conversaciones cerradas dentro del rango</td></tr>
      <tr><td><strong>Prom. primera asignación</strong></td><td>Tiempo promedio entre que llega y que alguien la toma</td></tr>
      <tr><td><strong>Prom. primera respuesta</strong></td><td>Tiempo promedio entre que llega y que sale la primera respuesta</td></tr>
    </tbody>
  </table>
  <p>
    Los dos promedios se calculan sobre las conversaciones <strong>recibidas</strong> dentro del rango
    que ya tienen esa marca. Una conversación recibida el último día del rango y contestada al día
    siguiente todavía no entra en el promedio.
  </p>

  <h3>Destacados del periodo</h3>
  <table class="table" style="width:100%;">
    <thead><tr><th>Destacado</th><th>Cómo se gana</th></tr></thead>
    <tbody>
      <tr><td><strong>Mayor carga actual</strong></td><td>Quien trae más conversaciones abiertas en este momento</td></tr>
      <tr><td><strong>Más actividad</strong></td><td>Quien registró más acciones en el rango</td></tr>
      <tr><td><strong>Más cierres</strong></td><td>Quien cerró más conversaciones propias en el rango</td></tr>
      <tr><td><strong>Respuesta más rápida</strong></td><td>Mejor promedio de primera respuesta en el rango</td></tr>
    </tbody>
  </table>
  <p>
    <strong>No hay un "mejor agente" general, y es deliberado.</strong> Estas cuatro preguntas casi
    nunca apuntan a la misma persona: quien más carga trae no es quien más rápido contesta, y quien
    más cierra no siempre es quien más se mueve. Combinarlas en un solo puntaje escondería justo lo
    que hay que ver.
  </p>
  <p>
    El de respuesta más rápida exige <strong>mínimo 3 conversaciones contestadas</strong> en el rango.
    Con una sola conversación contestada en dos minutos nadie gana, porque no sería una medición.
  </p>

  <h3>Tabla "Actividad por agente"</h3>
  <table class="table" style="width:100%;">
    <thead><tr><th>Columna</th><th>Qué cuenta</th><th>Periodo</th></tr></thead>
    <tbody>
      <tr><td><strong>Abiertas ahora</strong></td><td>Conversaciones activas que traes en este momento</td><td>Actual</td></tr>
      <tr><td><strong>Cerradas</strong></td><td>Conversaciones tuyas cerradas</td><td>Rango</td></tr>
      <tr><td><strong>Respuestas</strong></td><td>Respuestas que enviaste desde Nexus</td><td>Rango</td></tr>
      <tr><td><strong>Asignaciones</strong></td><td>Veces que tomaste o reasignaste una conversación</td><td>Rango</td></tr>
      <tr><td><strong>Prom. 1ª resp.</strong></td><td>Promedio de primera respuesta de tus conversaciones recibidas en el rango</td><td>Rango</td></tr>
      <tr><td><strong>Acciones</strong></td><td>Todo lo que registraste en el módulo</td><td>Rango</td></tr>
    </tbody>
  </table>
  <p>Tres detalles que explican diferencias que parecen errores:</p>
  <ul>
    <li><strong>Abiertas ahora no depende del rango.</strong> Si cambias las fechas, esa columna no se
      mueve. Es el mismo número que "en curso" en la pantalla Equipo.</li>
    <li><strong>Cerradas se acredita al dueño, no a quien cerró.</strong> Si un despachador cierra un
      hilo tuyo, te suma a ti. La acción de cerrar se le cuenta a él en Acciones.</li>
    <li><strong>Prom. 1ª resp. se agrupa por el dueño actual.</strong> Si una conversación se reasignó
      después de la primera respuesta, ese tiempo cuenta para quien la tiene hoy, no para quien
      contestó.</li>
  </ul>
  <p>
    El tooltip de esa columna dice sobre cuántas conversaciones se calculó tu promedio: uno sobre 2
    conversaciones no dice gran cosa. La barra azul de Acciones es proporcional a quien más acciones
    tuvo en el rango; sirve para comparar de un vistazo, no es un porcentaje de meta.
  </p>

  <h3>Disposiciones y volumen diario</h3>
  <p>
    <strong>Distribución de disposiciones</strong>: con qué disposición se cerraron las conversaciones
    del rango. <strong>Volumen diario recibido</strong>: cuántas llegaron cada día, con los días sin
    correo dibujados en cero para que un fin de semana tranquilo se vea como lo que es.
  </p>

  <h3>Exportar CSV</h3>
  <p>
    Baja el detalle conversación por conversación del rango: asunto, solicitante, estado, agente,
    disposición, folio de GLPI, las cuatro fechas clave y el número de mensajes. Si un número
    agregado no te cuadra, este archivo es la forma de revisarlo renglón por renglón.
  </p>
</section>

<section id="faq">
  <h2>Preguntas frecuentes</h2>

  <h3>Respondí desde Outlook. ¿Cuenta?</h3>
  <p>
    Cuenta a medias. La sincronización detecta el correo saliente y sí actualiza la conversación:
    registra la primera respuesta, la pasa a Respondida y actualiza la última actividad. Tu tiempo de
    primera respuesta <strong>sí mejora</strong> y el hilo <strong>sí</strong> deja de aparecer como
    sin responder.
  </p>
  <p>
    Lo que no pasa es que quede con tu nombre: el correo no le dice al módulo quién de la cuenta
    compartida lo escribió, así que la acción se guarda como del sistema. Por eso no suma en
    Respuestas ni en Acciones, y no reinicia tu contador de Sin actividad. Si quieres que tu trabajo
    se refleje, responde desde Nexus.
  </p>

  <h3>Mi promedio de primera respuesta salió alto y yo contesto rápido</h3>
  <p>Revisa tres cosas, en este orden:</p>
  <ol class="help-steps">
    <li><strong>Sobre cuántas conversaciones se calculó</strong>
      <p>Está en el tooltip de la columna. Con pocas conversaciones, una sola muy lenta arrastra el promedio entero.</p></li>
    <li><strong>Si te reasignaron conversaciones viejas</strong>
      <p>El promedio se agrupa por dueño actual, así que heredas el tiempo de respuesta de lo que te pasaron.</p></li>
    <li><strong>Si el horario de servicio está apagado</strong>
      <p>Sin él, las noches y los fines de semana sí cuentan como tiempo de espera.</p></li>
  </ol>

  <h3>Contesté un correo atrasado, ¿por qué sigue en rojo "fuera de SLA"?</h3>
  <p>
    No debería. Fuera de SLA solo cuenta conversaciones <strong>sin primera respuesta</strong>. En
    cuanto respondes, sale de ese conteo. Recuerda que la pantalla Equipo se refresca cada 30
    segundos.
  </p>

  <h3>¿Por qué tengo menos Respuestas que Cerradas?</h3>
  <p>
    Es normal y no significa nada malo. Hay conversaciones que se cierran sin responder:
    informativas, duplicadas, spam, o casos que se resolvieron por teléfono. También pasa si
    respondiste desde Outlook.
  </p>

  <h3>Estuve trabajando toda la mañana y aparezco "Sin actividad"</h3>
  <p>
    Sin actividad mide <strong>acciones registradas</strong>, no presencia. Leer conversaciones,
    investigar o hablar por teléfono no dejan rastro en el módulo. Tomar, responder, notar, cambiar
    estado o cerrar sí. Si estuviste atendiendo un caso complicado sin registrar nada, una nota
    interna deja constancia de en qué ibas y además actualiza tu contador.
  </p>

  <h3>Cerré una conversación de un compañero. ¿A quién le suma?</h3>
  <p>
    La conversación cerrada le suma <strong>a su dueño</strong> en la columna Cerradas. A ti te suma
    una acción. Es a propósito: Cerradas mide casos resueltos del agente que los llevó, no clics.
  </p>

  <h3>Reabrieron una conversación que yo había cerrado. ¿Pierdo el cierre?</h3>
  <p>
    Sí. Al reabrirse se borra su fecha de cierre, así que sale del conteo de Cerradas del rango.
    Cuando se vuelva a cerrar, contará con la nueva fecha. Su tiempo de primera respuesta original no
    se pierde ni se recalcula.
  </p>

  <h3>Cambié el rango de fechas y "Abiertas ahora" no se movió</h3>
  <p>Correcto. Esa columna es una foto del momento actual. Todas las demás sí responden al rango.</p>

  <h3>¿Los correos autoarchivados o autogenerados me afectan?</h3>
  <p>
    No cuentan como carga tuya en ninguna pantalla. Si tú verificas un autoarchivo o un ticket
    autogenerado, esa verificación sí te suma una acción, porque es trabajo que hiciste.
  </p>

  <h3>¿Por qué en Equipo salen números distintos a los de Métricas?</h3>
  <p>
    Porque responden preguntas distintas. Equipo es <strong>ahora</strong>: qué está abierto en este
    instante. Métricas es <strong>un periodo</strong>: qué pasó entre dos fechas. El único número que
    debe coincidir es "en curso" de Equipo con "Abiertas ahora" de Métricas.
  </p>
</section>

<section id="limites">
  <h2>Qué no se mide aquí</h2>
  <p>Vale la pena decirlo con claridad, para que nadie lea de más en los números:</p>
  <ul>
    <li><strong>No miden calidad.</strong> Ninguna pantalla sabe si una respuesta resolvió el problema
      o si el solicitante quedó conforme.</li>
    <li><strong>No miden dificultad.</strong> Diez casos triviales generan más acciones que dos casos
      complejos que sí requirieron trabajo real.</li>
    <li><strong>No miden presencia ni horario.</strong> No hay registro de entrada, de sesión ni de
      tiempo frente a la pantalla.</li>
    <li><strong>No miden trabajo fuera del módulo.</strong> Llamadas, visitas, asesorías de pasillo y
      todo lo que se resuelve sin tocar Nexus es invisible aquí.</li>
  </ul>
  <div class="help-callout help-callout-info">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <p>
      <strong>Acciones</strong> es una medida de movimiento. Se lee junto a las cerradas y al tiempo
      de respuesta, nunca sola y nunca como si fuera una calificación.
    </p>
  </div>
</section>

<section id="revisar">
  <h2>Si un número no cuadra</h2>
  <ol class="help-steps">
    <li><strong>Confirma el rango y el filtro</strong>
      <p>Las fechas y el agente seleccionado cambian casi todos los números de la pantalla.</p></li>
    <li><strong>Revisa si el horario de servicio está activo</strong>
      <p>Lo dice el encabezado de Equipo. Cambia por completo cómo se cuentan los tiempos.</p></li>
    <li><strong>Exporta el CSV del rango</strong>
      <p>Busca la conversación específica. Las cuatro fechas por renglón casi siempre explican la diferencia.</p></li>
    <li><strong>Abre la conversación y lee su bitácora</strong>
      <p>Ahí está, en orden, todo lo que le pasó y quién lo hizo.</p></li>
    <li><strong>Repórtalo con datos</strong>
      <p>El ID de la conversación, el rango que usaste y qué esperabas ver. Con eso se puede rastrear.</p></li>
  </ol>
</section>
