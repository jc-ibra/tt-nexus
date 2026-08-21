<?php
/**
 * Help Center content: cómo se calcula la evaluación mensual del agente.
 *
 * Rendered inside App\Modules\Core\Views\help\show. Each <section id="..."> must
 * match an entry in HelpCenter::topics()['evaluacion-kpis']['sections'] so the
 * table of contents and the scrollspy line up.
 *
 * The prose mirrors docs/agentkpis-evaluacion-para-agentes.md, which is the
 * printable version handed out as PDF. Keep both in step when the rules change.
 *
 * Thresholds shown here are the ones the calculation service applies
 * (KpiCalculationService); the abandonment window is read live from the
 * supervisor settings because it is configurable.
 */
$abandonDays = service('helpdeskSupervisorSettings')->businessDaysAbandonment();
?>

<section id="origen">
  <h2>De dónde salen los números</h2>
  <p>
    Tu calificación tiene dos mitades y cada una nace en un lugar distinto.
  </p>

  <h3>El 80% cuantitativo sale de la auditoría de mesa</h3>
  <p>
    Una vez al mes el supervisor corre una auditoría que lee directamente de GLPI: tus tickets del
    período, sus campos, su bitácora de cambios y sus seguimientos. Sobre cada ticket se aplican
    reglas tomadas del Manual de Mesa de Ayuda, y cada regla que no se cumple deja registrada una
    <strong>observación</strong>. Los KPIs cuentan observaciones. No hay nadie calificando ticket por
    ticket a mano.
  </p>

  <h3>El 20% cualitativo lo captura tu supervisor</h3>
  <p>
    Son ocho competencias que se califican del 1 al 4. Ahí sí hay criterio humano, y es a propósito:
    hay cosas que ningún sistema puede medir leyendo la base de datos.
  </p>

  <div class="help-callout help-callout-info">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <p>
      <strong>La auditoría no inventa datos.</strong> Todo lo que reporta está en GLPI y puedes verlo
      tú mismo abriendo el ticket. Si una observación no corresponde, el ticket es la evidencia para
      discutirla.
    </p>
  </div>
</section>

<section id="alcance">
  <h2>Qué tickets entran</h2>
  <p>
    Este es el punto que más dudas genera, así que conviene tenerlo claro antes de mirar cualquier
    porcentaje. <strong>Entran los tickets que tú registraste, con fecha de apertura dentro del mes
    evaluado.</strong> El criterio es quién aparece como el usuario que dio de alta el ticket en
    GLPI, no quién lo tiene asignado hoy.
  </p>

  <p>De ahí salen dos denominadores distintos:</p>
  <table class="table" style="width:100%;">
    <thead><tr><th>Denominador</th><th>Qué incluye</th><th>Qué KPIs lo usan</th></tr></thead>
    <tbody>
      <tr><td><strong>Tickets del período</strong></td><td>Todos tus tickets abiertos dentro del mes, sin importar su estado actual</td><td>KPI 1, 2 y 3</td></tr>
      <tr><td><strong>Tickets abiertos</strong></td><td>De esos, los que siguen sin resolver al momento de correr la auditoría</td><td>KPI 4</td></tr>
    </tbody>
  </table>

  <p>Dos consecuencias importantes:</p>
  <ul>
    <li><strong>Si no registraste ningún ticket en el mes, no se te genera evaluación.</strong> No
      sales con cero: simplemente no apareces en ese período.</li>
    <li><strong>La auditoría es una foto del día que se corrió.</strong> El KPI 4 mira tus tickets
      abiertos en ese momento. Si dos días después pusiste al día un ticket rezagado, el número del
      mes ya no cambia salvo que el supervisor vuelva a correr la auditoría y recalcule.</li>
  </ul>
  <p>
    La evaluación de un mes solo puede generarse si existe una auditoría completada para ese
    <strong>mes natural</strong>, del día 1 al último día. Una auditoría de una quincena o de un rango
    partido no alimenta la evaluación mensual.
  </p>
</section>

<section id="kpis">
  <h2>Los cinco KPIs</h2>
  <p>
    Cada KPI se califica en tres niveles: <span class="badge badge-success">Cumple</span>
    <span class="badge badge-warning">Parcial</span> <span class="badge badge-critical">No cumple</span>.
    Solo los que quedan en <em>Cumple</em> suman para el puntaje. Un <em>Parcial</em> está mejor que un
    <em>No cumple</em> para leer tu tendencia, pero para la suma valen igual.
  </p>

  <h3>KPI 1 · Seguimiento activo</h3>
  <p>
    De tus tickets que ya se resolvieron o cerraron, en cuántos dejaste al menos un seguimiento, tarea
    o solución tuya antes de resolverlos.
  </p>
  <table class="table" style="width:100%;">
    <thead><tr><th>Cumple</th><th>Parcial</th><th>No cumple</th></tr></thead>
    <tbody><tr><td>90% o más</td><td>de 75% a 89.99%</td><td>menos de 75%</td></tr></tbody>
  </table>
  <p>
    <strong>Qué lo baja:</strong> cerrar un ticket sin haber registrado nada en él. Resolverlo por
    teléfono y cerrarlo sin dejar constancia cuenta como falta de seguimiento, aunque el usuario haya
    quedado atendido. Manual, Parte 4.1 "Propiedad del ticket".
  </p>

  <h3>KPI 2 · Clasificación correcta</h3>
  <p>En cuántos de tus tickets la Categoría o el Tipo <strong>no</strong> tuvieron que cambiarse después de crearlos.</p>
  <table class="table" style="width:100%;">
    <thead><tr><th>Cumple</th><th>Parcial</th><th>No cumple</th></tr></thead>
    <tbody><tr><td>92% o más</td><td>de 80% a 91.99%</td><td>menos de 80%</td></tr></tbody>
  </table>
  <p>
    <strong>Qué lo baja:</strong> que alguien modifique la categoría o el tipo después de la creación.
    Se detecta en la bitácora de cambios de GLPI. Poner la categoría por primera vez al crear el
    ticket no cuenta: solo cuenta cuando había un valor previo y se sustituyó. Manual, Parte 3.3.
  </p>

  <h3>KPI 3 · Completitud de campos</h3>
  <p>
    En cuántos de tus tickets están llenos los campos obligatorios de la pestaña que corresponde a su
    categoría, más la pestaña IDS. Es el más exigente de los tres.
  </p>
  <table class="table" style="width:100%;">
    <thead><tr><th>Cumple</th><th>Parcial</th><th>No cumple</th></tr></thead>
    <tbody><tr><td>95% o más</td><td>de 85% a 94.99%</td><td>menos de 85%</td></tr></tbody>
  </table>
  <div class="help-callout help-callout-tip">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/></svg>
    <p>
      <strong>La convención <code>NO PROPORCIONADO</code> cuenta como campo lleno.</strong> Si el
      usuario no te dio el dato, escríbelo así en lugar de dejar el campo en blanco. El espacio vacío
      sí genera observación.
    </p>
  </div>
  <p>
    La pestaña IDS (Nombre y Número de empleado) se exige en todas las categorías excepto Control de
    Activos, y sus dos campos son obligatorios. Manual, Parte 3.7 y Parte 3.7.5.
  </p>

  <h3>KPI 4 · Tickets abandonados</h3>
  <p>
    Qué porcentaje de tus tickets <strong>abiertos</strong> lleva demasiado tiempo sin actividad tuya.
    Es el único donde menos es mejor. Si no traes ningún ticket abierto, queda en 0% y en Cumple.
  </p>
  <table class="table" style="width:100%;">
    <thead><tr><th>Cumple</th><th>Parcial</th><th>No cumple</th></tr></thead>
    <tbody><tr><td>5% o menos</td><td>de 5.01% a 10%</td><td>más de 10%</td></tr></tbody>
  </table>
  <p>
    <strong>Qué cuenta como abandono:</strong> que hayan pasado más de
    <strong><?= (int) $abandonDays ?> días hábiles</strong> desde tu última actividad en ese ticket,
    que es el umbral configurado hoy. Solo cuenta <strong>tu</strong> actividad: si un compañero lo
    movió, el reloj no se reinicia. Si nunca registraste nada, se cuenta desde la fecha de apertura.
    Sábados y domingos no suman. Manual, Parte 4.1.
  </p>

  <h3>KPI 5 · Escalaciones</h3>
  <p>
    Cuántas escalaciones válidas se te registraron en el mes. No sale de la auditoría automática: las
    captura el supervisor a mano, con ticket, fecha y motivo, y solo cuentan las que él marca como
    válidas.
  </p>
  <table class="table" style="width:100%;">
    <thead><tr><th>Cumple</th><th>Parcial</th><th>No cumple</th></tr></thead>
    <tbody><tr><td>0 escalaciones</td><td>1 o 2</td><td>3 o más</td></tr></tbody>
  </table>
  <p>Este KPI tiene una consecuencia especial, explicada en "Puntaje final y bloqueo".</p>
</section>

<section id="nivel">
  <h2>De los KPIs al 80%</h2>
  <p>
    No se promedian los porcentajes. Se cuenta <strong>cuántos KPIs quedaron en Cumple</strong> y esa
    cuenta se traduce a un nivel:
  </p>
  <table class="table" style="width:100%;">
    <thead><tr><th>KPIs en Cumple</th><th>Nivel</th><th>Puntos cuantitativos (de 80)</th></tr></thead>
    <tbody>
      <tr><td>5 de 5</td><td>100</td><td>80.00</td></tr>
      <tr><td>4 de 5</td><td>75</td><td>60.00</td></tr>
      <tr><td>3 de 5</td><td>50</td><td>40.00</td></tr>
      <tr><td>2 o menos</td><td>0</td><td>0.00</td></tr>
    </tbody>
  </table>

  <div class="help-callout help-callout-warning">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <p>
      <strong>Es una escalera, no una rampa.</strong> La diferencia entre 89.9% y 90% en un solo KPI
      puede valer 20 puntos de tu calificación final, porque cambia la cuenta de KPIs cumplidos. Y al
      revés: subir un KPI que ya estaba en Cumple no agrega nada.
    </p>
  </div>
  <p>
    Si quieres mover tu calificación, el mejor uso de tu esfuerzo es el KPI que está más cerca de
    cruzar su umbral, no el que ya cumples con holgura.
  </p>
</section>

<section id="rubrica">
  <h2>La rúbrica cualitativa (20%)</h2>
  <p>Son ocho competencias, cada una calificada del 1 al 4 por tu supervisor:</p>
  <table class="table" style="width:100%;">
    <thead><tr><th>Competencia</th><th>Peso</th></tr></thead>
    <tbody>
      <?php foreach (\App\Modules\AgentKpis\Config\AgentKpis::COMPETENCIES as $c): ?>
        <tr><td><?= esc($c['name']) ?></td><td><?= number_format($c['weight'] * 100, 0) ?>%</td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <p>La escala es la misma para todas:</p>
  <table class="table" style="width:100%;">
    <thead><tr><th>Nivel</th><th>Significado</th></tr></thead>
    <tbody>
      <?php foreach (\App\Modules\AgentKpis\Config\AgentKpis::LEVELS as $n => $desc): ?>
        <tr><td><strong><?= (int) $n ?></strong></td><td><?= esc($desc) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h3>Cómo se convierte en puntos</h3>
  <p>
    Se multiplica cada calificación por su peso y se suman. Eso da un número entre 1.00 y 4.00, que se
    lleva a la escala de 20 puntos. Un 3 parejo en las ocho competencias da 15 de 20; un 4 parejo da
    los 20; un 2 parejo da 10.
  </p>
  <p>
    El punto de partida cuando el supervisor no marca nada en una competencia es <strong>3
    (Cumple)</strong>, no cero. La rúbrica se hace para dejar constancia de lo que se sale de lo
    esperado, hacia arriba o hacia abajo.
  </p>
  <p>
    En tu evaluación ves las ocho competencias con su peso, tu calificación, lo que aportó cada una y
    el comentario que tu supervisor haya escrito en cada punto. Si una competencia tiene un 1 o un 2
    sin comentario, eso es justo lo que conviene preguntar en tu sesión de retroalimentación.
  </p>
</section>

<section id="final">
  <h2>Puntaje final y bloqueo</h2>
  <p>
    <strong>Puntaje final = puntos cuantitativos + puntos cualitativos.</strong> Máximo 100. Mientras
    tu supervisor no capture la rúbrica, la evaluación no aparece en tu pantalla: solo ves los
    períodos ya cerrados, para que nadie lea una calificación a medio calcular.
  </p>
  <p>
    Un ejemplo de lectura: 4 KPIs cumplidos (60 puntos) más una rúbrica que promedió cerca de 2 (unos
    10 puntos) da alrededor de 70. En esa evaluación lo que más mueve la aguja es cruzar el quinto
    KPI, no discutir un punto de la rúbrica.
  </p>

  <div class="help-callout help-callout-warning">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <p>
      <strong>Bloqueo por KPI 5.</strong> Con 3 o más escalaciones válidas en el mes, la evaluación
      queda bloqueada: no se calcula puntaje final, aunque tus otros cuatro KPIs estén perfectos. Sí
      ves todo el detalle. Es una regla del sistema de evaluación, no un error de cálculo, y está
      pensada para que ese mes se converse en lugar de resumirse en un número.
    </p>
  </div>
</section>

<section id="replica">
  <h2>Tus comentarios</h2>
  <p>
    Al final de cada evaluación tienes un espacio propio para escribir. Es lo único que tú puedes
    modificar, y nadie más escribe ahí: tu supervisor lo ve en solo lectura desde su módulo.
  </p>
  <p>
    Sirve para dejar constancia de contexto que los números no traen: una incapacidad, un mes con
    soporte a un proyecto especial, un ticket que la auditoría marcó y que tú sabes que estuvo bien
    atendido. Puedes editarlo las veces que quieras.
  </p>
  <p>
    Un comentario útil es concreto: el número de ticket, qué pasó y qué esperabas que se contara. "No
    estoy de acuerdo" sin datos no se puede revisar.
  </p>
</section>

<section id="faq">
  <h2>Preguntas frecuentes</h2>

  <h3>Vi una observación en Mi desempeño que no aparece en mis KPIs, o al revés</h3>
  <p>Son dos vistas con propósitos distintos y sí pueden no coincidir:</p>
  <ul>
    <li><strong>Mi desempeño</strong> te muestra únicamente las observaciones que tu supervisor revisó
      y marcó como <strong>procedentes</strong>. Es la lista para conversar y corregir.</li>
    <li><strong>Los KPIs</strong> se calculan sobre <strong>todas</strong> las observaciones que
      detectó la auditoría de ese mes, estén confirmadas o no.</li>
  </ul>
  <p>
    Así está definido hoy el sistema de evaluación. Si detectas una observación que no procede y que
    sí está afectando tu KPI, ese es exactamente el caso que hay que plantearle a tu supervisor,
    porque implica recalcular el mes.
  </p>

  <h3>El ticket lo atendí yo, pero lo levantó otra persona</h3>
  <p>
    Entonces no está en tu denominador. La evaluación agrupa por quien registró el ticket en GLPI. Si
    en tu operación es común atender tickets levantados por otros, es un tema para plantear con tu
    coordinador, porque cambia lo que la evaluación alcanza a ver de tu trabajo.
  </p>

  <h3>Ya puse al día un ticket abandonado, ¿por qué sigue contando?</h3>
  <p>
    Porque el KPI 4 se congeló el día que se corrió la auditoría. Ponerlo al día evita que vuelva a
    contar el mes siguiente; para que se refleje en el mes ya corrido, el supervisor tendría que
    volver a correr la auditoría y recalcular.
  </p>

  <h3>Mi KPI 3 salió bajo y yo lleno todos los campos</h3>
  <ol class="help-steps">
    <li><strong>Revisa los campos que quedaron literalmente vacíos</strong>
      <p>Si el usuario no dio el dato, <code>NO PROPORCIONADO</code> cuenta como lleno; el espacio en blanco no.</p></li>
    <li><strong>Revisa la pestaña IDS</strong>
      <p>Es la causa más común. Se exige en todas las categorías excepto Control de Activos, y sus dos campos son obligatorios.</p></li>
    <li><strong>Revisa la categoría del ticket</strong>
      <p>Cada categoría exige la pestaña que le corresponde. Un ticket mal categorizado se audita contra los campos equivocados, y de paso te pega en el KPI 2.</p></li>
  </ol>

  <h3>Subí un KPI y mi calificación no se movió</h3>
  <p>
    Es normal si ese KPI ya estaba en Cumple: el puntaje solo cuenta cuántos KPIs cumples, no por
    cuánto. Mira cuál de los que están en Parcial es el más cercano a su umbral.
  </p>

  <h3>Mi evaluación no aparece este mes</h3>
  <p>
    Tres razones posibles: no registraste tickets en el período, no se ha corrido la auditoría de ese
    mes, o tu supervisor todavía no captura la rúbrica. En los tres casos aparece en cuanto se
    resuelva.
  </p>

  <h3>¿El supervisor puede cambiar mis KPIs a mano?</h3>
  <p>
    No. Los cinco KPIs se calculan de los datos de GLPI. Lo que el supervisor determina es la rúbrica
    cualitativa, el registro de escalaciones y qué observaciones marca como procedentes.
  </p>
</section>

<section id="limites">
  <h2>Qué no mide</h2>
  <p>Vale la pena decirlo con claridad, para que nadie lea de más en el número:</p>
  <ul>
    <li><strong>No mide la satisfacción del usuario.</strong> Ninguna regla sabe si la persona quedó
      contenta con la atención.</li>
    <li><strong>No mide dificultad ni volumen.</strong> Veinte tickets sencillos y veinte complejos se
      ven igual en el denominador.</li>
    <li><strong>No mide el trabajo fuera de GLPI.</strong> Llamadas, apoyos a compañeros, visitas y
      todo lo que no deja rastro en un ticket es invisible aquí.</li>
    <li><strong>No mide presencia ni horario.</strong> No hay registro de entrada ni de sesión.</li>
  </ul>
  <div class="help-callout help-callout-info">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <p>
      Los KPIs miden <strong>disciplina de registro</strong>: que el ticket refleje lo que pasó, con
      sus datos completos y su seguimiento. Ese es su alcance, y por eso pesan 80 y no 100. El resto
      lo aporta la rúbrica, que sí es criterio humano.
    </p>
  </div>
</section>

<section id="revisar">
  <h2>Si un número no cuadra</h2>
  <ol class="help-steps">
    <li><strong>Abre el detalle de tu evaluación</strong>
      <p>Cada KPI muestra su denominador y cuántos tickets cumplieron el criterio. Ahí suele verse de inmediato si el problema es un ticket suelto o algo generalizado.</p></li>
    <li><strong>Contrasta con Mi desempeño</strong>
      <p>Las observaciones procedentes te dicen qué tickets específicos se marcaron y por qué regla del Manual.</p></li>
    <li><strong>Abre el ticket en GLPI</strong>
      <p>La evidencia siempre está ahí: los campos, la bitácora de cambios y los seguimientos.</p></li>
    <li><strong>Deja tu réplica por escrito</strong>
      <p>Con el número de ticket y qué esperabas que se contara.</p></li>
    <li><strong>Plantéalo con tu supervisor</strong>
      <p>Si procede, tendrá que recalcular el mes, y para eso necesita el caso concreto.</p></li>
  </ol>
</section>
