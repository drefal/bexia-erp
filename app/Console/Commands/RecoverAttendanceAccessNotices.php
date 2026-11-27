<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Attendance\AttendanceAccessNoticeRecovery;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class RecoverAttendanceAccessNotices extends Command
{
    protected $signature =
        'rrhh:recover-access-notices
        {--limit=50 : Maximum decisions per execution}';

    protected $description =
        'Recover missing notifications for pending HR access decisions';

    public function handle(): int
    {
        $rawLimit = (string) $this->option('limit');

        if (
            !preg_match('/^[1-9][0-9]?$/D', $rawLimit)
            || (int) $rawLimit >
                AttendanceAccessNoticeRecovery::MAX_BATCH
        ) {
            $this->error('INVALID_RECOVERY_LIMIT');

            return self::FAILURE;
        }

        $limit = (int) $rawLimit;

        $directory = (string) config(
            'attendance_realtime.notice_recovery_state_dir',
            storage_path(
                'app/private/attendance-access-recovery'
            )
        );

        $lock = null;

        try {

            if (
                !is_dir($directory)
                && !@mkdir($directory, 0700, true)
                && !is_dir($directory)
            ) {
                throw new RuntimeException(
                    'RECOVERY_STATE_DIRECTORY_UNAVAILABLE'
                );
            }

            $lock = @fopen(
                $directory . '/runner.lock',
                'c+'
            );

            if ($lock === false) {

                throw new RuntimeException(
                    'RECOVERY_LOCAL_LOCK_UNAVAILABLE'
                );
            }

            @chmod(
                $directory . '/runner.lock',
                0600
            );

            if (
                !flock(
                    $lock,
                    LOCK_EX | LOCK_NB
                )
            ) {
                $this->line(json_encode([
                    'status' => 'local_busy',
                ], JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }

            $day = (
                new DateTimeImmutable(
                    'now',
                    new DateTimeZone(
                        'America/Mexico_City'
                    )
                )
            )->format('Y-m-d');

            $statePath = $directory . '/cursor.json';

            $cursor = $this->readCursor(
                $statePath,
                $day
            );

            $started = hrtime(true);

            $result =
                AttendanceAccessNoticeRecovery::run(
                    $cursor,
                    $limit
                );

            $result['cursor_before'] = $cursor;

            $result['elapsed_ms'] = round(
                (hrtime(true) - $started)
                    / 1000000,
                2
            );

            $result['day'] = $day;

            if ($result['status'] !== 'busy') {

                $this->writeCursor(
                    $statePath,
                    [
                        'version' => 1,
                        'day' => $day,
                        'cursor' =>
                            (int) $result['next_cursor'],
                        'updated_at' =>
                            now()->toIso8601String(),
                    ]
                );
            }

            $this->line(
                json_encode(
                    $result,
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                )
            );

            // Avoid producing a large log every minute
            // when there is no actual work.

            if (
                ($result['created'] ?? 0) > 0
                || ($result['errors'] ?? 0) > 0
            ) {

                try {

                    if ($result['errors'] > 0) {

                        Log::warning(
                            'Attendance notice recovery completed with errors',
                            $result
                        );

                    } else {

                        Log::info(
                            'Attendance notices recovered',
                            $result
                        );
                    }

                } catch (Throwable) {

                    // State and notices were already saved.
                }
            }

            return ($result['errors'] ?? 0) > 0
                ? self::FAILURE
                : self::SUCCESS;

        } catch (Throwable $error) {

            $this->line(json_encode([
                'status' => 'failed',
                'exception_type' =>
                    get_class($error),
            ], JSON_THROW_ON_ERROR));

            try {

                Log::warning(
                    'Attendance recovery command failed',
                    [
                        'exception_type' =>
                            get_class($error),
                    ]
                );

            } catch (Throwable) {

                // Do not mask the original failure.
            }

            return self::FAILURE;

        } finally {

            if (is_resource($lock)) {

                flock($lock, LOCK_UN);

                fclose($lock);
            }
        }
    }

    private function readCursor(
        string $path,
        string $day
    ): int {

        if (!file_exists($path)) {
            return 0;
        }

        try {

            $raw = file_get_contents($path);

            if ($raw === false) {

                throw new RuntimeException(
                    'RECOVERY_CURSOR_UNREADABLE'
                );
            }

            $state = json_decode(
                $raw,
                true,
                16,
                JSON_THROW_ON_ERROR
            );

            if (
                !is_array($state)
                || ($state['version'] ?? null) !== 1
                || !is_string(
                    $state['day'] ?? null
                )
                || !is_int(
                    $state['cursor'] ?? null
                )
                || $state['cursor'] < 0
            ) {

                throw new RuntimeException(
                    'INVALID_RECOVERY_CURSOR'
                );
            }

            if ($state['day'] !== $day) {
                return 0;
            }

            return $state['cursor'];

        } catch (Throwable $error) {

            // Repeating a sweep is safe because
            // notice creation is idempotent.

            try {

                Log::warning(
                    'Attendance recovery cursor reset',
                    [
                        'exception_type' =>
                            get_class($error),
                    ]
                );

            } catch (Throwable) {
            }

            return 0;
        }
    }

    private function writeCursor(
        string $path,
        array $state
    ): void {

        $temporary = tempnam(
            dirname($path),
            'cursor-'
        );

        if ($temporary === false) {

            throw new RuntimeException(
                'RECOVERY_CURSOR_TEMPFILE_FAILED'
            );
        }

        try {

            $encoded = json_encode(
                $state,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
            );

            @chmod($temporary, 0600);

            $written = file_put_contents(
                $temporary,
                $encoded
            );

            if (
                $written === false
                || $written !== strlen($encoded)
            ) {

                throw new RuntimeException(
                    'RECOVERY_CURSOR_WRITE_FAILED'
                );
            }

            if (!rename($temporary, $path)) {

                throw new RuntimeException(
                    'RECOVERY_CURSOR_RENAME_FAILED'
                );
            }

        } finally {

            if (file_exists($temporary)) {

                @unlink($temporary);
            }
        }
    }
}
