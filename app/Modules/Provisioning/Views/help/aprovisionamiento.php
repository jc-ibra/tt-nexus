<?php
/**
 * Help Center content: Provisioning (altas, bajas y contraseñas).
 *
 * Rendered inside App\Modules\Core\Views\help\show. Each <section id="..."> must
 * match an entry in HelpCenter::topics()['aprovisionamiento']['sections'] so the
 * table of contents and scrollspy line up. Uses the shared .help-* content
 * classes defined by the guide layout, so this file only supplies copy,
 * structure and screenshots.
 *
 * The guide is shared by two audiences that work the same flow from opposite
 * ends: RRHH (module `employees`) pide, Sistemas (module `provisioning`)
 * ejecuta. Cada sección dice de quién es la acción para que ninguno de los dos
 * tenga que leer la mitad que no le toca.
 *
 * Screenshots live in public/img/help/aprovisionamiento/ and are lazy-loaded.
 */

$img = static fn (string $file): string => base_url('img/help/aprovisionamiento/' . $file);
?>

<section id="intro">
  <h2>Qué es el aprovisionamiento</h2>
  <p>
    <strong>Aprovisionar</strong> es darle a un colaborador sus accesos a los sistemas internos:
    su correo institucional, GLPI e Intranet. <strong>Desaprovisionar</strong> (dar de baja) es
    quitárselos cuando deja la organización.
  </p>
  <p>
    Todo esto se hace desde un solo lugar: la tarjeta <strong>Aprovisionamiento</strong> dentro de la
    ficha del empleado. Nexus crea las cuentas en cada sistema, asigna la misma contraseña en todos y
    le manda al colaborador un correo de bienvenida. No tienes que entrar sistema por sistema.
  </p>
  <p>La diferencia con el módulo de Empleados es simple:</p>
  <ul>
    <li><strong>Empleados</strong> guarda quién es la persona: su expediente, sus datos, su foto.</li>
    <li><strong>Aprovisionamiento</strong> guarda qué puede abrir esa persona: sus cuentas y accesos.</li>
  </ul>
  <div class="help-callout help-callout-info">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <p>
      Primero existe el expediente, después existen los accesos. Un colaborador que todavía no está
      dado de alta en Empleados no se puede aprovisionar: no hay a quién ligarle las cuentas.
    </p>
  </div>
</section>

<section id="quien">
  <h2>Quién hace qué</h2>
  <p>
    El proceso necesita dos manos y ninguna puede terminarlo sola. Esta guía sirve a las dos: cada
    sección indica de quién es la acción.
  </p>
  <ul>
    <li>
      <strong>Recursos Humanos</strong> registra al colaborador en Empleados, decide qué accesos
      necesita según su puesto y lo pide por ticket. Al final recibe las credenciales y se las
      entrega a la persona.
    </li>
    <li>
      <strong>Sistemas</strong> ejecuta el aprovisionamiento en Nexus, resguarda la contraseña y
      documenta el cierre en el ticket.
    </li>
  </ul>
  <p>Visto de corrido, un alta pasa por estas etapas:</p>
  <ol class="help-steps">
    <li>
      <strong>RRHH registra al colaborador</strong>
      <p>En Empleados, con su número de empleado. Todavía sin correo ni accesos.</p>
    </li>
    <li>
      <strong>RRHH levanta el ticket en GLPI</strong>
      <p>Indica el tipo de movimiento y qué sistemas necesita el puesto. El ticket se asigna solo a Sistemas.</p>
    </li>
    <li>
      <strong>Sistemas aprovisiona en Nexus</strong>
      <p>Verifica el número de empleado contra el ticket, genera la contraseña y da de alta en los sistemas pedidos.</p>
    </li>
    <li>
      <strong>Sistemas documenta la solución</strong>
      <p>Registra en el ticket el correo creado y la contraseña inicial, y lo resuelve.</p>
    </li>
    <li>
      <strong>RRHH valida y entrega</strong>
      <p>Toma las credenciales del ticket, confirma en Nexus que los accesos quedaron y se las da al colaborador.</p>
    </li>
  </ol>
  <div class="help-callout help-callout-warning">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <p>
      <strong>Ningún movimiento se ejecuta sin ticket.</strong> El ticket es la autorización y la
      evidencia de lo que se hizo, para quién y cuándo. Aplica igual para altas, bajas y cambios de
      contraseña.
    </p>
  </div>
</section>

<section id="solicitud">
  <h2>Pedir el alta o la baja (RRHH)</h2>
  <p>
    Antes de pedir nada, abre la ficha del colaborador en Empleados y revisa la tarjeta
    <strong>Aprovisionamiento</strong>. Si ves que no tiene cuenta de correo ligada, es justo el
    momento de levantar la solicitud.
  </p>
  <figure class="help-figure">
    <img src="<?= $img('nexus-sin-accesos.png') ?>" alt="Tarjeta Aprovisionamiento de un colaborador recién creado, sin cuentas de correo ligadas" loading="lazy" decoding="async">
    <figcaption>Un colaborador recién registrado: existe su expediente, pero todavía no tiene accesos.</figcaption>
  </figure>
  <p>
    La solicitud se levanta en GLPI, en <strong>Soporte &rsaquo; Catálogo de servicios &rsaquo;
    Sistemas Internos</strong>, con el formulario <strong>Aprovisionamiento de empleados (Nexus)</strong>.
    Es el mismo formulario para alta y para baja: lo que cambia es el campo <strong>Tipo de
    movimiento</strong>.
  </p>
  <figure class="help-figure">
    <img src="<?= $img('glpi-catalogo.png') ?>" alt="Catálogo de servicios de GLPI con el formulario Aprovisionamiento de empleados (Nexus)" loading="lazy" decoding="async">
    <figcaption>El formulario vive en Sistemas Internos y solo es visible para el grupo de RRHH.</figcaption>
  </figure>
  <p>Dos campos hacen todo el trabajo:</p>
  <ul>
    <li><strong>Tipo de movimiento</strong>: alta o baja.</li>
    <li>
      <strong>Detalle de la solicitud</strong>: viene prellenado con los accesos por defecto.
      Ajústalo a lo que de verdad necesita el puesto, porque Sistemas va a aprovisionar exactamente
      lo que digas aquí.
    </li>
  </ul>
  <p>
    Incluye siempre el <strong>número de empleado</strong>. Es el dato con el que Sistemas confirma
    que va a mover a la persona correcta.
  </p>
  <figure class="help-figure">
    <img src="<?= $img('glpi-solicitud-alta.png') ?>" alt="Formulario de solicitud con el tipo de movimiento Alta seleccionado" loading="lazy" decoding="async">
    <figcaption>Al enviarlo, GLPI te devuelve el número de ticket y lo asigna solo al responsable de Sistemas.</figcaption>
  </figure>
  <p>
    A partir de ahí no tienes que hacer nada más: el ticket queda asignado y puedes seguirlo desde
    <strong>Soporte &rsaquo; Tickets</strong>.
  </p>
  <div class="help-callout help-callout-warning">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <p>
      <strong>Si una baja es urgente</strong>, además del ticket avísale a Sistemas por vía directa,
      por ejemplo su extensión. La llamada acelera la atención, pero no sustituye al ticket.
    </p>
  </div>
</section>

<section id="panel">
  <h2>La tarjeta de Aprovisionamiento (Sistemas)</h2>
  <p>
    Entra a <strong>Aprovisionamiento &rsaquo; Empleados</strong> y busca al colaborador por su
    <strong>número de empleado</strong>. Confirma que coincide con el del ticket antes de tocar
    nada.
  </p>
  <figure class="help-figure">
    <img src="<?= $img('glpi-detalle-solicitud.png') ?>" alt="Detalle del ticket en GLPI mostrando el número de empleado y el SLA" loading="lazy" decoding="async">
    <figcaption>En el ticket vienen el número de empleado, los accesos pedidos y la fecha de vencimiento del SLA.</figcaption>
  </figure>
  <figure class="help-figure">
    <img src="<?= $img('nexus-busqueda-empleado.png') ?>" alt="Búsqueda de un empleado por número dentro del módulo de Aprovisionamiento" loading="lazy" decoding="async">
    <figcaption>Buscar por número evita el error más caro de todo el proceso: mover a la persona equivocada.</figcaption>
  </figure>
  <p>Al abrir su ficha, la tarjeta <strong>Aprovisionamiento</strong> tiene tres partes:</p>
  <ul>
    <li>
      <strong>Cuentas de correo electrónico</strong>: los buzones ligados al colaborador. Uno de
      ellos es el <strong>principal</strong>, y ese es el que se usa como llave de acceso.
    </li>
    <li>
      <strong>Sistemas</strong>: una fila por sistema, con el estado de la cuenta en cada uno y las
      casillas para elegir sobre cuáles vas a actuar.
    </li>
    <li>
      <strong>La barra de acciones</strong>, abajo: <strong>Alta en sistemas</strong>,
      <strong>Reactivar</strong>, <strong>Cambiar contraseña</strong> y <strong>Dar de baja</strong>.
    </li>
  </ul>
  <figure class="help-figure">
    <img src="<?= $img('nexus-panel-aprovisionamiento.png') ?>" alt="Tarjeta de Aprovisionamiento con las secciones de cuentas de correo, sistemas y la barra de acciones" loading="lazy" decoding="async">
    <figcaption>Las pestañas de acción cambian según el estado del colaborador: no siempre están todas.</figcaption>
  </figure>
  <div class="help-callout help-callout-info">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <p>
      Si una pestaña no aparece, es porque no aplica. <strong>Alta en sistemas</strong> solo se
      muestra si queda algún sistema sin cuenta; <strong>Cambiar contraseña</strong> y
      <strong>Dar de baja</strong> solo si ya tiene al menos una cuenta; <strong>Reactivar</strong>
      solo si tiene cuentas desactivadas.
    </p>
  </div>
</section>

<section id="alta">
  <h2>Dar de alta en sistemas (Sistemas)</h2>
  <p>
    En la sección <strong>Sistemas</strong>, marca únicamente los que pide el ticket. Después abre la
    pestaña <strong>Alta en sistemas</strong>.
  </p>
  <ol class="help-steps">
    <li>
      <strong>Genera la contraseña inicial</strong>
      <p>Pulsa <strong>Generar y copiar contraseña</strong>: se crea una contraseña segura y queda en tu portapapeles. Pégala de una vez en un lugar seguro.</p>
    </li>
    <li>
      <strong>Revisa el correo del buzón</strong>
      <p>Si el alta incluye buzón Staff, aparece el campo <strong>Correo de buzón Staff</strong> ya propuesto por Nexus con el formato <code>nombre.apellido</code>. Puedes ajustarlo, y eliges el dominio de la lista.</p>
    </li>
    <li>
      <strong>Confirma</strong>
      <p>Pulsa <strong>Dar de alta en sistemas</strong> y acepta el aviso de confirmación.</p>
    </li>
  </ol>
  <figure class="help-figure">
    <img src="<?= $img('nexus-alta-contrasena.png') ?>" alt="Panel de alta en sistemas con la contraseña generada y el correo del buzón propuesto" loading="lazy" decoding="async">
    <figcaption>Nexus propone el usuario con el primer nombre y el primer apellido. Si ya existe, agrega un consecutivo: nombre.apellido1.</figcaption>
  </figure>
  <p>
    Al terminar, Nexus te muestra la cuenta creada y la tarjeta se actualiza con los accesos ya
    otorgados. Aprovecha para comparar contra el ticket: lo que quedó marcado debe ser exactamente lo
    que se pidió.
  </p>
  <figure class="help-figure">
    <img src="<?= $img('nexus-cuenta-creada.png') ?>" alt="Confirmación del aprovisionamiento con la cuenta institucional creada" loading="lazy" decoding="async">
    <figcaption>La cuenta creada queda registrada como correo principal del colaborador.</figcaption>
  </figure>
  <figure class="help-figure">
    <img src="<?= $img('nexus-accesos-otorgados.png') ?>" alt="Sección de sistemas mostrando los accesos ya otorgados al colaborador" loading="lazy" decoding="async">
    <figcaption>Cada fila muestra el estado real de la cuenta en ese sistema, no lo que se intentó.</figcaption>
  </figure>
  <div class="help-callout help-callout-info">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <p>
      GLPI e Intranet usan siempre el <strong>correo institucional principal</strong> como usuario,
      nunca el personal. Por eso, si vas a dar de alta en esos dos, el colaborador necesita ya un
      correo institucional: inclúyelo en la misma alta o regístraselo antes.
    </p>
  </div>
  <p>
    Terminado el alta, el colaborador recibe automáticamente en su nuevo buzón un
    <strong>correo de bienvenida</strong> con su contraseña temporal y la lista de sistemas a los que
    puede entrar.
  </p>
  <figure class="help-figure">
    <img src="<?= $img('correo-bienvenida.png') ?>" alt="Correo de bienvenida que recibe el colaborador con sus accesos" loading="lazy" decoding="async">
    <figcaption>El correo le pide expresamente cambiar la contraseña cuanto antes.</figcaption>
  </figure>
</section>

<section id="contrasena">
  <h2>La contraseña</h2>
  <p>
    Una sola contraseña sirve para todos los sistemas del colaborador. Eso simplifica la entrega, y
    también obliga a tratarla con cuidado.
  </p>
  <div class="help-callout help-callout-warning">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <p>
      <strong>La contraseña se ve una sola vez.</strong> Nexus no la guarda en claro y no hay forma
      de volver a consultarla. Si la pierdes antes de entregarla, no la busques: genera una nueva y
      propágala.
    </p>
  </div>
  <p>
    Para cambiarla después del alta, abre la pestaña <strong>Cambiar contraseña</strong>, genera la
    nueva y pulsa <strong>Propagar contraseña</strong>. Se aplica a los sistemas que tengas
    marcados.
  </p>
  <figure class="help-figure">
    <img src="<?= $img('nexus-cambio-contrasena.png') ?>" alt="Panel de cambio de contraseña con todos los sistemas seleccionados" loading="lazy" decoding="async">
    <figcaption>Con todos los sistemas marcados, la nueva contraseña queda igual en todos lados.</figcaption>
  </figure>
  <p>
    Si solo necesitas cambiarla en un sistema, por ejemplo porque el colaborador perdió el acceso a
    la Intranet nada más, deja marcado únicamente ese.
  </p>
  <figure class="help-figure">
    <img src="<?= $img('nexus-contrasena-un-sistema.png') ?>" alt="Cambio de contraseña aplicado solo al sistema seleccionado" loading="lazy" decoding="async">
    <figcaption>Ojo: al hacerlo así, ese sistema queda con una contraseña distinta a la de los demás.</figcaption>
  </figure>
  <p>
    Desde aquí nunca se crean cuentas. Un sistema marcado en el que el colaborador no tenga cuenta
    simplemente se omite.
  </p>
</section>

<section id="entrega">
  <h2>Cerrar el movimiento y entregar (ambos)</h2>
  <p>
    <strong>Sistemas</strong> cierra el ticket en GLPI con la solución documentada: dentro del
    ticket, en el botón <strong>Responder</strong>, elige <strong>Solución</strong>, clasifícala como
    <strong>Resolución - Remota</strong> y llena la plantilla que se carga sola, sustituyendo los
    valores entre corchetes por el correo y la contraseña reales. Con <strong>Agregar</strong> queda
    registrada.
  </p>
  <p>
    <strong>RRHH</strong> recoge de ahí las credenciales. Si la solicitud ya no aparece en el listado
    de tickets es porque fue resuelta: búscala en la tarjeta <strong>Tickets resueltos</strong> o
    directamente por su número.
  </p>
  <figure class="help-figure">
    <img src="<?= $img('glpi-ticket-resuelto.png') ?>" alt="Ticket resuelto en GLPI mostrando el correo y la contraseña de acceso" loading="lazy" decoding="async">
    <figcaption>El ticket resuelto es el punto de entrega de las credenciales, y la evidencia del movimiento.</figcaption>
  </figure>
  <p>
    Con los datos en la mano puedes <strong>aprobar</strong> la solución o <strong>rechazarla</strong>
    si algo no cuadra, y en ese caso Sistemas lo revisa otra vez.
  </p>
  <p>
    Antes de dar por cerrado el alta, confirma en Nexus que el colaborador ya tiene su cuenta
    institucional como <strong>correo principal</strong> y que la tarjeta de Aprovisionamiento
    muestra los accesos esperados.
  </p>
  <figure class="help-figure">
    <img src="<?= $img('nexus-correo-principal.png') ?>" alt="Ficha del colaborador en Nexus con el correo principal ya registrado" loading="lazy" decoding="async">
    <figcaption>Si el correo principal aparece en la ficha, el alta llegó completa hasta Nexus.</figcaption>
  </figure>
  <div class="help-callout help-callout-warning">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <p>
      Al entregar las credenciales, dile al colaborador que <strong>cambie la contraseña de
      inmediato</strong>. Es temporal, y quedó expuesta en el momento en que se la hiciste llegar.
    </p>
  </div>
</section>

<section id="baja">
  <h2>Baja y reactivación (Sistemas)</h2>
  <p>
    Abre la pestaña <strong>Dar de baja</strong>, revisa que estén marcados los sistemas donde el
    colaborador tiene cuenta y pulsa <strong>Confirmar baja en sistemas</strong>.
  </p>
  <figure class="help-figure">
    <img src="<?= $img('nexus-baja.png') ?>" alt="Panel de baja del colaborador con los sistemas seleccionados" loading="lazy" decoding="async">
    <figcaption>La baja desactiva las cuentas en los sistemas marcados y deja al colaborador como inactivo en Nexus.</figcaption>
  </figure>
  <p>
    Nexus pide confirmación antes de ejecutarla. Es a propósito: es la acción menos reversible de
    todo el módulo.
  </p>
  <figure class="help-figure">
    <img src="<?= $img('nexus-baja-confirmacion.png') ?>" alt="Aviso de confirmación antes de ejecutar la baja" loading="lazy" decoding="async">
    <figcaption>Después de confirmar, el colaborador pierde el acceso a las plataformas.</figcaption>
  </figure>
  <p>
    <strong>La baja no borra nada.</strong> El expediente, las cuentas y el historial se conservan;
    lo que se revoca es el acceso. Por eso una persona dada de baja se puede reactivar: la pestaña
    <strong>Reactivar</strong> vuelve a habilitar las cuentas que ya tenía, sin crear ninguna nueva,
    y por seguridad siempre le asigna una contraseña nueva.
  </p>
  <div class="help-callout help-callout-warning">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <p>
      <strong>Microsoft 365 no pasa por Nexus.</strong> Si el colaborador tiene cuenta de Microsoft,
      darla de baja aquí no la toca: hay que hacerlo a mano en la consola de administración de
      Microsoft. Lo mismo aplica al alta.
    </p>
  </div>
</section>

<section id="casos">
  <h2>Casos que no son el camino feliz</h2>

  <h3>El colaborador ya tiene cuenta en GLPI</h3>
  <p>
    No lo des de alta otra vez: crearías un usuario duplicado. En la fila de GLPI usa
    <strong>Vincular cuenta existente</strong>, busca la cuenta que ya tiene y lígala. Si te
    equivocaste de cuenta, <strong>Desvincular</strong> deshace la liga sin tocar el usuario en
    GLPI.
  </p>

  <h3>El colaborador ya tiene un correo institucional</h3>
  <p>
    Regístralo en <strong>Cuentas de correo electrónico</strong> y usa <strong>Validar buzón</strong>
    para confirmar que existe de verdad. Al darlo de alta, Nexus adopta ese buzón en lugar de crear
    uno nuevo.
  </p>

  <h3>Un sistema falló durante el alta</h3>
  <p>
    Los demás sí quedaron: el alta no es todo o nada. La fila del sistema que falló muestra
    <strong>Reintentar alta</strong>, y desde ahí vuelves a intentarlo sin repetir el resto.
  </p>

  <h3>Hay que quitar un solo acceso</h3>
  <p>
    Si el colaborador sigue activo pero ya no debe entrar a un sistema, usa
    <strong>Desactivar</strong> en la fila de ese sistema. No es lo mismo que darlo de baja: el resto
    de sus accesos no se toca.
  </p>

  <h3>El número de empleado quedó mal</h3>
  <p>
    No se puede editar después de guardar. Repórtalo al responsable de Sistemas antes de ejecutar
    cualquier movimiento sobre esa ficha.
  </p>
</section>

<section id="faq">
  <h2>Preguntas frecuentes</h2>
  <div class="help-faq">

    <details>
      <summary>
        Perdí la contraseña antes de entregarla. ¿Dónde la consulto?
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          En ningún lado: Nexus no la guarda en claro. Genera una nueva desde
          <strong>Cambiar contraseña</strong> y propágala a todos sus sistemas.
        </p>
      </div>
    </details>

    <details>
      <summary>
        ¿Puedo elegir el nombre de usuario del correo?
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          Nexus lo propone con el primer nombre y el primer apellido, y le agrega un consecutivo si
          ya existe. Puedes ajustarlo antes de confirmar el alta, pero conviene respetar el formato
          para que el directorio se mantenga parejo.
        </p>
      </div>
    </details>

    <details>
      <summary>
        Di de baja al colaborador y su cuenta de Microsoft sigue activa.
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          Es lo esperado. Nexus no gestiona Microsoft 365 todavía. Esa baja se hace a mano en la
          consola de administración de Microsoft.
        </p>
      </div>
    </details>

    <details>
      <summary>
        El colaborador no puede entrar a un sistema que aparece en su correo de bienvenida.
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          Revisa la tarjeta de Aprovisionamiento de su ficha: ahí ves el estado real de la cuenta en
          cada sistema. Si esa fila quedó sin cuenta, usa <strong>Reintentar alta</strong>.
        </p>
      </div>
    </details>

    <details>
      <summary>
        ¿La baja borra al colaborador del directorio?
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          No. Su expediente se queda y solo cambia a inactivo. Se conserva el historial completo del
          movimiento, y por eso mismo se le puede reactivar más adelante.
        </p>
      </div>
    </details>

    <details>
      <summary>
        RRHH no encuentra el ticket en su listado.
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          Casi siempre es porque ya fue resuelto y salió de la vista de pendientes. Ábrelo desde la
          tarjeta <strong>Tickets resueltos</strong> o búscalo por su número.
        </p>
      </div>
    </details>

    <details>
      <summary>
        Necesito agregarle un acceso a alguien que ya está aprovisionado.
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          Si al colaborador todavía le falta algún sistema, la pestaña <strong>Alta en sistemas</strong>
          sigue disponible y solo lista los que no tiene. Igual que cualquier otro movimiento,
          requiere su ticket.
        </p>
      </div>
    </details>

  </div>
</section>
