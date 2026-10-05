<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();

            $table->boolean('requires_receipt')->default(true);
            $table->boolean('allows_without_receipt')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['company_id', 'code'],
                'expense_categories_company_code_unique'
            );

            $table->index(
                ['company_id', 'is_active'],
                'expense_categories_company_active_idx'
            );
        });

        Schema::create('petty_cash_funds', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();

            /*
             * Cuenta real de Tesoreria que representa el efectivo
             * bajo responsabilidad del empleado.
             */
            $table->foreignId('treasury_account_id')
                ->constrained('treasury_accounts')
                ->restrictOnDelete();

            /*
             * Cuenta/caja desde la que normalmente se fondea.
             * Ejemplo: Caja administracion.
             */
            $table->foreignId('funding_treasury_account_id')
                ->nullable()
                ->constrained('treasury_accounts')
                ->nullOnDelete();

            $table->string('number', 50)->nullable();
            $table->string('name', 150);

            $table->decimal('authorized_amount', 18, 6)->default(0);

            /*
             * Este campo es operativo.
             * El saldo financiero real debe coincidir con
             * treasury_accounts.current_balance.
             */
            $table->decimal('operational_balance', 18, 6)->default(0);

            $table->string('currency_code', 3)->default('MXN');

            $table->string('status', 30)->default('active');
            $table->boolean('is_active')->default(true);

            $table->date('assigned_at')->nullable();
            $table->date('closed_at')->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('closed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                'treasury_account_id',
                'petty_cash_funds_treasury_account_unique'
            );

            $table->index(
                ['company_id', 'employee_id', 'is_active'],
                'petty_cash_funds_company_employee_active_idx'
            );
        });

        Schema::create('expense_reports', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            /*
             * Empleado responsable de la comprobacion o
             * beneficiario del reembolso.
             */
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();

            /*
             * Null para reembolsos.
             */
            $table->foreignId('petty_cash_fund_id')
                ->nullable()
                ->constrained('petty_cash_funds')
                ->restrictOnDelete();

            $table->string('number', 50)->nullable();

            /*
             * petty_cash    = comprobacion de caja chica
             * reimbursement = empleado pago con recursos propios
             */
            $table->string('type', 30);

            /*
             * draft
             * submitted
             * pending_approval
             * approved
             * rejected
             * pending_payment
             * paid
             * closed
             * cancelled
             */
            $table->string('status', 30)->default('draft');

            $table->date('report_date');

            $table->decimal('subtotal', 18, 6)->default(0);
            $table->decimal('tax_amount', 18, 6)->default(0);
            $table->decimal('total_amount', 18, 6)->default(0);

            $table->string('currency_code', 3)->default('MXN');

            $table->text('purpose')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();

            /*
             * Motor general de aprobaciones.
             */
            $table->foreignId('approval_request_id')
                ->nullable()
                ->constrained('approval_requests')
                ->nullOnDelete();

            $table->string('approval_status', 30)->nullable();

            /*
             * Para reembolso pagado.
             */
            $table->foreignId('payment_treasury_account_id')
                ->nullable()
                ->constrained('treasury_accounts')
                ->nullOnDelete();

            $table->foreignId('treasury_movement_id')
                ->nullable()
                ->constrained('treasury_movements')
                ->nullOnDelete();

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

            $table->foreignId('rejected_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('paid_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approval_requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(
                ['company_id', 'type', 'status'],
                'expense_reports_company_type_status_idx'
            );

            $table->index(
                ['company_id', 'employee_id', 'report_date'],
                'expense_reports_company_employee_date_idx'
            );
        });

        Schema::create('expense_report_lines', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('expense_report_id')
                ->constrained('expense_reports')
                ->cascadeOnDelete();

            $table->foreignId('expense_category_id')
                ->nullable()
                ->constrained('expense_categories')
                ->nullOnDelete();

            /*
             * Puede ser distinto al responsable del reporte.
             * Esto conserva el requerimiento de saber quien
             * realizo realmente cada gasto.
             */
            $table->foreignId('spent_by_employee_id')
                ->constrained('employees')
                ->restrictOnDelete();

            $table->date('expense_date');

            $table->string('supplier_name', 255)->nullable();
            $table->string('supplier_rfc', 20)->nullable();

            $table->string('description', 500);

            $table->decimal('subtotal', 18, 6)->default(0);
            $table->decimal('tax_amount', 18, 6)->default(0);
            $table->decimal('total_amount', 18, 6);

            $table->string('currency_code', 3)->default('MXN');

            $table->string('payment_method', 50)->nullable();

            /*
             * Datos fiscales opcionales para ligar posteriormente
             * con CFDI recibido.
             */
            $table->uuid('cfdi_uuid')->nullable();
            $table->foreignId('sat_cfdi_document_id')
                ->nullable()
                ->constrained('sat_cfdi_documents')
                ->nullOnDelete();

            $table->boolean('has_receipt')->default(false);
            $table->boolean('requires_receipt')->default(true);

            $table->text('receipt_exception_reason')->nullable();
            $table->text('notes')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(
                ['expense_report_id', 'expense_date'],
                'expense_report_lines_report_date_idx'
            );

            $table->index(
                ['spent_by_employee_id', 'expense_date'],
                'expense_report_lines_employee_date_idx'
            );

            $table->index(
                'cfdi_uuid',
                'expense_report_lines_cfdi_uuid_idx'
            );
        });

        Schema::create('petty_cash_fund_movements', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('petty_cash_fund_id')
                ->constrained('petty_cash_funds')
                ->cascadeOnDelete();

            $table->foreignId('expense_report_id')
                ->nullable()
                ->constrained('expense_reports')
                ->nullOnDelete();

            /*
             * initial_funding
             * replenishment
             * expense
             * return
             * adjustment_in
             * adjustment_out
             * close_return
             */
            $table->string('type', 40);

            $table->date('movement_date');

            $table->decimal('amount', 18, 6);
            $table->string('currency_code', 3)->default('MXN');

            $table->decimal('balance_before', 18, 6)->default(0);
            $table->decimal('balance_after', 18, 6)->default(0);

            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();

            /*
             * Movimiento que realmente afecto Tesoreria,
             * cuando corresponda.
             */
            $table->foreignId('treasury_movement_id')
                ->nullable()
                ->constrained('treasury_movements')
                ->nullOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(
                ['petty_cash_fund_id', 'movement_date'],
                'petty_cash_fund_movements_fund_date_idx'
            );

            $table->index(
                ['company_id', 'type', 'movement_date'],
                'petty_cash_fund_movements_company_type_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_fund_movements');
        Schema::dropIfExists('expense_report_lines');
        Schema::dropIfExists('expense_reports');
        Schema::dropIfExists('petty_cash_funds');
        Schema::dropIfExists('expense_categories');
    }
};
