<?php

/**
 * reset-db.php - Equivalente web de reset-db.sh para hostings SIN acceso a terminal.
 *
 * Reinicia la base de datos de tt-nexus arrancando el framework CodeIgniter 4
 * (Boot::bootWorker) sin despachar una petición HTTP normal. Ofrece DOS modos:
 *
 *   mode=hard-reset  -> Igual que reset-db.sh: BORRA TODO. Hace DROP de todas las
 *                       tablas y vuelve a correr migraciones + seeders desde cero.
 *
 *   mode=reset       -> Reset "suave": vacía (TRUNCATE) sólo las tablas de datos
 *                       transaccionales/de prueba y CONSERVA:
 *                         - Todas las tablas de Core (core_*, ci_sessions, migrations)
 *                         - Las tablas de settings / credenciales:
 *                             provisioning_settings, provisioning_system_credentials,
 *                             provisioning_systems, provisioning_glpi_catalog_prefs,
 *                             mailboxes_settings, servicedesk_settings,
 *                             servicedesk_category_map, servicedesk_backlog_areas
 *                       Después re-ejecuta los seeders (idempotentes) para restaurar
 *                       catálogos por defecto (licencias MS, coordinadores GLPI) y el
 *                       registro de módulos/roles. NO toca el esquema (no migra).
 *
 * USO:
 *   1. Sube este archivo a la carpeta public/ del hosting (junto a index.php).
 *   2. Define SETUP_TOKEN en tu .env (o como variable de entorno del hosting):
 *        SETUP_TOKEN = un-valor-secreto-largo-y-aleatorio
 *   3. Visita:  https://TU-DOMINIO/reset-db.php?token=EL-TOKEN
 *        - Verás una pantalla con los dos modos y el nombre de la BD objetivo.
 *      Para EJECUTAR, agrega el modo y confirma escribiendo el nombre de la BD:
 *        ...?token=EL-TOKEN&mode=reset&confirm=NOMBRE_BD
 *        ...?token=EL-TOKEN&mode=hard-reset&confirm=NOMBRE_BD
 *   4. IMPORTANTE: BORRA este archivo del servidor cuando termines.
 *
 * SOLO PARA ENTORNOS DE TEST. Aborta si CI_ENVIRONMENT=production.
 * No contempla modo Docker (a diferencia de reset-db.sh).
 */

// ── Visibilidad de errores y sin límite de tiempo ───────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '1');
@set_time_limit(0);

// ── Check versión de PHP (igual que index.php) ──────────────────────────────────
$minPhpVersion = '8.2';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    exit(sprintf('Se requiere PHP %s o superior. Versión actual: %s', $minPhpVersion, PHP_VERSION));
}

// ── Arranque del framework CodeIgniter (sin routear) ────────────────────────────
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();

require $paths->systemDirectory . '/Boot.php';

// bootWorker() carga .env, constantes, autoloader, namespaces de modulos y
// servicios, y devuelve la app SIN ejecutar el kernel HTTP.
CodeIgniter\Boot::bootWorker($paths);

// ── Guard por token ─────────────────────────────────────────────────────────────
$expectedToken = (string) (env('SETUP_TOKEN') ?: '');
if ($expectedToken === '') {
    header('HTTP/1.1 500 Internal Server Error', true, 500);
    exit('500 - SETUP_TOKEN no está configurado. Define SETUP_TOKEN en tu .env antes de usar este script.');
}
$token = $_GET['token'] ?? '';
if (! is_string($token) || ! hash_equals($expectedToken, $token)) {
    header('HTTP/1.1 403 Forbidden', true, 403);
    exit('403 Forbidden - token invalido o ausente.');
}

// ── Seeders (clase => etiqueta), mismo orden que setup.sh / setup.php ────────────
$SEEDERS = [
    ['App\Database\Seeds\CoreSeeder', 'CoreSeeder'],
    ['App\Modules\Employees\Database\Seeders\EmployeesModuleSeeder', 'EmployeesModuleSeeder'],
    ['App\Modules\KPIsOperativos\Database\Seeders\KPIsOperativosModuleSeeder', 'KPIsOperativosModuleSeeder'],
    ['App\Modules\KPIsOperativos\Database\Seeders\GlpiCoordinatorsSeeder', 'GlpiCoordinatorsSeeder'],
    ['App\Modules\Mailboxes\Database\Seeders\MailboxesModuleSeeder', 'MailboxesModuleSeeder'],
    ['App\Modules\Provisioning\Database\Seeders\ProvisioningModuleSeeder', 'ProvisioningModuleSeeder'],
    ['App\Modules\Provisioning\Database\Seeders\MsLicensesSeeder', 'MsLicensesSeeder'],
    ['App\Modules\ServiceDesk\Database\Seeders\ServiceDeskModuleSeeder', 'ServiceDeskModuleSeeder'],
];

// ── Tablas de settings/credenciales que el reset SUAVE conserva ──────────────────
// (además de todas las tablas de Core: core_*, ci_sessions y migrations).
//
// Regla de oro: aquí van las tablas que guardan CONFIGURACIÓN hecha a mano por el
// admin (settings, credenciales, mapeos). NO van las de datos transaccionales,
// telemetría ni bitácoras de auditoría (esas se vacían y punto). Ver RESET-DB.md.
$PRESERVE_SETTINGS = [
    // Provisioning
    'provisioning_settings',
    'provisioning_system_credentials',
    'provisioning_systems',
    'provisioning_glpi_catalog_prefs',
    // Mailboxes
    'mailboxes_settings',
    // Service Desk — settings del importador + widget + creador IA + reporte de backlog
    'servicedesk_settings',
    // Service Desk — mapeo categoría GLPI → plantilla + regional/IDC/cliente del backlog
    'servicedesk_category_map',
    // Service Desk — mapeo categoría raíz GLPI → área de negocio del reporte de backlog
    // (asignado a mano por el SuperAdmin; NO se puede re-derivar con un seeder).
    'servicedesk_backlog_areas',
];

$mode    = (string) ($_GET['mode'] ?? '');
$confirm = (string) ($_GET['confirm'] ?? '');
$validModes = ['reset', 'hard-reset'];

// ── Conexión a BD (necesaria tanto para la pantalla como para ejecutar) ──────────
try {
    $db = \Config\Database::connect();
    $db->initialize();
    $dbName = (string) $db->getDatabase();
} catch (\Throwable $e) {
    header('HTTP/1.1 500 Internal Server Error', true, 500);
    exit('500 - No se pudo conectar a la base de datos: ' . htmlspecialchars($e->getMessage()));
}

$ciEnv = (string) (env('CI_ENVIRONMENT') ?: (defined('ENVIRONMENT') ? ENVIRONMENT : ''));

// ── SEGURO: nunca en producción ──────────────────────────────────────────────────
if ($ciEnv === 'production') {
    header('HTTP/1.1 403 Forbidden', true, 403);
    exit('403 - CI_ENVIRONMENT=production. ABORTADO: este script jamás debe correr en producción.');
}

// ── Salida HTML tipo consola ────────────────────────────────────────────────────
header('Content-Type: text/html; charset=UTF-8');

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$pageHead = static function (string $subtitle): void {
    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>tt-nexus · Reset de base de datos</title>
<style>
  :root { color-scheme: dark; }
  body { background:#0d1117; color:#c9d1d9; font:14px/1.55 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; margin:0; padding:24px; }
  .wrap { max-width:880px; margin:0 auto; }
  h1 { font-size:18px; color:#fff; margin:0 0 4px; }
  .sub { color:#8b949e; margin:0 0 20px; }
  .step { color:#58a6ff; font-weight:700; margin:22px 0 8px; border-top:1px solid #21262d; padding-top:14px; }
  .line { padding:2px 0; }
  .ok   { color:#3fb950; }
  .warn { color:#d29922; }
  .err  { color:#f85149; }
  .info { color:#8b949e; }
  .box  { background:#161b22; border:1px solid #21262d; border-radius:8px; padding:14px 16px; }
  table { border-collapse:collapse; width:100%; margin-top:8px; font-size:12.5px; }
  th,td { text-align:left; padding:4px 10px; border-bottom:1px solid #21262d; vertical-align:top; }
  th { color:#8b949e; font-weight:600; }
  code { background:#21262d; padding:1px 6px; border-radius:4px; color:#e6edf3; }
  .done { margin-top:24px; padding:14px 16px; border-radius:8px; background:#0f2417; border:1px solid #1f6f3f; color:#3fb950; font-weight:700; }
  .danger { margin-top:12px; padding:12px 16px; border-radius:8px; background:#2d1315; border:1px solid #6f2226; color:#f85149; }
  .card { background:#161b22; border:1px solid #21262d; border-radius:8px; padding:18px 20px; margin:16px 0; }
  .card h2 { font-size:15px; margin:0 0 6px; color:#fff; }
  .card.soft h2 { color:#58a6ff; }
  .card.hard h2 { color:#f85149; }
  .card p { color:#8b949e; margin:6px 0; }
  .card ul { color:#8b949e; margin:6px 0 10px; padding-left:20px; }
  input[type=text] { background:#0d1117; border:1px solid #30363d; color:#e6edf3; border-radius:6px; padding:7px 10px; font:inherit; width:260px; }
  button { font:inherit; font-weight:700; border:0; border-radius:6px; padding:8px 16px; cursor:pointer; }
  button.soft { background:#1f6feb; color:#fff; }
  button.hard { background:#a12a2f; color:#fff; }
  form { margin-top:10px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  label { color:#8b949e; }
</style>
</head>
<body>
<div class="wrap">
<h1>tt-nexus · Reset de base de datos (web)</h1>
<p class="sub"><?= h($subtitle) ?></p>
<?php
};

$pageFoot = static function (): void {
    echo '</div></body></html>';
};

// ─────────────────────────────────────────────────────────────────────────────────
// PANTALLA INICIAL: sin modo válido, o sin confirmación correcta → mostrar opciones
// ─────────────────────────────────────────────────────────────────────────────────
if (! in_array($mode, $validModes, true) || $confirm !== $dbName) {
    $pageHead('modo: local · sin terminal · SOLO PARA ENTORNOS DE TEST');

    echo '<div class="box">';
    echo '<div class="line info">Base de datos objetivo: <code>' . h($dbName) . '</code></div>';
    echo '<div class="line info">CI_ENVIRONMENT: <code>' . h($ciEnv !== '' ? $ciEnv : 'desconocido') . '</code></div>';
    echo '</div>';

    if ($mode !== '' && ! in_array($mode, $validModes, true)) {
        echo '<div class="danger">Modo desconocido: <code>' . h($mode) . '</code>. Usa <code>reset</code> o <code>hard-reset</code>.</div>';
    } elseif (in_array($mode, $validModes, true) && $confirm !== $dbName) {
        echo '<div class="danger">Confirmación incorrecta. Debes escribir exactamente el nombre de la base de datos (<code>' . h($dbName) . '</code>) para continuar.</div>';
    }

    // Tarjeta: reset suave
    echo '<div class="card soft">';
    echo '<h2>Reset (suave)</h2>';
    echo '<p>Vacía sólo los datos transaccionales / de prueba. <strong>Conserva</strong>:</p>';
    echo '<ul>';
    echo '<li>Todas las tablas de Core (<code>core_*</code>, <code>ci_sessions</code>, <code>migrations</code>) — tu login y RBAC intactos.</li>';
    echo '<li>Settings y credenciales: <code>' . h(implode('</code>, <code>', $PRESERVE_SETTINGS)) . '</code></li>';
    echo '</ul>';
    echo '<p>Después re-ejecuta los seeders para restaurar catálogos por defecto. No modifica el esquema.</p>';
    echo '<form method="get" action="reset-db.php">';
    echo '<input type="hidden" name="token" value="' . h($token) . '">';
    echo '<input type="hidden" name="mode" value="reset">';
    echo '<label>Escribe <code>' . h($dbName) . '</code>:</label>';
    echo '<input type="text" name="confirm" placeholder="' . h($dbName) . '" autocomplete="off">';
    echo '<button class="soft" type="submit">Ejecutar reset suave</button>';
    echo '</form>';
    echo '</div>';

    // Tarjeta: hard reset
    echo '<div class="card hard">';
    echo '<h2>Hard reset (destructivo)</h2>';
    echo '<p>Equivalente a <code>reset-db.sh</code>: hace <strong>DROP de TODAS las tablas</strong> (incluidas Core y settings) y vuelve a correr migraciones + seeders desde cero. <strong>Se pierde TODO</strong>: usuarios, roles, credenciales y configuración.</p>';
    echo '<form method="get" action="reset-db.php">';
    echo '<input type="hidden" name="token" value="' . h($token) . '">';
    echo '<input type="hidden" name="mode" value="hard-reset">';
    echo '<label>Escribe <code>' . h($dbName) . '</code>:</label>';
    echo '<input type="text" name="confirm" placeholder="' . h($dbName) . '" autocomplete="off">';
    echo '<button class="hard" type="submit">Ejecutar HARD reset</button>';
    echo '</form>';
    echo '</div>';

    echo '<div class="danger">Por seguridad, BORRA este archivo (public/reset-db.php) del servidor cuando termines.</div>';
    $pageFoot();
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────────
// EJECUCIÓN: modo válido + confirmación correcta
// ─────────────────────────────────────────────────────────────────────────────────
$pageHead('modo: ' . $mode . ' · BD: ' . $dbName . ' · SOLO PARA ENTORNOS DE TEST');
echo '<div class="box">';

// Flush progresivo para ver el avance en vivo.
function emit(string $html): void
{
    echo $html . "\n";
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
    }
    @flush();
}
function step(string $t): void { emit('<div class="step">==> ' . h($t) . '</div>'); }
function ok(string $t): void   { emit('<div class="line ok">  [OK] ' . h($t) . '</div>'); }
function info(string $t): void { emit('<div class="line info">  --> ' . h($t) . '</div>'); }
function warn(string $t): void { emit('<div class="line warn">  [!]  ' . h($t) . '</div>'); }
function errl(string $t): void { emit('<div class="line err">  [ERR] ' . h($t) . '</div>'); }

step('Entorno');
info('PHP version: ' . PHP_VERSION);
info('CI_ENVIRONMENT: ' . ($ciEnv !== '' ? $ciEnv : 'desconocido'));
ok('Conexión a base de datos: ' . $dbName);

$hadErrors = false;

if ($mode === 'hard-reset') {
    // ── HARD RESET: DROP de todas las tablas, luego migrar + seedear de cero ──────
    step('Hard reset — eliminando TODAS las tablas');
    try {
        $tables = $db->listTables();
        if ($tables === [] || $tables === false) {
            info('No hay tablas que eliminar (BD vacía).');
        } else {
            $db->query('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($tables as $t) {
                $db->query('DROP TABLE IF EXISTS ' . $db->protectIdentifiers($t, true, null, false));
                info('DROP ' . $t);
            }
            $db->query('SET FOREIGN_KEY_CHECKS = 1');
            ok(count($tables) . ' tabla(s) eliminada(s).');
        }
    } catch (\Throwable $e) {
        $hadErrors = true;
        errl('No se pudieron eliminar las tablas: ' . $e->getMessage());
    }

    // Migraciones desde cero (BD vacía → no requiere baseline).
    step('Migraciones (todos los módulos)');
    try {
        $migrate = \Config\Services::migrations();
        $migrate->setNamespace(null);
        $migrate->latest();
        ok('Todas las migraciones aplicadas.');
    } catch (\Throwable $e) {
        $hadErrors = true;
        errl('migrate --all — ' . $e->getMessage());
    }

    // Seeders.
    step('Seeders');
    $seeder = \Config\Database::seeder();
    foreach ($SEEDERS as [$class, $label]) {
        info('Seeder: ' . $label);
        try {
            $seeder->call($class);
            ok($label);
        } catch (\Throwable $e) {
            warn($label . ' — ' . $e->getMessage() . ' (puede ser que ya existan los datos)');
        }
    }
} else {
    // ── RESET SUAVE: TRUNCATE de tablas de datos, conservando Core + settings ─────
    step('Reset suave — vaciando datos (conservando Core y settings)');

    // Conjunto a conservar: siempre migrations/ci_sessions, cualquier tabla core_*,
    // y la lista explícita de settings/credenciales.
    $preserve = array_flip(array_merge(['migrations', 'ci_sessions'], $PRESERVE_SETTINGS));

    try {
        $tables = $db->listTables() ?: [];
        $truncated = [];
        $kept      = [];

        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $t) {
            if (isset($preserve[$t]) || str_starts_with($t, 'core_')) {
                $kept[] = $t;
                continue;
            }
            try {
                $db->query('TRUNCATE TABLE ' . $db->protectIdentifiers($t, true, null, false));
                $truncated[] = $t;
                info('TRUNCATE ' . $t);
            } catch (\Throwable $e) {
                // Fallback a DELETE si TRUNCATE falla por algún motivo.
                $db->query('DELETE FROM ' . $db->protectIdentifiers($t, true, null, false));
                $truncated[] = $t . ' (DELETE)';
                warn('TRUNCATE falló en ' . $t . ', usé DELETE — ' . $e->getMessage());
            }
        }
        $db->query('SET FOREIGN_KEY_CHECKS = 1');

        ok(count($truncated) . ' tabla(s) vaciada(s).');
        info('Conservadas (' . count($kept) . '): ' . implode(', ', $kept));
    } catch (\Throwable $e) {
        $hadErrors = true;
        errl('No se pudo vaciar la base de datos: ' . $e->getMessage());
    }

    // Re-seed: seeders idempotentes → restauran catálogos por defecto (licencias MS,
    // coordinadores GLPI) y aseguran el registro de módulos/roles en Core.
    step('Re-ejecutando seeders (restaurar catálogos por defecto)');
    $seeder = \Config\Database::seeder();
    foreach ($SEEDERS as [$class, $label]) {
        info('Seeder: ' . $label);
        try {
            $seeder->call($class);
            ok($label);
        } catch (\Throwable $e) {
            warn($label . ' — ' . $e->getMessage() . ' (puede ser que ya existan los datos)');
        }
    }
}

// ── Verificación de tablas ───────────────────────────────────────────────────────
// Auto-derivamos la lista esperada leyendo los createTable() de cada migración
// (misma lógica que db:verify-schema). Sirve sobre todo para el hard-reset.
step('Verificación de tablas');
$EXPECTED_TABLES = [];
foreach (glob(APPPATH . 'Modules/*/Database/Migrations/*.php') ?: [] as $file) {
    $code   = (string) @file_get_contents($file);
    $module = basename(dirname($file, 3));
    $origin = $module . '/' . basename($file);
    if (preg_match_all("/createTable\(\s*'([a-zA-Z0-9_]+)'/", $code, $m)) {
        foreach ($m[1] as $t) {
            $EXPECTED_TABLES[$t] = $origin;
        }
    }
}
ksort($EXPECTED_TABLES);

$hasMissingTables = false;
try {
    $existing = array_flip($db->listTables() ?: []);
    $missing  = [];
    foreach ($EXPECTED_TABLES as $table => $origin) {
        if (! isset($existing[$table])) {
            $missing[$table] = $origin;
        }
    }
    if ($missing === []) {
        ok(sprintf('Esquema OK: las %d tablas esperadas existen.', count($EXPECTED_TABLES)));
    } else {
        $hasMissingTables = true;
        foreach ($missing as $table => $origin) {
            errl('FALTA tabla: ' . $table . '  (' . $origin . ')');
        }
    }
} catch (\Throwable $e) {
    warn('No se pudo verificar tablas: ' . $e->getMessage());
}

emit('</div>'); // cierra .box
if ($hadErrors || $hasMissingTables) {
    emit('<div class="danger">Reset terminó CON ERRORES. Revisa las líneas [ERR] de arriba.</div>');
} else {
    emit('<div class="done">Reset completo (' . h($mode) . ').</div>');
}
emit('<div class="danger">Por seguridad, BORRA este archivo (public/reset-db.php) del servidor ahora que terminaste.</div>');
$pageFoot();
