<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Full-text search over the message body.
 *
 * The stored `body` is Outlook HTML: 85 KB on average, of which barely a fifth
 * is actual content. Searching it with LIKE would scan the whole table on every
 * keystroke (tens of GB on a real mailbox) and would match CSS and markup, the
 * same class of noise already fixed for previews.
 *
 * So the search runs over a plain-text projection instead: `body_text`, written
 * by MessageModel from `ForwardParser::plainText()` and covered by a FULLTEXT
 * index. Roughly a quarter of the bytes, no markup, and an inverted-index lookup
 * instead of a table scan.
 *
 * OPERACIÓN EN PRODUCCIÓN — leer antes de migrar:
 *
 *  1. El primer índice FULLTEXT de una tabla InnoDB **reconstruye la tabla**
 *     (MySQL debe agregar su columna interna FTS_DOC_ID) y no admite escrituras
 *     concurrentes. Detén el cron de `maildispatch:sync-mailbox` mientras corre.
 *  2. Esta migración se aplica ANTES del backfill a propósito: reconstruye con
 *     `body_text` vacía, que es el momento más barato posible, y el índice se
 *     llena después fila por fila, en línea.
 *  3. Después: `php spark maildispatch:backfill-body-text` (por lotes, con
 *     pausa configurable). Hasta que termine, la búsqueda por cuerpo solo
 *     encuentra los mensajes ya procesados; la búsqueda por asunto, solicitante
 *     y folio sigue funcionando igual desde el primer momento.
 */
class AddBodyTextSearchToMessages extends Migration
{
    private const TABLE = 'maildispatch_messages';
    private const INDEX = 'ft_md_msg_body_text';

    public function up(): void
    {
        if (! $this->db->fieldExists('body_text', self::TABLE)) {
            $this->forge->addColumn(self::TABLE, [
                'body_text' => [
                    'type'       => 'MEDIUMTEXT',
                    'null'       => true,
                    'after'      => 'body_is_html',
                    'comment'    => 'Proyección en texto plano de body, para búsqueda FULLTEXT',
                ],
            ]);
        }

        if (! $this->indexExists()) {
            $this->db->query('ALTER TABLE `' . self::TABLE . '` ADD FULLTEXT `' . self::INDEX . '` (`body_text`)');
        }
    }

    public function down(): void
    {
        if ($this->indexExists()) {
            $this->db->query('ALTER TABLE `' . self::TABLE . '` DROP INDEX `' . self::INDEX . '`');
        }
        if ($this->db->fieldExists('body_text', self::TABLE)) {
            $this->forge->dropColumn(self::TABLE, 'body_text');
        }
    }

    private function indexExists(): bool
    {
        return $this->db->table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $this->db->getDatabase())
            ->where('TABLE_NAME', self::TABLE)
            ->where('INDEX_NAME', self::INDEX)
            ->countAllResults() > 0;
    }
}
