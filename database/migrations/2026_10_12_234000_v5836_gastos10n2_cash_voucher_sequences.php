<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_voucher_counters', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->foreignId('petty_cash_fund_id')
                ->constrained('petty_cash_funds')
                ->restrictOnDelete();

            $table->string('voucher_type', 12);

            $table->unsignedBigInteger('last_number')
                ->default(0);

            $table->timestamps();

            $table->unique(
                ['petty_cash_fund_id', 'voucher_type'],
                'pcvc_fund_type_unique'
            );
        });

        Schema::create('petty_cash_vouchers', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->foreignId('petty_cash_fund_id')
                ->constrained('petty_cash_funds')
                ->restrictOnDelete();

            $table->string('voucher_type', 12);
            $table->unsignedBigInteger('sequence_number');

            $table->string('folio', 80);

            /*
             * Documentos de origen:
             *
             * ANT -> expense_advance
             * COM -> expense_report
             * DEV -> expense_advance_return
             * REM -> expense_advance_reimbursement
             *
             * source_id identifica el documento que origina
             * el vale. No se modifican los folios originales.
             */
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id');

            $table->timestamp('issued_at');

            $table->timestamps();

            $table->unique(
                [
                    'petty_cash_fund_id',
                    'voucher_type',
                    'sequence_number',
                ],
                'pcv_fund_type_sequence_unique'
            );

            $table->unique(
                ['source_type', 'source_id'],
                'pcv_source_unique'
            );

            $table->unique(
                'folio',
                'pcv_folio_unique'
            );

            $table->index(
                ['company_id', 'issued_at'],
                'pcv_company_issued_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_vouchers');
        Schema::dropIfExists('petty_cash_voucher_counters');
    }
};
