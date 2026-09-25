<?php

declare(strict_types=1);

namespace App\Modules\MailDispatch\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CSAT survey sent to the requester below the agent's signature on every reply
 * from Nexus. Two tables, not one, because a token is a live object (issued,
 * reused across every reply of the thread, extended, opened, expired, revoked)
 * while a response is an immutable analytic fact:
 *
 *  - maildispatch_survey_tokens: at most one LIVE row per conversation
 *    (responded_at IS NULL AND revoked_at IS NULL). Reused across replies so
 *    the same link keeps working in every email of the thread; each reuse
 *    extends expires_at. `token_plain_enc` holds the plaintext token
 *    encrypted with the shared CredentialCipher (see
 *    MailDispatchSettingsModel) so the *same* link can be embedded again in a
 *    second reply without ever storing it in clear text; it is nulled out the
 *    moment the token is responded or revoked, since it no longer needs to be
 *    resent. Only the sha256 hash is used to resolve an incoming request.
 *  - maildispatch_survey_responses: one immutable row per answered token.
 *    UNIQUE(token_id) makes a double answer impossible at the schema level —
 *    two concurrent POSTs race on the index, not on an `if` in PHP.
 *
 * A GET never consumes the token (Outlook Safe Links and mail-gateway
 * scanners open the link before the human does); only a POST does.
 */
class CreateMailDispatchSurveyTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'conversation_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'token_hash'       => ['type' => 'VARCHAR', 'constraint' => 64], // sha256(plain), hex
            'token_plain_enc'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], // CredentialCipher; nulled once responded/revoked
            'cycle'            => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 1],
            'issued_by'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true], // agent whose reply first carried it
            'sent_count'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'last_sent_at'     => ['type' => 'DATETIME', 'null' => true],
            'last_sent_to'     => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'last_sent_cc'     => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'expires_at'       => ['type' => 'DATETIME'],
            'first_opened_at'  => ['type' => 'DATETIME', 'null' => true], // first GET; never consumes
            'last_opened_at'   => ['type' => 'DATETIME', 'null' => true],
            'open_count'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'open_ip_hash'     => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'open_user_agent'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'responded_at'     => ['type' => 'DATETIME', 'null' => true], // set by the POST
            'revoked_at'       => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey(['conversation_id', 'cycle']);
        $this->forge->addKey('responded_at');
        $this->forge->addKey('expires_at');
        $this->forge->addForeignKey('conversation_id', 'maildispatch_conversations', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('issued_by', 'core_users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('maildispatch_survey_tokens');

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'token_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'conversation_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'agent_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true], // snapshot of the owner at response time
            'rating'           => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true], // 1..5
            'resolved'         => ['type' => 'ENUM', 'constraint' => ['yes', 'partial', 'no']],
            'comment'          => ['type' => 'TEXT', 'null' => true],
            'requester_email'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], // snapshot
            'ip_hash'          => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'user_agent'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'responded_at'     => ['type' => 'DATETIME'],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_id'); // one response per token, enforced by the schema
        $this->forge->addKey('conversation_id');
        $this->forge->addKey(['agent_id', 'responded_at']);
        $this->forge->addKey('responded_at');
        $this->forge->addKey('rating');
        $this->forge->addForeignKey('token_id', 'maildispatch_survey_tokens', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('conversation_id', 'maildispatch_conversations', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('agent_id', 'core_users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('maildispatch_survey_responses');
    }

    public function down(): void
    {
        $this->forge->dropTable('maildispatch_survey_responses', true);
        $this->forge->dropTable('maildispatch_survey_tokens', true);
    }
}
