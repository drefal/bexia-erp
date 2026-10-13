<?php

namespace App\Support\Expenses;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PettyCashVoucherService
{
    public const TYPE_ADVANCE = 'ANT';
    public const TYPE_REPORT = 'COM';
    public const TYPE_RETURN = 'DEV';
    public const TYPE_REIMBURSEMENT = 'REM';

    private const SOURCE_TYPES = [
        self::TYPE_ADVANCE => 'expense_advance',
        self::TYPE_REPORT => 'expense_report',
        self::TYPE_RETURN => 'expense_advance_return',
        self::TYPE_REIMBURSEMENT =>
            'expense_advance_reimbursement',
    ];

    /**
     * Emite o recupera el folio permanente de una operación.
     *
     * REQUIERE una transacción exterior activa y una operación
     * de negocio ya validada dentro de esa transacción.
     *
     * No crea movimientos financieros ni modifica documentos.
     */
    public function issue(
        int $companyId,
        int $fundId,
        string $type,
        int $sourceId,
        Carbon|string|null $issuedAt = null
    ): string {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException(
                'La emisión del vale requiere una transacción activa.'
            );
        }

        $sourceType = self::SOURCE_TYPES[$type] ?? null;

        if (
            $sourceType === null
            || $companyId <= 0
            || $fundId <= 0
            || $sourceId <= 0
        ) {
            throw new RuntimeException(
                'Datos inválidos para emitir el vale de caja.'
            );
        }

        /*
         * El mismo orden de bloqueo debe utilizarse en todos
         * los procesos que emitan vales: caja, contador, folio.
         */
        $fund = DB::table('petty_cash_funds')
            ->where('id', $fundId)
            ->lockForUpdate()
            ->first();

        if (
            ! $fund
            || (int) $fund->company_id !== $companyId
        ) {
            throw new RuntimeException(
                'La caja chica no pertenece a la empresa.'
            );
        }

        /*
         * Idempotencia por documento y tipo de operación.
         * Ejemplo: un anticipo puede tener ANT, DEV y REM,
         * cada uno asociado a un source_type distinto.
         */
        $existing = DB::table('petty_cash_vouchers')
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if ($existing) {
            if (
                (int) $existing->company_id !== $companyId
                || (int) $existing->petty_cash_fund_id
                    !== $fundId
                || (string) $existing->voucher_type !== $type
            ) {
                throw new RuntimeException(
                    'El documento ya tiene un folio incompatible.'
                );
            }

            return (string) $existing->folio;
        }

        $counter = DB::table('petty_cash_voucher_counters')
            ->where('petty_cash_fund_id', $fundId)
            ->where('voucher_type', $type)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            /*
             * El bloqueo previo de la caja serializa
             * la creación inicial del contador.
             */
            DB::table('petty_cash_voucher_counters')
                ->insert([
                    'company_id' => $companyId,
                    'petty_cash_fund_id' => $fundId,
                    'voucher_type' => $type,
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $counter = DB::table(
                'petty_cash_voucher_counters'
            )
                ->where('petty_cash_fund_id', $fundId)
                ->where('voucher_type', $type)
                ->lockForUpdate()
                ->first();
        }

        if (
            ! $counter
            || (int) $counter->company_id !== $companyId
        ) {
            throw new RuntimeException(
                'Contador de vales inconsistente.'
            );
        }

        $next = (int) $counter->last_number + 1;

        if ($next <= 0) {
            throw new RuntimeException(
                'Consecutivo de vale fuera de rango.'
            );
        }

        $folio = sprintf(
            '%s-CC%06d-%05d',
            $type,
            $fundId,
            $next
        );

        $date = $issuedAt === null
            ? now()
            : Carbon::parse($issuedAt);

        DB::table('petty_cash_voucher_counters')
            ->where('id', $counter->id)
            ->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

        DB::table('petty_cash_vouchers')->insert([
            'company_id' => $companyId,
            'petty_cash_fund_id' => $fundId,
            'voucher_type' => $type,
            'sequence_number' => $next,
            'folio' => $folio,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'issued_at' => $date,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $folio;
    }

    /**
     * Consulta sin asignar ni consumir consecutivos.
     */
    public function existing(
        string $type,
        int $sourceId
    ): ?string {
        $sourceType = self::SOURCE_TYPES[$type] ?? null;

        if ($sourceType === null || $sourceId <= 0) {
            return null;
        }

        return DB::table('petty_cash_vouchers')
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->value('folio');
    }
}
