<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

                $table->string('name');
                $table->string('code')->nullable();
                $table->boolean('active')->default(true);

                $table->string('address_line1')->nullable();
                $table->string('address_line2')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('country')->nullable();

                $table->string('contact_name')->nullable();
                $table->string('contact_phone')->nullable();
                $table->string('contact_email')->nullable();

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index('company_id');
                $table->unique(['company_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
