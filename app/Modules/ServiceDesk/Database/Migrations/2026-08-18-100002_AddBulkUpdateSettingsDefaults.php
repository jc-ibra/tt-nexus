<?php

declare(strict_types=1);

namespace App\Modules\ServiceDesk\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Defaults del modo actualización/cierre masivo en servicedesk_settings.
 *
 * Idempotente: solo inserta las claves ausentes, nunca pisa lo que el admin
 * guardó. Los accessors de ServiceDeskSettings ya caen a estos mismos defaults
 * cuando la fila no existe; esta migración solo las hace visibles en el
 * formulario del SuperAdmin y mantiene la tabla completa.
 */
class AddBulkUpdateSettingsDefaults extends Migration
{
    /** @var array<string,string> key => default */
    private array $defaults = [
        // Apagado por default: es una operación destructiva, el SuperAdmin la habilita.
        'update_enabled'            => '0',
        // Reabrir tickets cerrados para poder planchar y volver a cerrarlos.
        'update_reopen_closed'      => '1',
        // Releer el ticket tras escribir y reportar DESVIACION si no se quedó el valor.
        'update_verify_writes'      => '1',
        // Rearmar CLIENTE - SUCURSAL - TITULO cuando cambia la categoría pero no el título.
        'update_rehomologate_title' => '0',
        // Texto de la solución cuando la fila no trae columna SOLUCION.
        'update_solution_text'      => 'Cierre masivo desde Nexus. Ticket atendido y validado.',
    ];

    public function up(): void
    {
        $table = $this->db->table('servicedesk_settings');
        $now   = date('Y-m-d H:i:s');

        foreach ($this->defaults as $key => $value) {
            $exists = $this->db->table('servicedesk_settings')
                ->where('key', $key)
                ->countAllResults();
            if ($exists === 0) {
                $table->insert(['key' => $key, 'value' => $value, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        $this->db->table('servicedesk_settings')
            ->whereIn('key', array_keys($this->defaults))
            ->delete();
    }
}
