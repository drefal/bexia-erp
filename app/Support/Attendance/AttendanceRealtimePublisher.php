<?php

namespace App\Support\Attendance;

use App\Models\AttendanceTerminal;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;
use Throwable;

final class AttendanceRealtimePublisher
{
    public static function channel(
        int $companyId,
        int $branchId
    ): string {
        if ($companyId <= 0 || $branchId <= 0) {
            throw new \InvalidArgumentException(
                'Invalid attendance scope.'
            );
        }

        return sprintf(
            'private-attendance.security.%d.%d',
            $companyId,
            $branchId
        );
    }

    public function publishEntry(
        AttendanceTerminal $terminal
    ): void {
        $this->publishSecurityRefresh(
            (int) $terminal->company_id,
            (int) $terminal->branch_id
        );
    }

    public function publishSecurityRefresh(
        int $companyId,
        int $branchId
    ): void {
        $this->publishRefresh(
            self::channel($companyId, $branchId),
            'attendance.entry.created'
        );
    }

    public function publishUserRefresh(
        int $companyId,
        int $userId
    ): void {
        $this->publishRefresh(
            AttendanceAccessUserChannel::channel(
                $companyId,
                $userId
            ),
            'attendance.approvals.changed'
        );
    }

    private function publishRefresh(
        string $channel,
        string $event
    ): void {
        if (! config('attendance_realtime.enabled')) {
            return;
        }

        try {
            $key = (string) config(
                'attendance_realtime.key'
            );

            $secret = (string) config(
                'attendance_realtime.secret'
            );

            $appId = (string) config(
                'attendance_realtime.app_id'
            );

            if ($key === '' || $secret === '' || $appId === '') {
                throw new \RuntimeException(
                    'Realtime credentials missing.'
                );
            }

            $scheme = (string) config(
                'attendance_realtime.scheme'
            );

            $client = new Client([
                'connect_timeout' => (float) config(
                    'attendance_realtime.connect_timeout',
                    0.5
                ),
                'timeout' => (float) config(
                    'attendance_realtime.timeout',
                    1.0
                ),
                'allow_redirects' => false,
            ]);

            $pusher = new Pusher(
                $key,
                $secret,
                $appId,
                [
                    'host' => (string) config(
                        'attendance_realtime.host'
                    ),
                    'port' => (int) config(
                        'attendance_realtime.port'
                    ),
                    'scheme' => $scheme,
                    'useTLS' => $scheme === 'https',
                ],
                $client
            );

            $pusher->trigger(
                $channel,
                $event,
                ['refresh' => true]
            );
        } catch (Throwable $e) {
            // La asistencia ya fue guardada.
            // Un fallo de Reverb no debe convertirla
            // en un registro fallido.
            try {
                Log::warning(
                    'Attendance realtime publish failed',
                    ['exception_type' => get_class($e)]
                );
            } catch (Throwable) {
                // No afectar la respuesta del kiosco.
            }
        }
    }
}
