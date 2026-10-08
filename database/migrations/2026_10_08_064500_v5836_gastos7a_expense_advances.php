<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expense_advance_settings')) {
            Schema::create('expense_advance_settings', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id')
                    ->constrained('companies')
                    ->cascadeOnDelete();

                /*
                 * off   = no validar anticipos abiertos
                 * warn  = advertir, pero permitir
                 * block = impedir un nuevo anticipo
                 */
                $table->string('pending_rule', 20)->default('warn');

                $table->unsignedInteger('due_days')->default(5);

                $table->boolean('is_active')->default(true);

                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->unique(
                    'company_id',
                    'expense_advance_settings_company_unique'
                );
            });
        }

        if (! Schema::hasTable('expense_advances')) {
            Schema::create('expense_advances', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id')
                    ->constrained('companies')
                    ->cascadeOnDelete();

                $table->foreignId('petty_cash_fund_id')
                    ->constrained('petty_cash_funds')
                    ->restrictOnDelete();

                $table->foreignId('employee_id')
                    ->constrained('employees')
                    ->restrictOnDelete();

                $table->string('number', 60)->nullable();

                /*
                 * 4A1:
                 * draft
                 *
                 * Preparados para fases siguientes:
                 * pending_approval
                 * approved
                 * pending_reconciliation
                 * pending_return
                 * pending_reimbursement
                 * closed
                 * rejected
                 * cancelled
                 */
                $table->string('status', 40)->default('draft');

                $table->date('request_date');
                $table->date('due_date')->nullable();

                $table->decimal('amount', 18, 6)->default(0);
                $table->string('currency_code', 3)->default('MXN');

                $table->text('purpose');
                $table->text('notes')->nullable();

                /*
                 * Estos vínculos quedan preparados desde 4A1.
                 * Se utilizarán en 4A2 / 4B / 4C.
                 */
                $table->foreignId('treasury_cash_transfer_request_id')
                    ->nullable()
                    ->constrained('treasury_cash_transfer_requests')
                    ->nullOnDelete();

                $table->foreignId('treasury_movement_id')
                    ->nullable()
                    ->constrained('treasury_movements')
                    ->nullOnDelete();

                $table->foreignId('petty_cash_fund_movement_id')
                    ->nullable()
                    ->constrained('petty_cash_fund_movements')
                    ->nullOnDelete();

                $table->foreignId('approval_request_id')
                    ->nullable()
                    ->constrained('approval_requests')
                    ->nullOnDelete();

                $table->string('approval_status', 40)->nullable();

                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('submitted_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('approved_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('delivered_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();

                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->unique(
                    ['company_id', 'number'],
                    'expense_advances_company_number_unique'
                );

                $table->index(
                    ['company_id', 'status', 'request_date'],
                    'expense_advances_company_status_date_idx'
                );

                $table->index(
                    ['company_id', 'employee_id', 'status'],
                    'expense_advances_company_employee_status_idx'
                );

                $table->index(
                    ['petty_cash_fund_id', 'status'],
                    'expense_advances_fund_status_idx'
                );

                $table->index(
                    'due_date',
                    'expense_advances_due_date_idx'
                );
            });
        }

        /*
         * Configuración inicial únicamente para CEDIS GRUPO L7.
         * insertOrIgnore hace la migración repetible de forma segura.
         */
        $companyId = DB::table('companies')
            ->where('name', 'CEDIS GRUPO L7')
            ->value('id');

        if ($companyId) {
            DB::table('expense_advance_settings')
                ->insertOrIgnore([
                    'company_id' => $companyId,
                    'pending_rule' => 'warn',
                    'due_days' => 5,
                    'is_active' => true,
                    'metadata' => json_encode([
                        'seed' => 'V5.83.6-ADVANCES4A1',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_advances');
        Schema::dropIfExists('expense_advance_settings');
    }
};
