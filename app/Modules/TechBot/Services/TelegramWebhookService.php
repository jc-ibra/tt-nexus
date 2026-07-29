<?php

declare(strict_types=1);

namespace App\Modules\TechBot\Services;

use App\Modules\Employees\Models\EmployeeModel;
use App\Modules\Provisioning\Models\ProvisioningExternalAccountModel;
use App\Modules\TechBot\Models\TelegramLinkModel;

/**
 * Entry point for Telegram updates. Parses the raw update, resolves the chat to
 * a technician, and routes:
 *   - unlinked chat  -> registration flow (spec §10)
 *   - inactive link  -> rejected (spec §15)
 *   - active link    -> handed to ConversationService (spec §6-7, §11)
 *
 * The controller has already validated the shared secret, so this service trusts
 * the payload's origin but still validates the chat id and throttles registration
 * attempts.
 */
class TelegramWebhookService
{
    public function __construct(
        private TechBotSettingsService $settings,
        private TelegramApiService $telegram,
        private ConversationService $conversation,
        private TelegramLinkModel $links,
        private EmployeeModel $employees,
        private ProvisioningExternalAccountModel $externalAccounts,
    ) {}

    /**
     * Processes one Telegram update. Never throws to the caller: any failure is
     * logged so the webhook can still return 200 quickly (spec §16).
     */
    public function process(array $update): void
    {
        try {
            $event = $this->normalize($update);
        } catch (\Throwable $e) {
            log_message('error', '[TechBot][Webhook] normalize failed: ' . $e->getMessage());
            return;
        }

        if ($event === null) {
            return; // update type we do not handle (poll, my_chat_member, etc.)
        }

        $chatId = $event['chat_id'];
        if ($chatId <= 0) {
            return; // spec §15: only positive chat ids are valid.
        }

        if (! $this->settings->botReady()) {
            // No token/cipher: we cannot reply, so just stop.
            return;
        }

        $link = $this->links->findByChatId($chatId);

        if ($link !== null && $link['status'] !== 'active') {
            $this->telegram->sendMessage($chatId, 'Tu vinculación está desactivada. Contacta a tu supervisor para reactivarla.');
            return;
        }

        if ($link === null) {
            $this->handleRegistration($event);
            return;
        }

        // Hand an active, employee-enriched link to the state machine.
        $active = $this->links->findActiveWithEmployeeByChatId($chatId) ?? $link;
        $this->conversation->handle($active, $event);
    }

    // ------------------------------------------------------------------
    // Registration (spec §10) — for chats with no link yet
    // ------------------------------------------------------------------

    private function handleRegistration(array $event): void
    {
        $chatId = $event['chat_id'];
        $type   = $event['type'];

        if ($type === 'command' && $event['command'] === 'start') {
            $this->telegram->sendMessage(
                $chatId,
                'Bienvenido al Bot de Soporte. Para vincular tu cuenta, envía tu número de empleado.'
            );
            return;
        }

        if ($type !== 'text') {
            $this->telegram->sendMessage($chatId, 'Primero vincula tu cuenta: envía /start y luego tu número de empleado.');
            return;
        }

        // Anti-bruteforce: cap registration attempts per chat (spec §15).
        $throttler = service('throttler');
        if ($throttler->check('techbot_reg_' . $chatId, 5, 15 * MINUTE) === false) {
            $this->telegram->sendMessage($chatId, 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.');
            return;
        }

        $employeeNumber = trim($event['text']);
        if ($employeeNumber === '' || ! preg_match('/^[A-Za-z0-9\-]{1,20}$/', $employeeNumber)) {
            $this->telegram->sendMessage($chatId, 'Envía un número de empleado válido. Ejemplo: 3002');
            return;
        }

        $employee = $this->employees->where('employee_number', $employeeNumber)->first();
        if ($employee === null) {
            $this->telegram->sendMessage($chatId, 'No se encontró un empleado con ese número. Verifica e intenta de nuevo.');
            return;
        }

        $glpiUserId = $this->externalAccounts->glpiUserIdByEmployeeNumber($employeeNumber);
        if ($glpiUserId === null) {
            $this->telegram->sendMessage($chatId, 'Tu cuenta no tiene acceso a GLPI. Contacta a Mesa de Ayuda.');
            return;
        }

        // One employee = one Telegram account.
        if ($this->links->findByEmployeeId((int) $employee['id']) !== null) {
            $this->telegram->sendMessage($chatId, 'Este empleado ya está vinculado a otra cuenta de Telegram. Contacta a tu supervisor.');
            return;
        }

        try {
            $this->links->insert([
                'telegram_chat_id'    => $chatId,
                'telegram_username'   => $event['username'] !== '' ? $event['username'] : null,
                'telegram_first_name' => $event['first_name'] !== '' ? $event['first_name'] : null,
                'employee_id'         => (int) $employee['id'],
                'glpi_user_id'        => $glpiUserId,
                'status'              => 'active',
                'verified_at'         => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[TechBot][Webhook] link insert failed: ' . $e->getMessage());
            $this->telegram->sendMessage($chatId, 'No se pudo completar la vinculación. Intenta de nuevo o contacta a Mesa de Ayuda.');
            return;
        }

        $this->telegram->sendMessage($chatId, $this->settings->welcomeMessage() . "\n\nUsa /tickets para ver tus tickets asignados.");
    }

    // ------------------------------------------------------------------
    // Update normalization
    // ------------------------------------------------------------------

    /**
     * Reduces a raw Telegram update to a flat event, or null when it is a type we
     * do not act on.
     *
     * @return array{type:string,chat_id:int,text:string,command:string,args:string,data:string,callback_id:string,message_id:int,photo_file_id:string,username:string,first_name:string}|null
     */
    private function normalize(array $update): ?array
    {
        if (isset($update['callback_query'])) {
            $cq   = $update['callback_query'];
            $from = $cq['from'] ?? [];
            $chat = $cq['message']['chat'] ?? [];
            return $this->event([
                'type'        => 'callback',
                'chat_id'     => (int) ($chat['id'] ?? ($from['id'] ?? 0)),
                'data'        => (string) ($cq['data'] ?? ''),
                'callback_id' => (string) ($cq['id'] ?? ''),
                'message_id'  => (int) ($cq['message']['message_id'] ?? 0),
                'username'    => (string) ($from['username'] ?? ''),
                'first_name'  => (string) ($from['first_name'] ?? ''),
            ]);
        }

        $message = $update['message'] ?? $update['edited_message'] ?? null;
        if (! is_array($message)) {
            return null;
        }

        $from   = $message['from'] ?? [];
        $chat   = $message['chat'] ?? [];
        $chatId = (int) ($chat['id'] ?? 0);
        $base   = [
            'chat_id'    => $chatId,
            'message_id' => (int) ($message['message_id'] ?? 0),
            'username'   => (string) ($from['username'] ?? ''),
            'first_name' => (string) ($from['first_name'] ?? ''),
        ];

        // Photo (largest size wins).
        if (! empty($message['photo']) && is_array($message['photo'])) {
            $sizes  = $message['photo'];
            $last   = end($sizes);
            $fileId = (string) ($last['file_id'] ?? '');
            if ($fileId !== '') {
                return $this->event($base + ['type' => 'photo', 'photo_file_id' => $fileId]);
            }
        }

        $text = trim((string) ($message['text'] ?? ''));
        if ($text === '') {
            return null; // stickers, locations, etc. — unsupported, ignored silently
        }

        // Command?
        if (str_starts_with($text, '/')) {
            $parts   = preg_split('/\s+/', $text, 2);
            $command = ltrim((string) $parts[0], '/');
            // Strip the @botname suffix Telegram appends in groups.
            $command = strtolower(explode('@', $command)[0]);
            return $this->event($base + [
                'type'    => 'command',
                'command' => $command,
                'args'    => trim((string) ($parts[1] ?? '')),
            ]);
        }

        return $this->event($base + ['type' => 'text', 'text' => $text]);
    }

    /** Fills the event shape with defaults so callers can read any key. */
    private function event(array $partial): array
    {
        return array_merge([
            'type'          => '',
            'chat_id'       => 0,
            'text'          => '',
            'command'       => '',
            'args'          => '',
            'data'          => '',
            'callback_id'   => '',
            'message_id'    => 0,
            'photo_file_id' => '',
            'username'      => '',
            'first_name'    => '',
        ], $partial);
    }
}
