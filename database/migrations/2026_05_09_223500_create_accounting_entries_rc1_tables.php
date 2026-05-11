<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_entries')) {
            Schema::create('accounting_entries', function (Blueprint $table) {
                $table->id();

                $table->foreignId('company_id')
                    ->constrained('companies')
                    ->cascadeOnDelete();

                $table->foreignId('journal_id')
                    ->nullable()
                    ->constrained('accounting_journals')
                    ->nullOnDelete();

                $table->string('entry_number', 80);
                $table->date('entry_date');
                $table->string('status', 30)->default('draft');

                $table->string('source_type', 120)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('source_label')->nullable();

                $table->string('currency', 10)->default('MXN');
                $table->decimal('total_debit', 18, 6)->default(0);
                $table->decimal('total_credit', 18, 6)->default(0);

                $table->timestamp('posted_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();

                $table->foreignId('cancelled_by_entry_id')
                    ->nullable()
                    ->constrained('accounting_entries')
                    ->nullOnDelete();

                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->unsignedBigInteger('posted_by_user_id')->nullable();

                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'entry_number'], 'acct_entries_company_number_unique');
                $table->index(['company_id', 'status', 'entry_date'], 'acct_entries_company_status_date_idx');
                $table->index(['source_type', 'source_id'], 'acct_entries_source_idx');
            });
        }

        if (! Schema::hasTable('accounting_entry_lines')) {
            Schema::create('accounting_entry_lines', function (Blueprint $table) {
                $table->id();

                $table->foreignId('company_id')
                    ->constrained('companies')
                    ->cascadeOnDelete();

                $table->foreignId('accounting_entry_id')
                    ->constrained('accounting_entries')
                    ->cascadeOnDelete();

                $table->foreignId('account_id')
                    ->constrained('accounting_accounts')
                    ->restrictOnDelete();

                $table->unsignedInteger('line_number')->default(1);
                $table->string('label')->nullable();

                $table->unsignedBigInteger('partner_contact_id')->nullable();

                $table->decimal('debit', 18, 6)->default(0);
                $table->decimal('credit', 18, 6)->default(0);
                $table->string('currency', 10)->default('MXN');

                $table->string('source_type', 120)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();

                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'account_id'], 'acct_entry_lines_company_account_idx');
                $table->index(['accounting_entry_id', 'line_number'], 'acct_entry_lines_entry_line_idx');
                $table->index(['source_type', 'source_id'], 'acct_entry_lines_source_idx');
            });
        }

        if (! Schema::hasTable('accounting_mappings')) {
            Schema::create('accounting_mappings', function (Blueprint $table) {
                $table->id();

                $table->foreignId('company_id')
                    ->constrained('companies')
                    ->cascadeOnDelete();

                $table->string('module', 80);
                $table->string('operation_type', 120);
                $table->string('mapping_key', 120);

                $table->foreignId('account_id')
                    ->constrained('accounting_accounts')
                    ->restrictOnDelete();

                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('priority')->default(100);

                $table->json('options')->nullable();
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'module', 'operation_type', 'mapping_key'], 'acct_mappings_unique');
                $table->index(['company_id', 'module', 'operation_type'], 'acct_mappings_module_operation_idx');
                $table->index(['company_id', 'is_active'], 'acct_mappings_active_idx');
            });
        }

        if (! Schema::hasTable('accounting_posting_audits')) {
            Schema::create('accounting_posting_audits', function (Blueprint $table) {
                $table->id();

                $table->foreignId('company_id')
                    ->constrained('companies')
                    ->cascadeOnDelete();

                $table->string('source_type', 120)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();

                $table->foreignId('accounting_entry_id')
                    ->nullable()
                    ->constrained('accounting_entries')
                    ->nullOnDelete();

                $table->string('event', 80);
                $table->string('status', 40)->default('info');
                $table->text('message')->nullable();

                $table->json('request_meta')->nullable();
                $table->json('response_meta')->nullable();

                $table->unsignedBigInteger('created_by_user_id')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'source_type', 'source_id'], 'acct_post_audits_source_idx');
                $table->index(['company_id', 'event', 'status'], 'acct_post_audits_event_status_idx');
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('invoices', 'accounting_status')) {
                    $table->string('accounting_status', 30)->default('not_posted')->index();
                }

                if (! Schema::hasColumn('invoices', 'accounting_entry_id')) {
                    $table->foreignId('accounting_entry_id')
                        ->nullable()
                        ->constrained('accounting_entries')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('invoices', 'accounting_posted_at')) {
                    $table->timestamp('accounting_posted_at')->nullable();
                }

                if (! Schema::hasColumn('invoices', 'accounting_error_message')) {
                    $table->text('accounting_error_message')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasColumn('invoices', 'accounting_entry_id')) {
                    try {
                        $table->dropConstrainedForeignId('accounting_entry_id');
                    } catch (Throwable $e) {
                        try {
                            $table->dropColumn('accounting_entry_id');
                        } catch (Throwable $ignored) {
                        }
                    }
                }

                foreach (['accounting_status', 'accounting_posted_at', 'accounting_error_message'] as $column) {
                    if (Schema::hasColumn('invoices', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('accounting_posting_audits');
        Schema::dropIfExists('accounting_mappings');
        Schema::dropIfExists('accounting_entry_lines');
        Schema::dropIfExists('accounting_entries');
    }
};
