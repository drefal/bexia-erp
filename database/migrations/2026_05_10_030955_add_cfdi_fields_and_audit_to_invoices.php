<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table): void {
                if (! Schema::hasColumn('invoices', 'cfdi_status')) {
                    $table->string('cfdi_status', 40)->nullable()->default('pending')->index();
                }

                if (! Schema::hasColumn('invoices', 'cfdi_uuid')) {
                    $table->string('cfdi_uuid', 80)->nullable()->index();
                }

                if (! Schema::hasColumn('invoices', 'cfdi_version')) {
                    $table->string('cfdi_version', 10)->nullable()->default('4.0');
                }

                if (! Schema::hasColumn('invoices', 'cfdi_type')) {
                    $table->string('cfdi_type', 10)->nullable()->default('I');
                }

                if (! Schema::hasColumn('invoices', 'cfdi_series')) {
                    $table->string('cfdi_series', 40)->nullable();
                }

                if (! Schema::hasColumn('invoices', 'cfdi_folio')) {
                    $table->string('cfdi_folio', 80)->nullable();
                }

                if (! Schema::hasColumn('invoices', 'pac_provider')) {
                    $table->string('pac_provider', 40)->nullable();
                }

                if (! Schema::hasColumn('invoices', 'pac_environment')) {
                    $table->string('pac_environment', 40)->nullable();
                }

                if (! Schema::hasColumn('invoices', 'pac_request_id')) {
                    $table->string('pac_request_id', 120)->nullable();
                }

                if (! Schema::hasColumn('invoices', 'pac_error_message')) {
                    $table->text('pac_error_message')->nullable();
                }

                if (! Schema::hasColumn('invoices', 'cfdi_xml_path')) {
                    $table->string('cfdi_xml_path')->nullable();
                }

                if (! Schema::hasColumn('invoices', 'cfdi_pdf_path')) {
                    $table->string('cfdi_pdf_path')->nullable();
                }

                if (! Schema::hasColumn('invoices', 'cfdi_stamped_at')) {
                    $table->timestamp('cfdi_stamped_at')->nullable();
                }

                if (! Schema::hasColumn('invoices', 'cfdi_cancelled_at')) {
                    $table->timestamp('cfdi_cancelled_at')->nullable();
                }

                if (! Schema::hasColumn('invoices', 'cfdi_cancel_status')) {
                    $table->string('cfdi_cancel_status', 40)->nullable();
                }

                if (! Schema::hasColumn('invoices', 'cfdi_cancel_reason')) {
                    $table->string('cfdi_cancel_reason', 10)->nullable();
                }

                if (! Schema::hasColumn('invoices', 'cfdi_cancel_substitution_uuid')) {
                    $table->string('cfdi_cancel_substitution_uuid', 80)->nullable();
                }
            });

            DB::table('invoices')
                ->whereNull('cfdi_status')
                ->update([
                    'cfdi_status' => 'pending',
                    'cfdi_version' => '4.0',
                    'cfdi_type' => 'I',
                    'updated_at' => now(),
                ]);
        }

        if (! Schema::hasTable('invoice_cfdi_audits')) {
            Schema::create('invoice_cfdi_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->nullable()->index();
                $table->foreignId('invoice_id')->nullable()->index();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('action', 40)->index();
                $table->string('status', 40)->index();
                $table->string('pac_provider', 40)->nullable();
                $table->string('pac_environment', 40)->nullable();
                $table->string('request_id', 120)->nullable();
                $table->json('request_meta')->nullable();
                $table->json('response_meta')->nullable();
                $table->text('message')->nullable();
                $table->string('ip_address', 80)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_cfdi_audits')) {
            Schema::dropIfExists('invoice_cfdi_audits');
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table): void {
                foreach ([
                    'cfdi_status',
                    'cfdi_uuid',
                    'cfdi_version',
                    'cfdi_type',
                    'cfdi_series',
                    'cfdi_folio',
                    'pac_provider',
                    'pac_environment',
                    'pac_request_id',
                    'pac_error_message',
                    'cfdi_xml_path',
                    'cfdi_pdf_path',
                    'cfdi_stamped_at',
                    'cfdi_cancelled_at',
                    'cfdi_cancel_status',
                    'cfdi_cancel_reason',
                    'cfdi_cancel_substitution_uuid',
                ] as $column) {
                    if (Schema::hasColumn('invoices', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
