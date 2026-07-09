<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('odoo_history_packages')) {
            Schema::create('odoo_history_packages', function (Blueprint $table) {
                $table->id();
                $table->string('period_label')->index();
                $table->string('bucket')->index();
                $table->string('package_name');
                $table->string('package_path');
                $table->string('sha256', 64)->index();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('extract_path');
                $table->string('status')->default('registered')->index();
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->unique(['period_label', 'bucket']);
            });
        }

        if (! Schema::hasTable('odoo_history_file_summaries')) {
            Schema::create('odoo_history_file_summaries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('package_id')->nullable()->index();
                $table->string('period_label')->index();
                $table->string('bucket')->index();
                $table->string('source_file')->index();
                $table->string('model')->nullable()->index();
                $table->unsignedInteger('row_count')->default(0);
                $table->decimal('sum_amount_total', 24, 4)->default(0);
                $table->decimal('sum_amount_paid', 24, 4)->default(0);
                $table->decimal('sum_quantity', 24, 4)->default(0);
                $table->decimal('sum_value', 24, 4)->default(0);
                $table->json('headers')->nullable();
                $table->json('raw_json')->nullable();
                $table->timestamps();

                $table->unique(['period_label', 'bucket', 'source_file']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_history_file_summaries');
        Schema::dropIfExists('odoo_history_packages');
    }
};
