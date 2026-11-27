<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Models\EmployeeAttendance;
use App\Models\AttendanceTerminal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AttendanceAccessClockIntegration
{
    public static function recordSafely(
        EmployeeAttendance $attendance,
        AttendanceTerminal $terminal
    ): ?array {

        if (DB::transactionLevel() < 1) {

            self::warn(
                $attendance,
                $terminal,
                'outer_transaction_required'
            );

            return null;
        }

        $connection = DB::connection();

        $outerLevel = $connection->transactionLevel();

        // Explicit transaction methods avoid Laravel's
        // nested concurrency exception handler.
        $connection->beginTransaction();

        try {

            $result = AttendanceAccessDecisionService::record(
                $attendance,
                $terminal
            );

            if (
                $connection->transactionLevel()
                    !== $outerLevel + 1
            ) {
                throw new \RuntimeException(
                    'INVALID_ACCESS_TRANSACTION_LEVEL'
                );
            }

            $connection->commit();

            return $result;

        } catch (Throwable $e) {

            // Never swallow an error if the savepoint
            // cannot be restored.
            if (
                $connection->transactionLevel()
                    <= $outerLevel
            ) {
                throw $e;
            }

            $connection->rollBack($outerLevel);

            if (
                $connection->transactionLevel()
                    !== $outerLevel
                || !$connection->getPdo()->inTransaction()
            ) {
                throw new \RuntimeException(
                    'ACCESS_SAVEPOINT_RECOVERY_FAILED',
                    0,
                    $e
                );
            }

            // Prove that PostgreSQL is no longer in
            // SQLSTATE 25P02 before continuing.
            $connection->selectOne('SELECT 1 AS healthy');

            self::warn(
                $attendance,
                $terminal,
                get_class($e)
            );

            // Serialization failures and deadlocks should
            // retry the entire attendance transaction.
            if (
                self::requiresOuterRetry($e)
            ) {
                throw $e;
            }

            // An ordinary access evaluation error must not
            // discard an otherwise valid attendance.
            return null;
        }
    }

    private static function requiresOuterRetry(
        Throwable $error
    ): bool {
        do {

            if ($error instanceof \PDOException) {

                $state = (string) (
                    $error->errorInfo[0]
                    ?? $error->getCode()
                );

                if (
                    in_array(
                        $state,
                        ['40P01', '40001'],
                        true
                    )
                ) {
                    return true;
                }
            }

            if (
                $error instanceof
                    \Illuminate\Database\DeadlockException
            ) {
                return true;
            }

            $error = $error->getPrevious();

        } while ($error !== null);

        return false;
    }

    private static function warn(
        EmployeeAttendance $attendance,
        AttendanceTerminal $terminal,
        string $error
    ): void {

        try {

            Log::warning(
                'Attendance access evaluation unavailable',
                [
                    'company_id' =>
                        (int) $attendance->company_id,
                    'attendance_id' =>
                        (int) $attendance->id,
                    'terminal_id' =>
                        (int) $terminal->id,
                    'error_type' => $error,
                ]
            );

        } catch (Throwable) {

            // Logging failure must not affect attendance.
        }
    }
}
