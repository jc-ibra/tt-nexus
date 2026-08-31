<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Communications\Services\MailerService;
use App\Modules\Core\Models\UserInvitationModel;
use App\Modules\Core\Models\UserModel;

/**
 * Issues and redeems account invitations.
 *
 * The invited account already exists in `core_users` with status `pending`
 * and its roles assigned, so the administrator sees it in the users list from
 * the moment it is invited and `AuthService::attempt()` keeps it locked out
 * (it only lets `active` accounts in). Redeeming the token is what sets the
 * password and flips the account to `active`; the two-step verification is
 * then handled by the existing MFA flow, since `AuthFilter` sends any signed
 * in user without MFA to `mfa/setup`.
 */
class InvitationService
{
    /** Hours a link stays valid; overridable with INVITATION_TTL_HOURS. */
    private const DEFAULT_TTL_HOURS = 72;

    public function __construct(
        private UserModel $userModel,
        private UserInvitationModel $invitationModel
    ) {}

    public function ttlHours(): int
    {
        $env = (int) env('INVITATION_TTL_HOURS', 0);

        return $env > 0 ? $env : self::DEFAULT_TTL_HOURS;
    }

    /**
     * Creates the token for an existing user and emails the link. Used both
     * for the first invitation and for resending it (resending revokes the
     * previous link).
     */
    public function send(int $userId): ServiceResult
    {
        $user = $this->userModel->find($userId);

        if ($user === null) {
            return ServiceResult::fail('Usuario no encontrado.');
        }

        // Only an account that has never set its own password can be invited.
        // For everyone else the right tool is "Restablecer contraseña", which
        // does not hand out an account-creation link.
        if ($user['status'] !== 'pending') {
            return ServiceResult::fail('Este usuario ya tiene su cuenta activada. Para darle acceso de nuevo usa "Restablecer contraseña".');
        }

        $token = $this->invitationModel->issue(
            $userId,
            $user['email'],
            session()->get('user_id') ? (int) session()->get('user_id') : null,
            $this->ttlHours()
        );

        $url = base_url('invitation/' . $token);

        if (ENVIRONMENT === 'development') {
            log_message('info', "[Invitation] Link for {$user['email']}: {$url}");
        }

        $sent = $this->sendEmail($user['email'], (string) $user['name'], $url);

        if (! $sent['success']) {
            log_message('error', '[Invitation] Could not send invitation to ' . $user['email'] . ': ' . $sent['error']);

            return ServiceResult::fail(
                'La invitación se generó pero el correo no pudo enviarse: ' . $sent['error']
                . ' Revisa la configuración SMTP y usa "Reenviar invitación".'
            );
        }

        return ServiceResult::ok(
            ['url' => $url],
            'Invitación enviada a ' . $user['email'] . '. El enlace vence en ' . $this->ttlHours() . ' horas.'
        );
    }

    public function revoke(int $userId): ServiceResult
    {
        if ($this->userModel->find($userId) === null) {
            return ServiceResult::fail('Usuario no encontrado.');
        }

        $this->invitationModel->revokeFor($userId);

        return ServiceResult::ok(null, 'Invitación cancelada. El enlace enviado dejó de funcionar.');
    }

    public function pendingFor(int $userId): ?array
    {
        $pending = $this->invitationModel->pendingFor($userId);

        if ($pending === null) {
            return null;
        }

        $pending['is_expired'] = strtotime($pending['expires_at']) < time();

        return $pending;
    }

    /**
     * Resolves a token to its invitation plus the target user, or null when
     * the link is invalid, already used, revoked or expired.
     *
     * @return array{invitation: array, user: array}|null
     */
    public function resolve(string $token): ?array
    {
        $invitation = $this->invitationModel->findValidByToken($token);

        if ($invitation === null) {
            return null;
        }

        $user = $this->userModel->find((int) $invitation['user_id']);

        if ($user === null) {
            return null;
        }

        return ['invitation' => $invitation, 'user' => $user];
    }

    /**
     * Redeems the invitation: the invited person sets their own name and
     * password, the account becomes active and a session is opened so the
     * MFA setup screen is the next thing they see.
     */
    public function accept(
        string $token,
        string $name,
        string $password,
        string $passwordConfirm,
        bool $startSession = true
    ): ServiceResult
    {
        $resolved = $this->resolve($token);

        if ($resolved === null) {
            return ServiceResult::fail('El enlace de invitación es inválido, ya fue usado o expiró. Pide al administrador que te lo reenvíe.');
        }

        $name = trim($name);

        if ($name === '') {
            return ServiceResult::fail(['name' => 'Escribe tu nombre completo.']);
        }

        if (mb_strlen($name) > 120) {
            return ServiceResult::fail(['name' => 'El nombre no puede exceder 120 caracteres.']);
        }

        if (strlen($password) < 8) {
            return ServiceResult::fail(['password' => 'La contraseña debe tener al menos 8 caracteres.']);
        }

        if ($password !== $passwordConfirm) {
            return ServiceResult::fail(['password_confirm' => 'Las contraseñas no coinciden.']);
        }

        $user   = $resolved['user'];
        $userId = (int) $user['id'];

        $this->userModel->update($userId, [
            'id'       => $userId,
            'name'     => $name,
            'email'    => $user['email'],
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'status'   => 'active',
        ]);

        $this->invitationModel->markAccepted((int) $resolved['invitation']['id']);

        $fresh = $this->userModel->find($userId);

        // The API redeems the same invitation statelessly; only the web flow
        // needs the session opened so the MFA setup follows immediately.
        if ($startSession) {
            service('authService')->startSession($fresh);
        }

        return ServiceResult::ok($fresh, 'Cuenta activada. Ahora configura tu verificación en dos pasos.');
    }

    /**
     * @return array{success: bool, error: string}
     */
    private function sendEmail(string $email, string $name, string $url): array
    {
        $smtp      = service('appSettings')->getSmtp();
        $config    = new \Config\Email();
        $fromEmail = $smtp['smtp_from_email'] !== '' ? $smtp['smtp_from_email'] : $config->fromEmail;
        $fromName  = $smtp['smtp_from_name'] !== '' ? $smtp['smtp_from_name'] : $config->fromName;

        if ((string) $fromEmail === '') {
            return ['success' => false, 'error' => 'No hay remitente configurado en Ajustes > SMTP.'];
        }

        // Sent through MailerService so it uses the SMTP saved in Ajustes,
        // the same transport as the rest of the platform.
        return (new MailerService())->sendSingle(
            $email,
            $name,
            (string) $fromEmail,
            (string) $fromName,
            'Invitación para crear tu cuenta en Nexus',
            $this->buildBody($name, $url)
        );
    }

    private function buildBody(string $name, string $url): string
    {
        $greeting = $name !== '' ? 'Hola ' . esc($name) . ',' : 'Hola,';
        $hours    = $this->ttlHours();
        $safeUrl  = esc($url, 'attr');

        return <<<HTML
<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#202223;max-width:560px;">
  <p>{$greeting}</p>
  <p>Se creó una cuenta para ti en Nexus. Para activarla necesitas definir tu propia contraseña y configurar la verificación en dos pasos.</p>
  <p style="margin:28px 0;">
    <a href="{$safeUrl}" style="background:#1773C8;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block;">Activar mi cuenta</a>
  </p>
  <p style="color:#6D7175;font-size:13px;">Si el botón no funciona, copia y pega esta dirección en tu navegador:<br>
    <span style="word-break:break-all;">{$safeUrl}</span>
  </p>
  <p style="color:#6D7175;font-size:13px;">El enlace es de un solo uso y vence en {$hours} horas. Si no esperabas esta invitación, ignora este correo.</p>
</div>
HTML;
    }
}
