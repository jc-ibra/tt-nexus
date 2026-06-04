<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\PasswordResetModel;
use App\Modules\Core\Models\UserModel;
use CodeIgniter\Throttle\Throttler;

class AuthService
{
    private const MAX_ATTEMPTS     = 5;
    private const LOCKOUT_SECONDS  = 300; // 5 minutes

    public function __construct(
        private UserModel $userModel,
        private PasswordResetModel $resetModel
    ) {}

    public function attempt(string $email, string $password, string $ip): ServiceResult
    {
        $throttler = service('throttler');
        $key       = 'login_' . md5($ip . $email);

        if ($throttler->check($key, self::MAX_ATTEMPTS, self::LOCKOUT_SECONDS) === false) {
            $seconds = $throttler->getTokentime();

            return ServiceResult::fail(
                "Demasiados intentos fallidos. Intenta de nuevo en {$seconds} segundos."
            );
        }

        $user = $this->userModel->findByEmail($email);

        if ($user === null || ! password_verify($password, $user['password'])) {
            return ServiceResult::fail('Correo o contraseña incorrectos.');
        }

        if ($user['status'] !== 'active') {
            return ServiceResult::fail('Tu cuenta está desactivada. Contacta al administrador.');
        }

        $throttler->remove($key);

        session()->set([
            'user_id'    => $user['id'],
            'user_name'  => $user['name'],
            'user_email' => $user['email'],
        ]);
        session()->regenerate(true);

        return ServiceResult::ok($user);
    }

    public function sendResetLink(string $email): ServiceResult
    {
        $user = $this->userModel->findByEmail($email);

        // Don't reveal whether the email exists
        if ($user === null) {
            return ServiceResult::ok(null, 'Si el correo existe, recibirás un enlace en breve.');
        }

        $token   = $this->resetModel->createToken($email);
        $resetUrl = base_url("password/reset/{$token}");

        // Log token in dev so it can be tested without SMTP
        if (ENVIRONMENT === 'development') {
            log_message('info', "Password reset token for {$email}: {$token}");
            log_message('info', "Reset URL: {$resetUrl}");
        }

        $emailSvc = service('email');
        $emailSvc->setTo($email)
            ->setSubject('Restablecer contraseña — Nexus')
            ->setMessage(
                "<p>Hola {$user['name']},</p>"
                . "<p>Haz clic en el enlace para restablecer tu contraseña (válido 1 hora):</p>"
                . "<p><a href=\"{$resetUrl}\">{$resetUrl}</a></p>"
                . "<p>Si no solicitaste este cambio, ignora este correo.</p>"
            )
            ->setMailType('html');

        $emailSvc->send(false); // false = don't throw on failure in production

        return ServiceResult::ok(null, 'Si el correo existe, recibirás un enlace en breve.');
    }

    public function resetPassword(string $token, string $password, string $passwordConfirm): ServiceResult
    {
        if ($password !== $passwordConfirm) {
            return ServiceResult::fail(['password_confirm' => 'Las contraseñas no coinciden.']);
        }

        if (strlen($password) < 8) {
            return ServiceResult::fail(['password' => 'La contraseña debe tener al menos 8 caracteres.']);
        }

        $record = $this->resetModel->findValidByToken($token);

        if ($record === null) {
            return ServiceResult::fail('El enlace es inválido o ha expirado.');
        }

        $user = $this->userModel->findByEmail($record['email']);

        if ($user === null) {
            return ServiceResult::fail('Usuario no encontrado.');
        }

        $this->userModel->update($user['id'], [
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        $this->resetModel->deleteByEmail($record['email']);

        return ServiceResult::ok(null, 'Contraseña actualizada correctamente.');
    }
}
