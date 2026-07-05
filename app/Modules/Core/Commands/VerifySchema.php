<?php

declare(strict_types=1);

namespace App\Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Config\Services;
use Throwable;

/**
 * Verifica que TODAS las tablas que las migraciones dicen crear existan
 * físicamente en la base de datos.
 *
 * Auto-deriva la lista esperada leyendo las llamadas `createTable('x')` de cada
 * archivo de migración de cada módulo, así que NO hay lista que mantener a mano:
 * si agregas un módulo o una migración, la verificación se entera sola.
 *
 * Detecta dos fallas típicas que dejaban tablas faltantes en staging/prod:
 *   1. Una migración que nunca se aplicó (tabla ausente).
 *   2. El desync clásico: la migración figura en la tabla `migrations` pero la
 *      tabla física NO existe (import parcial / parche SQL manual). En ese estado
 *      `migrate` reporta "nada nuevo" pero la app truena al consultar la tabla.
 *
 * Uso:
 *   php spark db:verify-schema         # verifica; exit code 1 si falta algo
 *   php spark db:verify-schema -v      # además lista todas las tablas esperadas
 */
class VerifySchema extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:verify-schema';
    protected $description = 'Verifica que cada tabla declarada por las migraciones exista físicamente.';
    protected $usage       = 'db:verify-schema [-v]';
    protected $options     = [
        '-v' => 'Modo verboso: lista todas las tablas esperadas y su origen.',
    ];

    public function run(array $params): void
    {
        $verbose = array_key_exists('v', CLI::getOptions());

        // 1. Descubrir migraciones de todos los namespaces de módulos.
        $expected = $this->expectedTables();
        if ($expected === []) {
            CLI::error('No se encontró ninguna migración con createTable(). ¿Ruta de módulos correcta?');
            return;
        }

        // 2. Tablas físicas reales.
        try {
            $db       = Database::connect();
            $existing = array_flip($db->listTables());
        } catch (Throwable $e) {
            CLI::error('No se pudo conectar a la base de datos: ' . $e->getMessage());
            exit(1);
        }

        if ($verbose) {
            CLI::write('Tablas esperadas (según migraciones):', 'cyan');
            foreach ($expected as $table => $origin) {
                CLI::write(sprintf('  %-40s %s', $table, $origin), 'dark_gray');
            }
            CLI::newLine();
        }

        // 3. Comparar.
        $missing = [];
        foreach ($expected as $table => $origin) {
            if (! isset($existing[$table])) {
                $missing[$table] = $origin;
            }
        }

        if ($missing === []) {
            CLI::write(sprintf('✓ Esquema OK: las %d tablas esperadas existen.', count($expected)), 'green');
            return;
        }

        CLI::error(sprintf('✗ FALTAN %d de %d tablas:', count($missing), count($expected)));
        foreach ($missing as $table => $origin) {
            CLI::write('  - ' . $table . '  (' . $origin . ')', 'red');
        }
        CLI::newLine();
        CLI::write('Remediación:', 'yellow');
        CLI::write('  1. Corre `php spark migrate --all` y revisa si aplica las faltantes.', 'yellow');
        CLI::write('  2. Si migrate dice "nothing to migrate" pero la tabla no existe, la tabla', 'yellow');
        CLI::write('     `migrations` está desincronizada: borra los registros de esas migraciones', 'yellow');
        CLI::write('     y vuelve a correr migrate para recrearlas.', 'yellow');
        exit(1);
    }

    /**
     * Escanea los archivos de migración de cada módulo y extrae los nombres de
     * tabla creados vía `createTable('...')` (Forge) o `CREATE TABLE ...` (SQL crudo).
     *
     * @return array<string, string> tabla => "Modulo/archivo" de origen
     */
    private function expectedTables(): array
    {
        $tables  = [];
        $modules = APPPATH . 'Modules';

        foreach (glob($modules . '/*/Database/Migrations/*.php') ?: [] as $file) {
            $code   = (string) file_get_contents($file);
            $module = basename(dirname($file, 3));
            $origin = $module . '/' . basename($file);

            // Forge: $this->forge->createTable('nombre')
            if (preg_match_all("/createTable\(\s*'([a-zA-Z0-9_]+)'/", $code, $m)) {
                foreach ($m[1] as $t) {
                    $tables[$t] = $origin;
                }
            }
            // SQL crudo: CREATE TABLE [IF NOT EXISTS] `nombre` | nombre
            if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $code, $m)) {
                foreach ($m[1] as $t) {
                    $tables[$t] = $origin;
                }
            }
        }

        ksort($tables);

        return $tables;
    }
}
