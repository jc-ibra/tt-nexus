<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

/**
 * File lock shared by the cron-driven commands to keep overlapping runs out.
 *
 * A plain "the file exists, so someone is running" check is not enough: PHP's
 * fatal errors (a memory limit, a segfault) are handled by CI4's shutdown
 * handler, which calls exit() and therefore skips every shutdown callback
 * registered afterwards — the lock file outlives the crashed process and every
 * later run refuses to start until someone deletes it by hand.
 *
 * So a lock is also considered free when the process that wrote it is gone, or
 * when it is older than the run could plausibly take.
 *
 *   $lock = RunLock::acquire('maildispatch_sync', 1800);
 *   if ($lock === null) { return; }   // another run really is in progress
 *   try { … } finally { $lock->release(); }
 */
final class RunLock
{
    private bool $released = false;

    private function __construct(private readonly string $path) {}

    /**
     * Returns the held lock, or null when another run owns it.
     *
     * @param string $name        lock identifier (no extension, no directory)
     * @param int    $staleAfter  seconds after which an untouched lock is
     *                            assumed dead; 0 disables the age check
     */
    public static function acquire(string $name, int $staleAfter = 3600): ?self
    {
        $path = sys_get_temp_dir() . '/' . $name . '.lock';

        if (is_file($path)) {
            if (! self::isStale($path, $staleAfter)) {
                return null;
            }
            log_message('warning', "[RunLock] Lock obsoleto liberado: {$path}");
            @unlink($path);
        }

        // 'x' fails if the file appeared meanwhile, so two runs racing here
        // cannot both win.
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            return null;
        }
        fwrite($handle, (string) getmypid());
        fclose($handle);

        $lock = new self($path);

        // Best effort for the ordinary paths (normal end, uncaught exception);
        // a fatal error skips this, which is what the staleness check covers.
        register_shutdown_function(static fn() => $lock->release());

        return $lock;
    }

    public function release(): void
    {
        if ($this->released) {
            return;
        }
        $this->released = true;
        @unlink($this->path);
    }

    /** A lock is stale when it is too old or its writer is no longer running. */
    private static function isStale(string $path, int $staleAfter): bool
    {
        $mtime = @filemtime($path);
        if ($mtime === false) {
            return true; // vanished between the check and here
        }
        if ($staleAfter > 0 && (time() - $mtime) >= $staleAfter) {
            return true;
        }

        $pid = (int) trim((string) @file_get_contents($path));
        if ($pid <= 0) {
            return true; // truncated or never written
        }

        if (! function_exists('posix_kill')) {
            return false; // cannot tell; trust the age check alone
        }

        if (@posix_kill($pid, 0)) {
            return false;
        }

        // EPERM (1 on Linux and macOS) means the process does exist, it just
        // belongs to another user — so the lock is still held.
        return posix_get_last_error() !== 1;
    }
}
