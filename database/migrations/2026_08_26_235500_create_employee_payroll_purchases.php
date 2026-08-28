<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_payroll_purchases', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id');
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('employee_id');

            $table->string('number', 40)->nullable();
            $table->date('purchase_date');
            $table->string('frequency', 20)->default('weekly');
            $table->unsignedSmallInteger('installments_count')->default(1);
            $table->date('first_deduction_date');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable();

            $table->foreignId('created_by_user_id')->nullable();
            $table->foreignId('updated_by_user_id')->nullable();

            $table->timestamps();

            $table->foreign('company_id', 'epp_company_fk')
                ->references('id')->on('companies')->cascadeOnDelete();

            $table->foreign('branch_id', 'epp_branch_fk')
                ->references('id')->on('branches')->nullOnDelete();

            $table->foreign('employee_id', 'epp_employee_fk')
                ->references('id')->on('employees')->cascadeOnDelete();

            $table->foreign('confirmed_by_user_id', 'epp_confirmed_user_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->foreign('cancelled_by_user_id', 'epp_cancelled_user_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->foreign('created_by_user_id', 'epp_created_user_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->foreign('updated_by_user_id', 'epp_updated_user_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->unique(
                ['company_id', 'number'],
                'employee_payroll_purchases_company_number_unique'
            );

            $table->index(
                ['company_id', 'employee_id', 'status'],
                'employee_payroll_purchases_employee_status_idx'
            );

            $table->index(
                ['company_id', 'purchase_date'],
                'employee_payroll_purchases_date_idx'
            );
        });

        Schema::table('employee_payroll_deductions', function (Blueprint $table): void {
            $table->foreignId('employee_payroll_purchase_id')
                ->nullable()
                ->after('employee_id');

            $table->foreign(
                'employee_payroll_purchase_id',
                'epd_purchase_fk'
            )
                ->references('id')
                ->on('employee_payroll_purchases')
                ->nullOnDelete();

            $table->index(
                ['company_id', 'employee_payroll_purchase_id'],
                'employee_payroll_deductions_purchase_idx'
            );
        });

        Schema::create('employee_payroll_purchase_lines', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id');
            $table->foreignId('employee_payroll_purchase_id');
            $table->foreignId('product_id')->nullable();

            $table->string('product_sku', 120)->nullable();
            $table->string('product_reference', 120)->nullable();
            $table->string('product_name', 255);
            $table->string('variant_name', 255)->nullable();

            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('unit_price_without_tax', 14, 4)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('unit_price_with_tax', 14, 4)->default(0);
            $table->decimal('line_subtotal', 14, 2)->default(0);
            $table->decimal('line_tax', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);

            $table->timestamps();

            $table->foreign('company_id', 'eppl_company_fk')
                ->references('id')->on('companies')->cascadeOnDelete();

            $table->foreign('employee_payroll_purchase_id', 'eppl_purchase_fk')
                ->references('id')->on('employee_payroll_purchases')->cascadeOnDelete();

            $table->foreign('product_id', 'eppl_product_fk')
                ->references('id')->on('products')->nullOnDelete();

            $table->index(
                ['company_id', 'product_id'],
                'employee_payroll_purchase_lines_product_idx'
            );
        });

        Schema::create('employee_payroll_purchase_installments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id');
            $table->foreignId('employee_payroll_purchase_id');
            $table->foreignId('employee_id');
            $table->foreignId('employee_payroll_deduction_id')->nullable();
            $table->foreignId('employee_payroll_deduction_application_id')->nullable();
            $table->foreignId('payroll_run_id')->nullable();

            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date');
            $table->decimal('scheduled_amount', 14, 2);
            $table->decimal('applied_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamp('applied_at')->nullable();

            $table->timestamps();

            $table->foreign('company_id', 'eppi_company_fk')
                ->references('id')->on('companies')->cascadeOnDelete();

            $table->foreign('employee_payroll_purchase_id', 'eppi_purchase_fk')
                ->references('id')->on('employee_payroll_purchases')->cascadeOnDelete();

            $table->foreign('employee_id', 'eppi_employee_fk')
                ->references('id')->on('employees')->cascadeOnDelete();

            $table->foreign('employee_payroll_deduction_id', 'eppi_deduction_fk')
                ->references('id')->on('employee_payroll_deductions')->nullOnDelete();

            $table->foreign(
                'employee_payroll_deduction_application_id',
                'eppi_application_fk'
            )
                ->references('id')
                ->on('employee_payroll_deduction_applications')
                ->nullOnDelete();

            $table->foreign('payroll_run_id', 'eppi_run_fk')
                ->references('id')->on('payroll_runs')->nullOnDelete();

            $table->unique(
                ['employee_payroll_purchase_id', 'installment_number'],
                'employee_payroll_purchase_installments_unique'
            );

            $table->index(
                ['company_id', 'due_date', 'status'],
                'employee_payroll_purchase_installments_due_idx'
            );

            $table->index(
                ['employee_id', 'due_date', 'status'],
                'employee_payroll_purchase_installments_employee_due_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payroll_purchase_installments');
        Schema::dropIfExists('employee_payroll_purchase_lines');

        if (Schema::hasColumn('employee_payroll_deductions', 'employee_payroll_purchase_id')) {
            Schema::table('employee_payroll_deductions', function (Blueprint $table): void {
                $table->dropForeign('epd_purchase_fk');
                $table->dropIndex('employee_payroll_deductions_purchase_idx');
                $table->dropColumn('employee_payroll_purchase_id');
            });
        }

        Schema::dropIfExists('employee_payroll_purchases');
    }
};
