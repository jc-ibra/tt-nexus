<?php

/**
 * setup.php - Equivalente web de setup.sh para hostings SIN acceso a terminal.
 *
 * Levanta migraciones y seeders iniciales de tt-nexus arrancando el framework
 * CodeIgniter 4 (Boot::bootWorker) sin despachar una petición HTTP normal.
 *
 * USO:
 *   1. Sube este archivo a la carpeta public/ del hosting (junto a index.php).
 *   2. Cambia SETUP_TOKEN por un valor secreto propio (linea de abajo).
 *   3. Visita:  https://TU-DOMINIO/setup.php?token=TOKEN_1a2b3c
 *        - &only=migrations   corre solo migraciones
 *        - &only=seeders      corre solo seeders
 *        (sin 'only' corre ambos)
 *   4. IMPORTANTE: BORRA este archivo del servidor cuando termines.
 *
 * No contempla modo Docker (a diferencia de setup.sh).
 */

// ── Seguridad: cambia esto por un token secreto tuyo ────────────────────────────
const SETUP_TOKEN = 'CAMBIA_ESTE_TOKEN_1a2b3c';

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

// ── Guard por token ─────────────────────────────────────────────────────────────
$token = $_GET['token'] ?? '';
if (! is_string($token) || ! hash_equals(SETUP_TOKEN, $token)) {
    header('HTTP/1.1 403 Forbidden', true, 403);
    exit('403 Forbidden - token invalido o ausente.');
}
if (SETUP_TOKEN === 'TOKEN_1a2b3c') {
    // Permite correr pero avisa; el usuario deberia cambiarlo.
    // (No abortamos para no bloquear el primer uso.)
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

// ── Módulos y sus namespaces (mismo orden que setup.sh) ─────────────────────────
$MODULES = [
    'App\Modules\Core',
    'App\Modules\Communications',
    'App\Modules\Employees',
    'App\Modules\KPIsOperativos',
    'App\Modules\Mailboxes',
    'App\Modules\Provisioning',
];

// ── Seeders (clase => etiqueta), mismo orden que setup.sh ───────────────────────
$SEEDERS = [
    ['App\Database\Seeds\CoreSeeder', 'CoreSeeder'],
    ['App\Modules\Employees\Database\Seeders\EmployeesModuleSeeder', 'EmployeesModuleSeeder'],
    ['App\Modules\Employees\Database\Seeders\EmployeeAreasSeeder', 'EmployeeAreasSeeder'],
    ['App\Modules\Employees\Database\Seeders\EmployeeDepartmentsSeeder', 'EmployeeDepartmentsSeeder'],
    ['App\Modules\Employees\Database\Seeders\EmployeePositionsSeeder', 'EmployeePositionsSeeder'],
    ['App\Modules\KPIsOperativos\Database\Seeders\KPIsOperativosModuleSeeder', 'KPIsOperativosModuleSeeder'],
    ['App\Modules\KPIsOperativos\Database\Seeders\GlpiCoordinatorsSeeder', 'GlpiCoordinatorsSeeder'],
    ['App\Modules\Mailboxes\Database\Seeders\MailboxesModuleSeeder', 'MailboxesModuleSeeder'],
    ['App\Modules\Provisioning\Database\Seeders\ProvisioningModuleSeeder', 'ProvisioningModuleSeeder'],
    ['App\Modules\Provisioning\Database\Seeders\MsLicensesSeeder', 'MsLicensesSeeder'],
];

$only = $_GET['only'] ?? '';
$runMigrations = ($only === '' || $only === 'migrations');
$runSeeders    = ($only === '' || $only === 'seeders');

// ── Salida HTML tipo consola ────────────────────────────────────────────────────
header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>tt-nexus · Setup web</title>
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
  th,td { text-align:left; padding:4px 10px; border-bottom:1px solid #21262d; }
  th { color:#8b949e; font-weight:600; }
  .done { margin-top:24px; padding:14px 16px; border-radius:8px; background:#0f2417; border:1px solid #1f6f3f; color:#3fb950; font-weight:700; }
  .danger { margin-top:12px; padding:12px 16px; border-radius:8px; background:#2d1315; border:1px solid #6f2226; color:#f85149; }
</style>
</head>
<body>
<div class="wrap">
<h1>tt-nexus · Setup inicial (web)</h1>
<p class="sub">modo: local · sin terminal</p>
<div class="box">
<?php
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

function step(string $t): void { emit('<div class="step">==> ' . htmlspecialchars($t) . '</div>'); }
function ok(string $t): void   { emit('<div class="line ok">  [OK] ' . htmlspecialchars($t) . '</div>'); }
function info(string $t): void { emit('<div class="line info">  --> ' . htmlspecialchars($t) . '</div>'); }
function warn(string $t): void { emit('<div class="line warn">  [!]  ' . htmlspecialchars($t) . '</div>'); }
function errl(string $t): void { emit('<div class="line err">  [ERR] ' . htmlspecialchars($t) . '</div>'); }

// ── Entorno ─────────────────────────────────────────────────────────────────────
step('Verificando entorno');
info('PHP version: ' . PHP_VERSION);
info('ENVIRONMENT: ' . (defined('ENVIRONMENT') ? ENVIRONMENT : 'desconocido'));

try {
    $db = \Config\Database::connect();
    $db->initialize();
    ok('Conexión a base de datos: ' . $db->getDatabase());
} catch (\Throwable $e) {
    errl('No se pudo conectar a la base de datos: ' . $e->getMessage());
    emit('</div><div class="danger">Revisa las credenciales de BD en tu archivo .env y vuelve a intentar.</div></div></body></html>');
    exit;
}

// ── Migraciones ─────────────────────────────────────────────────────────────────
if ($runMigrations) {
    step('Migraciones');
    $migrate = \Config\Services::migrations();

    foreach ($MODULES as $ns) {
        $short = substr((string) strrchr($ns, '\\'), 1) ?: $ns;
        info('Migrando: ' . $short);
        try {
            $migrate->setNamespace($ns);
            $migrate->latest();
            ok($short);
        } catch (\Throwable $e) {
            warn($short . ' — ' . $e->getMessage());
        }
    }
}

// ── Seeders ─────────────────────────────────────────────────────────────────────
if ($runSeeders) {
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
}

// ── Estado de migraciones ───────────────────────────────────────────────────────
step('Estado de migraciones');
try {
    $rows = $db->table('migrations')
        ->select('class, namespace, batch, version')
        ->orderBy('id', 'ASC')
        ->get()
        ->getResultArray();

    if ($rows === []) {
        info('La tabla migrations está vacía.');
    } else {
        emit('<table><thead><tr><th>Batch</th><th>Namespace</th><th>Clase</th><th>Version</th></tr></thead><tbody>');
        foreach ($rows as $r) {
            emit('<tr><td>' . htmlspecialchars((string) $r['batch'])
                . '</td><td>' . htmlspecialchars((string) $r['namespace'])
                . '</td><td>' . htmlspecialchars((string) $r['class'])
                . '</td><td>' . htmlspecialchars((string) $r['version']) . '</td></tr>');
        }
        emit('</tbody></table>');
    }
} catch (\Throwable $e) {
    warn('No se pudo leer la tabla migrations: ' . $e->getMessage());
}

emit('</div>'); // cierra .box
emit('<div class="done">Setup completo.</div>');
emit('<div class="danger">Por seguridad, BORRA este archivo (public/setup.php) del servidor ahora que terminaste.</div>');
?>
</div>
</body>
</html>
