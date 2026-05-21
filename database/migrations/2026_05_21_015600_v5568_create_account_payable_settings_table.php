<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_payable_settings')) {
            Schema::create('account_payable_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();

                $table->foreignId('default_treasury_account_id')->nullable()->constrained('treasury_accounts')->nullOnDelete();
                $table->foreignId('default_payment_form_id')->nullable()->constrained('payment_forms')->nullOnDelete();

                $table->unsignedSmallInteger('default_due_days')->default(30);
                $table->decimal('rounding_tolerance', 12, 4)->default(0.0200);
                $table->boolean('allow_overpayment')->default(false);
                $table->boolean('show_logo_on_pdf')->default(true);

                $table->json('metadata')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('account_payable_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('account_payable_settings', 'default_treasury_account_id')) {
                $table->foreignId('default_treasury_account_id')->nullable()->after('company_id')->constrained('treasury_accounts')->nullOnDelete();
            }

            if (! Schema::hasColumn('account_payable_settings', 'default_payment_form_id')) {
                $table->foreignId('default_payment_form_id')->nullable()->after('default_treasury_account_id')->constrained('payment_forms')->nullOnDelete();
            }

            if (! Schema::hasColumn('account_payable_settings', 'default_due_days')) {
                $table->unsignedSmallInteger('default_due_days')->default(30)->after('default_payment_form_id');
            }

            if (! Schema::hasColumn('account_payable_settings', 'rounding_tolerance')) {
                $table->decimal('rounding_tolerance', 12, 4)->default(0.0200)->after('default_due_days');
            }

            if (! Schema::hasColumn('account_payable_settings', 'allow_overpayment')) {
                $table->boolean('allow_overpayment')->default(false)->after('rounding_tolerance');
            }

            if (! Schema::hasColumn('account_payable_settings', 'show_logo_on_pdf')) {
                $table->boolean('show_logo_on_pdf')->default(true)->after('allow_overpayment');
            }

            if (! Schema::hasColumn('account_payable_settings', 'metadata')) {
                $table->json('metadata')->nullable()->after('show_logo_on_pdf');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_payable_settings');
    }
};
