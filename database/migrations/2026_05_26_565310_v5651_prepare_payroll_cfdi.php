<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function addColumnIfMissing(string $table, string $column, callable $definition): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($definition): void {
            $definition($tableBlueprint);
        });
    }

    public function up(): void
    {
        $this->addColumnIfMissing('employees', 'fiscal_name', fn (Blueprint $table) => $table->string('fiscal_name')->nullable()->after('rfc'));
        $this->addColumnIfMissing('employees', 'fiscal_postal_code', fn (Blueprint $table) => $table->string('fiscal_postal_code', 10)->nullable()->after('fiscal_name'));
        $this->addColumnIfMissing('employees', 'sat_tax_regime_code', fn (Blueprint $table) => $table->string('sat_tax_regime_code', 10)->nullable()->after('fiscal_postal_code'));
        $this->addColumnIfMissing('employees', 'social_security_number', fn (Blueprint $table) => $table->string('social_security_number', 30)->nullable()->after('sat_tax_regime_code'));

        $this->addColumnIfMissing('employee_contracts', 'sat_contract_type_code', fn (Blueprint $table) => $table->string('sat_contract_type_code', 10)->nullable()->after('contract_type'));
        $this->addColumnIfMissing('employee_contracts', 'sat_workday_type_code', fn (Blueprint $table) => $table->string('sat_workday_type_code', 10)->nullable()->after('sat_contract_type_code'));
        $this->addColumnIfMissing('employee_contracts', 'sat_regime_type_code', fn (Blueprint $table) => $table->string('sat_regime_type_code', 10)->nullable()->after('sat_workday_type_code'));
        $this->addColumnIfMissing('employee_contracts', 'sat_risk_position_code', fn (Blueprint $table) => $table->string('sat_risk_position_code', 10)->nullable()->after('sat_regime_type_code'));
        $this->addColumnIfMissing('employee_contracts', 'daily_salary', fn (Blueprint $table) => $table->decimal('daily_salary', 14, 4)->nullable()->after('base_salary'));
        $this->addColumnIfMissing('employee_contracts', 'integrated_daily_salary', fn (Blueprint $table) => $table->decimal('integrated_daily_salary', 14, 4)->nullable()->after('daily_salary'));
        $this->addColumnIfMissing('employee_contracts', 'is_unionized', fn (Blueprint $table) => $table->boolean('is_unionized')->nullable()->after('integrated_daily_salary'));

        $this->addColumnIfMissing('payroll_concepts', 'is_taxable', fn (Blueprint $table) => $table->boolean('is_taxable')->nullable()->after('sat_key'));
        $this->addColumnIfMissing('payroll_concepts', 'taxable_amount_default', fn (Blueprint $table) => $table->decimal('taxable_amount_default', 14, 4)->nullable()->after('is_taxable'));
        $this->addColumnIfMissing('payroll_concepts', 'exempt_amount_default', fn (Blueprint $table) => $table->decimal('exempt_amount_default', 14, 4)->nullable()->after('taxable_amount_default'));

        $this->addColumnIfMissing('payroll_run_line_concepts', 'sat_key', fn (Blueprint $table) => $table->string('sat_key', 20)->nullable()->after('unit'));
        $this->addColumnIfMissing('payroll_run_line_concepts', 'taxable_amount', fn (Blueprint $table) => $table->decimal('taxable_amount', 14, 4)->nullable()->after('amount'));
        $this->addColumnIfMissing('payroll_run_line_concepts', 'exempt_amount', fn (Blueprint $table) => $table->decimal('exempt_amount', 14, 4)->nullable()->after('taxable_amount'));

        $this->addColumnIfMissing('payroll_runs', 'payroll_cfdi_status', fn (Blueprint $table) => $table->string('payroll_cfdi_status', 40)->nullable()->after('lock_reason'));
        $this->addColumnIfMissing('payroll_runs', 'payroll_cfdi_validated_at', fn (Blueprint $table) => $table->timestamp('payroll_cfdi_validated_at')->nullable()->after('payroll_cfdi_status'));
        $this->addColumnIfMissing('payroll_runs', 'payroll_cfdi_ready_lines_count', fn (Blueprint $table) => $table->unsignedInteger('payroll_cfdi_ready_lines_count')->default(0)->after('payroll_cfdi_validated_at'));
        $this->addColumnIfMissing('payroll_runs', 'payroll_cfdi_error_lines_count', fn (Blueprint $table) => $table->unsignedInteger('payroll_cfdi_error_lines_count')->default(0)->after('payroll_cfdi_ready_lines_count'));
        $this->addColumnIfMissing('payroll_runs', 'payroll_cfdi_validation_errors', fn (Blueprint $table) => $table->json('payroll_cfdi_validation_errors')->nullable()->after('payroll_cfdi_error_lines_count'));

        $this->addColumnIfMissing('payroll_run_lines', 'payroll_cfdi_status', fn (Blueprint $table) => $table->string('payroll_cfdi_status', 40)->nullable()->after('notes'));
        $this->addColumnIfMissing('payroll_run_lines', 'payroll_cfdi_validation_errors', fn (Blueprint $table) => $table->json('payroll_cfdi_validation_errors')->nullable()->after('payroll_cfdi_status'));

        if (! Schema::hasTable('payroll_cfdi_receipts')) {
            Schema::create('payroll_cfdi_receipts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
                $table->foreignId('payroll_run_line_id')->constrained('payroll_run_lines')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

                $table->string('status', 40)->default('draft');
                $table->string('cfdi_version', 10)->default('4.0');
                $table->string('payroll_complement_version', 10)->default('1.2');

                $table->string('series', 25)->nullable();
                $table->string('folio', 40)->nullable();
                $table->string('uuid', 80)->nullable();

                $table->string('pac_provider', 30)->nullable();
                $table->boolean('pac_test_env')->nullable();
                $table->string('pac_request_id')->nullable();
                $table->text('pac_error_message')->nullable();

                $table->string('xml_path')->nullable();
                $table->string('pdf_path')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->timestamp('stamped_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();

                $table->json('issuer_snapshot')->nullable();
                $table->json('employee_snapshot')->nullable();
                $table->json('contract_snapshot')->nullable();
                $table->json('totals_snapshot')->nullable();
                $table->json('validation_errors')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->unique('payroll_run_line_id', 'payroll_cfdi_receipts_line_unique');
                $table->index(['company_id', 'status']);
                $table->index(['payroll_run_id', 'status']);
                $table->index('uuid');
            });
        }

        if (! Schema::hasTable('payroll_cfdi_audits')) {
            Schema::create('payroll_cfdi_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('payroll_cfdi_receipt_id')->nullable()->constrained('payroll_cfdi_receipts')->nullOnDelete();
                $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
                $table->foreignId('payroll_run_line_id')->nullable()->constrained('payroll_run_lines')->nullOnDelete();
                $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->string('action', 80);
                $table->string('status', 40);
                $table->string('pac_provider', 30)->nullable();
                $table->boolean('pac_test_env')->nullable();
                $table->string('request_id')->nullable();
                $table->json('request_meta')->nullable();
                $table->json('response_meta')->nullable();
                $table->text('message')->nullable();
                $table->string('ip_address', 80)->nullable();

                $table->timestamps();

                $table->index(['company_id', 'action']);
                $table->index(['payroll_run_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_cfdi_audits');
        Schema::dropIfExists('payroll_cfdi_receipts');

        foreach ([
            'payroll_run_lines' => [
                'payroll_cfdi_validation_errors',
                'payroll_cfdi_status',
            ],
            'payroll_runs' => [
                'payroll_cfdi_validation_errors',
                'payroll_cfdi_error_lines_count',
                'payroll_cfdi_ready_lines_count',
                'payroll_cfdi_validated_at',
                'payroll_cfdi_status',
            ],
            'payroll_run_line_concepts' => [
                'exempt_amount',
                'taxable_amount',
                'sat_key',
            ],
            'payroll_concepts' => [
                'exempt_amount_default',
                'taxable_amount_default',
                'is_taxable',
            ],
            'employee_contracts' => [
                'is_unionized',
                'integrated_daily_salary',
                'daily_salary',
                'sat_risk_position_code',
                'sat_regime_type_code',
                'sat_workday_type_code',
                'sat_contract_type_code',
            ],
            'employees' => [
                'social_security_number',
                'sat_tax_regime_code',
                'fiscal_postal_code',
                'fiscal_name',
            ],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $tableBlueprint) => $tableBlueprint->dropColumn($column));
                }
            }
        }
    }
};
