<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('odoo_history_raw_records')) {
            Schema::create('odoo_history_raw_records', function (Blueprint $table) {
                $table->id();
                $table->string('period_label')->index();
                $table->string('bucket')->index();
                $table->string('source_file')->index();
                $table->string('model')->nullable()->index();
                $table->bigInteger('odoo_id')->nullable()->index();
                $table->bigInteger('odoo_company_id')->nullable()->index();
                $table->string('record_name')->nullable()->index();
                $table->string('record_date')->nullable()->index();
                $table->string('record_state')->nullable()->index();
                $table->string('raw_hash', 64)->index();
                $table->json('raw_json');
                $table->timestamps();

                $table->unique(['period_label', 'bucket', 'source_file', 'odoo_id'], 'odoo_hist_raw_unique_record');
                $table->index(['period_label', 'bucket', 'model'], 'odoo_hist_raw_period_bucket_model_idx');
            });
        }

        if (! Schema::hasTable('odoo_history_import_runs')) {
            Schema::create('odoo_history_import_runs', function (Blueprint $table) {
                $table->id();
                $table->string('version')->index();
                $table->string('period_label')->index();
                $table->string('bucket')->index();
                $table->string('status')->index();
                $table->unsignedInteger('files_processed')->default(0);
                $table->unsignedInteger('rows_inserted')->default(0);
                $table->unsignedInteger('rows_deleted_before')->default(0);
                $table->json('raw_json')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_history_import_runs');
        Schema::dropIfExists('odoo_history_raw_records');
    }
};
