<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contacts')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table): void {
            if (! Schema::hasColumn('contacts', 'csf_pdf_path')) {
                $table->string('csf_pdf_path', 500)->nullable()->after('extra_attributes');
            }

            if (! Schema::hasColumn('contacts', 'csf_source_filename')) {
                $table->string('csf_source_filename', 255)->nullable()->after('csf_pdf_path');
            }

            if (! Schema::hasColumn('contacts', 'csf_imported_at')) {
                $table->timestamp('csf_imported_at')->nullable()->after('csf_source_filename');
            }

            if (! Schema::hasColumn('contacts', 'csf_imported_by_user_id')) {
                $table->unsignedBigInteger('csf_imported_by_user_id')->nullable()->index()->after('csf_imported_at');
            }
        });
    }

    public function down(): void
    {
        // No bajamos estos campos automáticamente para no perder evidencia fiscal.
    }
};
