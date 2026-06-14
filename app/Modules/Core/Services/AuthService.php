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

        session()->regenerate(true);
        session()->set([
            'user_id'     => $user['id'],
            'user_name'   => $user['name'],
            'user_email'  => $user['email'],
            'mfa_enabled' => (bool) ($user['mfa_enabled'] ?? false),
        ]);

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

    // -----------------------------------------------------------------------
    // MFA / TOTP
    // -----------------------------------------------------------------------

    public function generateMfaSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function getMfaOtpauthUrl(string $email, string $secret): string
    {
        $label  = rawurlencode('TT Nexus:' . $email);
        $issuer = rawurlencode('TT Nexus');
        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    public function getMfaQrCodeDataUri(string $email, string $secret): string
    {
        $url     = $this->getMfaOtpauthUrl($email, $secret);
        $options = new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\Output\QRMarkupSVG::class,
            'imageBase64' => true,
        ]);
        return (new \chillerlan\QRCode\QRCode($options))->render($url);
    }

    public function verifyTotp(string $secret, string $code, int $discrepancy = 1): bool
    {
        $code    = str_pad(preg_replace('/\D/', '', $code), 6, '0', STR_PAD_LEFT);
        $counter = (int) floor(time() / 30);
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            if (hash_equals($this->computeTotp($secret, $counter + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    public function enableMfa(int $userId, string $secret): void
    {
        $this->userModel->update($userId, [
            'mfa_secret'  => $secret,
            'mfa_enabled' => 1,
        ]);
    }

    public function completeMfa(): void
    {
        session()->set('mfa_verified', true);
    }

    private function computeTotp(string $secret, int $counter): string
    {
        $key    = $this->base32Decode($secret);
        $msg    = pack('N', 0) . pack('N', $counter);
        $hash   = hash_hmac('sha1', $msg, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $code   = (
            ((ord($hash[$offset])     & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8)  |
            (ord($hash[$offset + 3])  & 0xff)
        ) % 1000000;
        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $buf    = 0;
        $bits   = 0;
        foreach (str_split($bytes) as $byte) {
            $buf  = ($buf << 8) | ord($byte);
            $bits += 8;
            while ($bits >= 5) {
                $bits   -= 5;
                $output .= $chars[($buf >> $bits) & 31];
            }
        }
        if ($bits > 0) {
            $output .= $chars[($buf << (5 - $bits)) & 31];
        }
        return $output;
    }

    private function base32Decode(string $base32): string
    {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $base32));
        $output = '';
        $buf    = 0;
        $bits   = 0;
        foreach (str_split($base32) as $char) {
            $val = strpos($chars, $char);
            if ($val === false) {
                continue;
            }
            $buf  = ($buf << 5) | $val;
            $bits += 5;
            if ($bits >= 8) {
                $bits   -= 8;
                $output .= chr(($buf >> $bits) & 0xff);
            }
        }
        return $output;
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
