<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('billing_series')) {
            Schema::create('billing_series', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('pos_point_id')->nullable()->index();
                $table->string('document_type', 40)->default('invoice')->index();
                $table->string('name');
                $table->string('series', 80);
                $table->unsignedInteger('year')->nullable()->index();
                $table->unsignedBigInteger('next_number')->default(1);
                $table->unsignedBigInteger('last_number')->nullable();
                $table->unsignedTinyInteger('padding')->default(5);
                $table->string('reset_period', 20)->default('yearly');
                $table->boolean('is_default')->default(false)->index();
                $table->boolean('active')->default(true)->index();
                $table->text('notes')->nullable();
                $table->timestamp('last_assigned_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'document_type', 'active']);
                $table->index(['company_id', 'document_type', 'branch_id', 'pos_point_id'], 'billing_series_context_idx');
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table): void {
                if (! Schema::hasColumn('invoices', 'cfdi_number_display')) {
                    $table->string('cfdi_number_display', 160)->nullable()->after('cfdi_folio')->index();
                }

                if (! Schema::hasColumn('invoices', 'billing_series_id')) {
                    $table->unsignedBigInteger('billing_series_id')->nullable()->after('cfdi_number_display')->index();
                }
            });
        }

        if (Schema::hasTable('companies') && Schema::hasTable('billing_series')) {
            $year = (int) date('Y');

            foreach (DB::table('companies')->orderBy('id')->get() as $company) {
                $exists = DB::table('billing_series')
                    ->where('company_id', $company->id)
                    ->where('document_type', 'invoice')
                    ->exists();

                if (! $exists) {
                    DB::table('billing_series')->insert([
                        'company_id' => $company->id,
                        'branch_id' => null,
                        'pos_point_id' => null,
                        'document_type' => 'invoice',
                        'name' => 'Facturas ' . $year,
                        'series' => 'INV ' . $year,
                        'year' => $year,
                        'next_number' => 1,
                        'last_number' => null,
                        'padding' => 5,
                        'reset_period' => 'yearly',
                        'is_default' => true,
                        'active' => true,
                        'notes' => 'Serie inicial creada automáticamente. Ajusta siguiente folio antes de timbrar.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table): void {
                foreach ([
                    'cfdi_number_display',
                    'billing_series_id',
                ] as $column) {
                    if (Schema::hasColumn('invoices', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('billing_series');
    }
};
