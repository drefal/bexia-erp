<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_documents')) {
            Schema::create('employee_documents', function (Blueprint $table) {
                $table->id();

                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('hr_document_type_id')->nullable()->constrained('hr_document_types')->nullOnDelete();

                $table->string('name');
                $table->string('document_number')->nullable();
                $table->string('status', 30)->default('pending')->index();

                $table->date('issued_at')->nullable();
                $table->date('expires_at')->nullable()->index();

                $table->string('file_path')->nullable();
                $table->string('file_original_name')->nullable();

                $table->text('notes')->nullable();

                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->index(['company_id', 'employee_id']);
                $table->index(['company_id', 'hr_document_type_id']);
                $table->index(['company_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
