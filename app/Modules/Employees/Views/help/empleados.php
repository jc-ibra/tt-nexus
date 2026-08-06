<?php
/**
 * Help Center content: Empleados module.
 *
 * Rendered inside App\Modules\Core\Views\help\show. Each <section id="..."> must
 * match an entry in HelpCenter::topics()['empleados']['sections'] so the table
 * of contents and scrollspy line up. Uses the shared .help-* content classes
 * defined by the guide layout, so this file only supplies copy and structure.
 */
?>

<section id="intro">
  <h2>Qué es el directorio</h2>
  <p>
    El módulo de <strong>Empleados</strong> es el directorio central de colaboradores de la organización.
    Desde aquí das de alta a cada persona, mantienes sus datos al día y consultas su información
    laboral. Es la fuente de verdad: cuando se aprovisionan sus accesos a sistemas (correo, GLPI,
    Intranet), estos parten de la ficha que capturas aquí.
  </p>
  <p>La tabla principal muestra, de un vistazo, lo esencial de cada colaborador:</p>
  <ul>
    <li><strong>Nombre y número</strong> de empleado, con su foto o iniciales.</li>
    <li><strong>Puesto, departamento y área</strong> a los que pertenece.</li>
    <li><strong>Correo</strong> institucional y si ya tiene buzón.</li>
    <li><strong>Estado</strong>: activo o inactivo.</li>
  </ul>
  <div class="help-callout help-callout-info">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <p>
      Recursos Humanos gestiona los datos del empleado; Sistemas usa el mismo directorio en modo
      lectura para aprovisionar accesos. Por eso algunos botones aparecen o no según tu rol.
    </p>
  </div>
</section>

<section id="alta">
  <h2>Dar de alta un empleado</h2>
  <p>Para registrar a un colaborador nuevo:</p>
  <ol class="help-steps">
    <li>
      <strong>Abre el formulario</strong>
      <p>En la pantalla de Empleados, pulsa el botón <strong>Nuevo empleado</strong> (arriba a la derecha).</p>
    </li>
    <li>
      <strong>Captura los datos de identidad</strong>
      <p>Número de empleado, nombre y apellidos. El número es su identificador único dentro de la plataforma.</p>
    </li>
    <li>
      <strong>Asigna su información laboral</strong>
      <p>Elige puesto, departamento y área. Si alguno no existe todavía, créalo primero en <strong>Catálogos</strong>.</p>
    </li>
    <li>
      <strong>Agrega el origen y el jefe directo</strong>
      <p>Selecciona estado y ubicación de origen, y escribe el nombre del jefe directo: el buscador te lo sugiere conforme escribes.</p>
    </li>
    <li>
      <strong>Registra sus datos de contacto personales</strong>
      <p>Captura el correo electrónico personal (obligatorio) y el celular o teléfono de contacto personal del colaborador. Registra la fecha de ingreso y, si quieres, sube una foto.</p>
    </li>
    <li>
      <strong>Guarda</strong>
      <p>Al guardar, se abre la ficha del empleado, lista para consultarse o para aprovisionar sus accesos.</p>
    </li>
  </ol>
  <div class="help-callout help-callout-info">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <p>
      <strong>El correo que capturas aquí es el personal, no el institucional.</strong> Estos son los
      datos de contacto del colaborador, como parte de su expediente. El correo institucional y los
      demás accesos se crean después, desde Aprovisionamiento; no se registran en esta alta.
    </p>
  </div>
  <div class="help-callout help-callout-warning">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <p>
      El <strong>número de empleado</strong> no se puede cambiar una vez guardado. Verifícalo con
      calma antes de crear el registro.
    </p>
  </div>
</section>

<section id="buscar">
  <h2>Buscar y filtrar</h2>
  <p>
    Cuando el directorio crece, la barra superior te ayuda a encontrar a cualquiera en segundos.
    Puedes combinar la búsqueda de texto con los filtros:
  </p>
  <ul>
    <li><strong>Buscar</strong>: escribe parte del nombre, del correo o el número de empleado.</li>
    <li><strong>Área</strong> y <strong>Departamento</strong>: acota a un equipo concreto.</li>
    <li><strong>Estado</strong>: muestra solo activos, solo inactivos, o todos.</li>
  </ul>
  <p>
    Pulsa <strong>Filtrar</strong> para aplicar y <strong>Limpiar</strong> para volver a ver la lista
    completa. Los resultados se paginan de 20 en 20.
  </p>
</section>

<section id="editar">
  <h2>Editar datos y foto</h2>
  <p>
    Abre a cualquier colaborador desde la lista (botón <strong>Ver</strong>) y usa <strong>Editar</strong>
    para actualizar sus datos. Desde el formulario también puedes subir o reemplazar su foto.
  </p>
  <div class="help-callout help-callout-tip">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/></svg>
    <p>
      <strong>Regla de oro:</strong> los nombres oficiales solo se cambian desde Nexus. Si editas el
      nombre, los apellidos o la foto de alguien que ya tiene cuenta en GLPI, Mailcow o Intranet, el
      cambio se sincroniza automáticamente hacia esos sistemas. Así el nombre nunca queda distinto en
      cada lado.
    </p>
  </div>
  <p>
    El número de empleado se muestra como solo lectura en la edición: es intencional, para proteger su
    identidad dentro de la plataforma.
  </p>
</section>

<section id="estados">
  <h2>Activos, inactivos y bajas</h2>
  <p>
    Cada colaborador tiene un estado que verás como una etiqueta en la lista:
  </p>
  <ul>
    <li><span class="badge badge-success">Activo</span> colaborador vigente.</li>
    <li><span class="badge badge-warning">Inactivo</span> ya no forma parte de la operación.</li>
  </ul>
  <p>
    Para dar de baja a alguien, edita su ficha, cámbialo a inactivo y registra la fecha de baja. No se
    borra: conservas su historial y sus accesos pueden gestionarse por separado desde Aprovisionamiento.
  </p>
</section>

<section id="catalogos">
  <h2>Catálogos</h2>
  <p>
    Los catálogos son las listas que alimentan los campos del formulario y los filtros. Mantenerlos
    ordenados hace que dar de alta sea rápido y consistente. Encuentras todos en el botón
    <strong>Catálogos</strong> de la pantalla de Empleados:
  </p>
  <ul>
    <li><strong>Áreas</strong> y <strong>Departamentos</strong>: la estructura organizativa.</li>
    <li><strong>Puestos</strong>: los cargos disponibles.</li>
    <li><strong>Estados de origen</strong> y <strong>Ubicaciones de origen</strong>: la procedencia del colaborador.</li>
  </ul>
  <p>
    En cada catálogo puedes crear, editar y eliminar registros. Si tienes muchos por cargar, usa la
    opción de <strong>importación</strong> para darlos de alta en bloque desde un archivo.
  </p>
  <div class="help-callout help-callout-info">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <p>
      Prepara los catálogos <strong>antes</strong> de empezar a dar de alta personas: así al capturar
      cada empleado solo eliges de una lista, sin interrumpirte para crear un área o un puesto.
    </p>
  </div>
</section>

<section id="accesos">
  <h2>Correo y accesos a sistemas</h2>
  <p>
    Si tu perfil incluye Aprovisionamiento, verás en la lista una columna extra de <strong>Accesos</strong>
    con etiquetas por sistema. El color indica el estado de la cuenta en cada uno:
  </p>
  <ul>
    <li><span class="badge badge-success">Mailcow</span> cuenta activa.</li>
    <li><span class="badge badge-warning">GLPI</span> alta en proceso.</li>
    <li><span class="badge badge-neutral">Intranet</span> cuenta deshabilitada.</li>
  </ul>
  <p>
    Cuando un colaborador todavía no tiene correo institucional, aparece la etiqueta
    <span class="badge badge-warning">Pendiente por provisionar</span>. Desde la ficha puedes vincular
    un buzón de Mailcow que ya exista, o generar sus accesos desde el panel de Aprovisionamiento.
  </p>
  <div class="help-callout help-callout-warning">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <p>
      Las cuentas de correo las administra el equipo de <strong>Sistemas</strong> desde
      Aprovisionamiento, no desde Recursos Humanos. Si eres de RRHH y ves esos datos en modo lectura,
      es normal.
    </p>
  </div>
</section>

<section id="faq">
  <h2>Preguntas frecuentes</h2>
  <div class="help-faq">
    <details>
      <summary>
        ¿Por qué no puedo cambiar el número de empleado?
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          El número es el identificador único del colaborador y otros procesos dependen de él. Por eso
          es de solo lectura una vez creado el registro. Si se capturó mal, contacta a Sistemas.
        </p>
      </div>
    </details>
    <details>
      <summary>
        No veo el botón "Nuevo empleado" ni "Editar", ¿qué pasa?
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          Tu perfil abre el directorio en modo lectura (por ejemplo, un rol de Aprovisionamiento). La
          gestión de datos corresponde al rol de Empleados / Recursos Humanos. Si necesitas capturar o
          editar, pide que te asignen ese rol.
        </p>
      </div>
    </details>
    <details>
      <summary>
        Cambié el nombre de una persona, ¿se actualiza en su correo y en GLPI?
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          Sí. Si el colaborador ya tiene cuenta en esos sistemas, al guardar el cambio de nombre,
          apellidos o foto la plataforma lo propaga automáticamente a GLPI, Mailcow e Intranet.
        </p>
      </div>
    </details>
    <details>
      <summary>
        ¿Qué significa "Pendiente por provisionar"?
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          Que el colaborador todavía no tiene correo institucional ni accesos generados. Es la señal
          para que Sistemas le cree sus cuentas desde Aprovisionamiento, o para vincular un buzón
          existente desde su ficha.
        </p>
      </div>
    </details>
    <details>
      <summary>
        Tengo muchas áreas o puestos por cargar, ¿hay forma más rápida?
        <svg class="help-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
      </summary>
      <div class="help-faq-body">
        <p>
          Sí. Cada catálogo tiene una opción de importación para dar de alta muchos registros a la vez
          desde un archivo, en lugar de capturarlos uno por uno.
        </p>
      </div>
    </details>
  </div>
</section>
