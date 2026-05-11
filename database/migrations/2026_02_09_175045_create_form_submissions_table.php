<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('form_id')
                ->constrained('forms')
                ->cascadeOnDelete();

            $table->string('status')->default('BORRADOR'); // BORRADOR | ENVIADA | CANCELADA
            $table->string('folio')->nullable()->unique();

            $table->foreignId('company_id')->nullable()
                ->constrained('companies')->nullOnDelete();

            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->jsonb('data');              // respuestas del formulario
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
