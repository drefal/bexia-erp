<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use LogicException;
use App\Support\Reports\ReportClock;

final class ReportExport extends Model
{
    use HasUuids;

    public const ATTENDANCE_PDF = 'attendance_pdf';

    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const EXPIRED = 'expired';

    protected $table = 'report_exports';

    // Preserve the actual instant in PostgreSQL timestamptz.
    protected $dateFormat = 'Y-m-d H:i:s.uP';

    protected $keyType = 'string';

    public $incrementing = false;

    // Las solicitudes se asignan explicitamente
    // desde el servicio interno, nunca desde el request.

    protected $guarded = ['*'];

    protected $hidden = [
        'filters',
        'file_sha256',
        'queue_job_id',
        'error_code',
    ];

    protected $attributes = [
        'status' => self::PENDING,
        'row_count' => 0,
        'total_blocks' => 0,
        'completed_blocks' => 0,
        'progress' => 0,
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'user_id' => 'integer',
            'filters' => 'array',
            'row_count' => 'integer',
            'total_blocks' => 'integer',
            'completed_blocks' => 'integer',
            'progress' => 'integer',
            'file_size' => 'integer',
            'queue_job_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'heartbeat_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function scopeOwnedBy(
        Builder $query,
        int $companyId,
        int $userId
    ): Builder {

        return $query
            ->where('company_id', $companyId)
            ->where('user_id', $userId);
    }

    public function scopeActive(
        Builder $query
    ): Builder {

        return $query->whereIn('status', [
            self::PENDING,
            self::PROCESSING,
        ]);
    }

    public static function canTransition(
        string $from,
        string $to
    ): bool {

        return in_array(
            $to,
            match ($from) {

                self::PENDING => [
                    self::PROCESSING,
                    self::FAILED,
                ],

                self::PROCESSING => [
                    self::COMPLETED,
                    self::FAILED,
                ],

                self::COMPLETED => [
                    self::EXPIRED,
                ],

                default => [],
            },
            true
        );
    }

    public static function assertTransition(
        string $from,
        string $to
    ): void {

        if (!self::canTransition($from, $to)) {

            throw new LogicException(
                'Invalid report status transition.'
            );
        }
    }

    public static function progressFor(
        int $completed,
        int $total
    ): int {

        if (
            $completed < 0
            || $total < 1
            || $completed > $total
        ) {
            throw new LogicException(
                'Invalid report block progress.'
            );
        }

        // El 100% se reserva para la publicacion
        // completa y confirmada del PDF.

        return min(
            99,
            (int) floor(
                ($completed / $total) * 100
            )
        );
    }

    public function isDownloadable(): bool
    {
        return $this->status === self::COMPLETED
            && $this->progress === 100
            && $this->expires_at !== null
            && $this->expires_at->greaterThan(ReportClock::now())
            && $this->file_size > 0
            && is_string($this->file_sha256)
            && preg_match(
                '/\A[a-f0-9]{64}\z/',
                $this->file_sha256
            ) === 1;
    }
}
