# Brief para Claude Code: Modulo de Aprovisionamiento de Accesos (Nexus)

## 1. Objetivo

Nexus debe convertirse en la fuente unica de verdad y el orquestador del ciclo de vida de identidades de la empresa. A partir de una alta, baja o cambio capturado en Nexus, el sistema debe propagar los cambios automaticamente a tres sistemas externos: GLPI, Mailcow e Intranet. Microsoft 365 queda fuera del alcance de este modulo (se maneja manualmente y nunca se elimina, solo se bloquea, por temas de historial).

Este documento define un MODULO NUEVO. No es una modificacion de un modulo existente. Ver seccion 3 para las reglas de alcance.

## 2. Operaciones que debe cubrir el modulo

1. Alta de usuario: crear la identidad en Nexus y aprovisionar (crear cuenta) en GLPI, Mailcow e Intranet.
2. Baja de usuario: desactivar en Nexus y desaprovisionar (desactivar/revocar acceso, NO eliminar) en GLPI, Mailcow e Intranet. Se conserva el registro para auditoria e indicadores.
3. Cambio de contrasena centralizado: cambiar la contrasena en Nexus y propagarla a los tres sistemas via API.
4. (Opcional, dejar preparado) Sincronizacion de datos: si cambia area, departamento, puesto o jefe directo, propagar la actualizacion a los sistemas que lo soporten (principalmente GLPI).

## 3. Alcance: que SI y que NO

### MODULO NUEVO
- Crear un modulo independiente. Nombre sugerido: `Aprovisionamiento` (o `Orquestador`, a criterio de la convencion del proyecto). Debe quedar claramente separado de los modulos existentes.
- Aqui vive toda la logica de conectores, orquestacion, cola de reintentos y bitacora de aprovisionamiento.

### NO TOCAR
- El modulo de **Buzones** ya funciona y se conecta a Mailcow correctamente. NO modificar su codigo, sus rutas ni su comportamiento actual.
- Se permite REUTILIZAR la logica de conexion a Mailcow del modulo Buzones (cliente HTTP, autenticacion, endpoints), pero encapsulandola desde el modulo nuevo. Si hace falta extraer logica comun, hacerlo creando una clase/servicio compartido que el modulo Buzones pueda seguir usando sin cambios de comportamiento, o consumiendo el servicio existente sin alterarlo. Ante la duda, preferir envolver (wrap) y no editar.

### MEJORAR (sin romper)
- El modulo de **Empleados** ya existe pero hay que mejorarlo. Claude Code ya sabe como maneja los usuarios y la asignacion de roles, asi que respetar esa logica. Las mejoras requeridas estan en la seccion 7.

## 4. Arquitectura propuesta (capa de conectores)

Implementar un patron de conector con contrato comun, para que cada sistema sea intercambiable y la orquestacion no dependa de los detalles de cada API.

Definir una interfaz, por ejemplo `ConectorSistema`, con estos metodos:

- `crearUsuario(array $datosUsuario): ResultadoConector`
- `desactivarUsuario(string $identificador): ResultadoConector`
- `cambiarPassword(string $identificador, string $nuevoPassword): ResultadoConector`
- `actualizarUsuario(string $identificador, array $datosUsuario): ResultadoConector`  (opcional, dejar la firma aunque algun sistema no lo soporte)
- `verificarConexion(): ResultadoConector`  (health check)

Implementaciones concretas:

- `ConectorGLPI`
- `ConectorMailcow` (reutiliza la conexion del modulo Buzones, ver seccion 3)
- `ConectorIntranet`

Servicio orquestador, por ejemplo `OrquestadorAccesos`, que recibe la operacion (alta, baja, cambio de password) y la ejecuta sobre la lista de conectores activos, registrando el resultado por sistema.

`ResultadoConector` debe estandarizar la respuesta: `exito` (bool), `mensaje`, `codigo`, `id_externo` (el id que el sistema externo asigno, por ejemplo el ID del usuario en GLPI), y `payload` crudo para depuracion.

## 5. Transaccionalidad y manejo de errores

No existe transaccion distribuida real entre 4 sistemas, asi que se usa un enfoque de pasos con estado y reintentos:

- Cada operacion sobre cada sistema se registra en una bitacora con su estado: `pendiente`, `exito`, `error`.
- Si un sistema falla (por ejemplo Mailcow no responde), los demas NO se revierten. Se marca ese paso como `error` y queda disponible para reintento manual o automatico.
- Implementar una cola de reintentos simple (tabla + comando programable) para los pasos en `error`.
- La UI debe mostrar el estado de aprovisionamiento por empleado y por sistema, para que el agente de sistemas vea de un vistazo si algo quedo a medias.
- Nunca dejar al usuario en un estado donde Nexus dice "alta completa" pero un sistema quedo sin crear sin que eso sea visible.

## 6. Modelo de datos nuevo (tablas del modulo)

Crear las tablas dentro del modulo nuevo. Nombres sugeridos, ajustar a la convencion del proyecto:

- `aprov_sistemas`: catalogo de sistemas destino (GLPI, Mailcow, Intranet). Campos: id, nombre, activo, base_url, tipo_auth, notas.
- `aprov_credenciales_sistema`: credenciales/tokens de Nexus hacia cada sistema (las llaves de API para llamar a GLPI, Mailcow, Intranet). Cifradas en reposo. Campos: id, sistema_id, nombre_credencial, valor_cifrado, creado_en, rotado_en.
- `aprov_cuentas_externas`: relacion empleado a sistema. Campos: id, empleado_id, sistema_id, id_externo (id del usuario en ese sistema), estado (activa, desactivada, error), creado_en, actualizado_en.
- `aprov_bitacora`: bitacora inmutable de cada accion. Campos: id, empleado_id, sistema_id, operacion (alta, baja, password, update), estado (pendiente, exito, error), mensaje, payload_resumen, usuario_ejecutor, ip_origen, creado_en.
- `aprov_cola_reintentos`: pasos fallidos pendientes de reintento. Campos: id, bitacora_id, intentos, proximo_intento, estado.

Nota sobre contrasenas: Nexus NO debe persistir la contrasena del empleado en texto plano. Para su propio login, guarda solo el hash (bcrypt/Argon2). En el momento del cambio de contrasena centralizado, Nexus toma el texto plano una sola vez en memoria, lo propaga a cada sistema via API y lo descarta. Lo que si se guarda cifrado (reversible, con clave maestra fuera del repo) son las credenciales de Nexus hacia los sistemas, no las de los empleados.

## 7. Mejoras al modulo Empleados existente

- Asegurar que el registro de empleado contenga todos los campos que necesitan los sistemas destino: No. Empleado, nombre, apellidos, correo (personal y de trabajo), area, departamento, puesto, jefe directo (No. Empleado), fecha de ingreso, fecha de baja, estado (activo/baja).
- Estos campos son los que alimentan la creacion de usuario en GLPI con datos completos (es justo el problema que tenemos hoy: GLPI por LDAP no trae nombre/apellido, por eso creamos via API con datos completos).
- Agregar, en la ficha del empleado, una vista del estado de aprovisionamiento por sistema (consumiendo `aprov_cuentas_externas` y `aprov_bitacora`), y botones para disparar las operaciones del orquestador (alta, baja, cambio de password, reintento).
- No alterar la logica existente de roles y usuarios que Claude Code ya maneja; solo extenderla.

## 8. Especificacion por conector

### 8.1 GLPI

- Usar la API REST nativa de GLPI.
- Flujo de autenticacion: `initSession` con App-Token y user-token (o user/pass), obtener `session_token`, usarlo en las llamadas, cerrar con `killSession`.
- Crear usuario via API con datos COMPLETOS (nombre, apellido, correo, y los campos de perfil que apliquen), no solo el login. Este es el motivo de no usar LDAP/SAML aqui: necesitamos los datos poblados para poder asignar tickets e generar indicadores de carga por tecnico sin esperar a que el usuario inicie sesion.
- Baja: desactivar el usuario (campo `is_active` a 0) e invalidar sesiones. NO eliminar, para conservar el historico de tickets.
- Cambio de password: actualizar via API el campo correspondiente.
- Guardar el ID que GLPI asigna en `aprov_cuentas_externas.id_externo`.

### 8.2 Mailcow

- Reutilizar la conexion existente del modulo Buzones (ver seccion 3, sin modificar Buzones).
- Alta: crear mailbox con la cuenta de correo de trabajo definida.
- Baja: desactivar el mailbox (no eliminar), para conservar el historial de correo.
- Cambio de password: actualizar el password del mailbox via API.
- Si el modulo Buzones ya expone metodos para crear/editar mailbox, el `ConectorMailcow` del modulo nuevo debe llamarlos, no reimplementarlos.

### 8.3 Intranet (contrato que Nexus espera que la Intranet exponga)

La Intranet es propia, asi que Nexus define el contrato y la Intranet debe implementar estos endpoints. Documentar en el codigo y en el README del modulo. Autenticacion: todas las llamadas llevan un header `Authorization: Bearer <API_KEY>` (la API key vive cifrada en `aprov_credenciales_sistema`). Todo viaja por HTTPS. Formato JSON.

**Crear usuario**

`POST /api/v1/usuarios`

Request (lo que Nexus envia):
```
{
  "no_empleado": "1024",
  "nombre": "Ana",
  "apellidos": "Lopez Garcia",
  "correo": "ana.lopez@trantortechnologies.mx",
  "area": "Ops",
  "departamento": "Soporte en Campo",
  "puesto": "Tecnico de Campo",
  "jefe_directo": "1003",
  "password": "<texto_plano_temporal>",
  "estado": "activo"
}
```

Response esperada (lo que la Intranet debe devolver):
```
{
  "exito": true,
  "id_usuario": "INT-5567",
  "mensaje": "Usuario creado"
}
```

**Desactivar usuario (baja)**

`POST /api/v1/usuarios/{no_empleado}/desactivar`

Response esperada:
```
{
  "exito": true,
  "mensaje": "Usuario desactivado"
}
```

**Cambiar contrasena**

`POST /api/v1/usuarios/{no_empleado}/password`

Request:
```
{
  "password": "<texto_plano_nuevo>"
}
```

Response esperada:
```
{
  "exito": true,
  "mensaje": "Contrasena actualizada"
}
```

**Actualizar datos (opcional)**

`PUT /api/v1/usuarios/{no_empleado}`

Request: mismos campos que en creacion, los que cambien.

Response: igual formato que creacion.

**Reglas de respuesta para todos los endpoints de la Intranet:**

- Codigo HTTP 200 para exito, 4xx para errores de validacion/autenticacion, 5xx para errores internos.
- Siempre devolver `exito` (bool) y `mensaje` (string) en el cuerpo, aunque haya error.
- En error, agregar `error_codigo` para que Nexus lo registre en bitacora. Ejemplo:
```
{
  "exito": false,
  "error_codigo": "USUARIO_DUPLICADO",
  "mensaje": "Ya existe un usuario con ese no_empleado"
}
```
- La baja NO debe borrar el registro en la Intranet, solo marcarlo inactivo.

## 9. Seguridad (requisitos minimos del modulo)

- Todas las llamadas salientes a GLPI, Mailcow e Intranet por HTTPS.
- Credenciales y tokens hacia los sistemas: cifrados en reposo. Clave maestra en variable de entorno del servidor, fuera del repositorio.
- Contrasena de empleado en texto plano: solo en memoria durante la propagacion, nunca persistida. En Nexus, solo hash para el login propio.
- CSRF activo en todos los formularios del modulo.
- Validacion y sanitizacion de toda entrada. Usar el query builder, nunca SQL crudo concatenado.
- Cada operacion del orquestador queda en `aprov_bitacora` con usuario ejecutor, IP y timestamp.
- Considerar rate limiting en las acciones masivas (no permitir disparar cientos de operaciones sin control).

## 10. Entregables esperados de Claude Code

1. El modulo nuevo `Aprovisionamiento` con su estructura (controladores, modelos, servicios, vistas, migraciones de las tablas de la seccion 6).
2. La interfaz `ConectorSistema` y las tres implementaciones (`ConectorGLPI`, `ConectorMailcow`, `ConectorIntranet`).
3. El servicio `OrquestadorAccesos` con las operaciones alta, baja y cambio de password.
4. La cola de reintentos y un comando ejecutable (cron) para procesar pendientes.
5. Las mejoras al modulo Empleados de la seccion 7, sin romper la logica de roles existente.
6. Un README del modulo que documente: como configurar las credenciales de cada sistema, el contrato de la API de la Intranet (seccion 8.3) listo para entregar a quien desarrolle esos endpoints, y como disparar/monitorear las operaciones.
7. Confirmacion explicita de que el modulo Buzones no fue modificado.

## 11. Aclaracion critica: los empleados NO son usuarios de Nexus

Esto es una regla dura, no debe malinterpretarse:

- Los unicos que inician sesion en Nexus son los usuarios internos del equipo de sistemas (administradores), gestionados por el modulo de usuarios y roles que Nexus YA tiene implementado. Esa logica no se toca ni se extiende para empleados.
- Los empleados son registros de datos dentro del modulo de Empleados. Son el objeto que Nexus administra y propaga. NO se les crea cuenta de acceso a Nexus, NO inician sesion en Nexus, NO tienen rol en Nexus.
- Nexus crea/administra las cuentas de los empleados en GLPI, Mailcow e Intranet, pero nunca en si mismo.
- No implementar ningun flujo de login, recuperacion de contrasena, ni autenticacion para empleados dentro de Nexus. El "cambio de contrasena centralizado" de este modulo cambia la contrasena del empleado en los sistemas EXTERNOS (GLPI, Mailcow, Intranet), no en Nexus, porque el empleado no tiene cuenta en Nexus.

## 12. Supuestos y entorno

- Nexus corre sobre CodeIgniter 4. Usar la estructura de modulos/namespaces de CI4 (por ejemplo `App\Modules\Aprovisionamiento` o la convencion ya usada en el proyecto), migraciones de CI4, y el cliente HTTP de CI4 (CodeIgniter\HTTP\CURLRequest) o Guzzle si ya se usa en el proyecto.
- Se asume que los endpoints de la Intranet aun no existen y se construiran segun el contrato de la seccion 8.3.
- El nombre del modulo (`Aprovisionamiento`) es sugerido; usar la convencion de nombres del proyecto.
- Microsoft 365 esta explicitamente fuera de alcance de este modulo.
