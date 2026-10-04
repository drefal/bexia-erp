<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('computer_rental_session_lines')) {
            return;
        }

        Schema::create('computer_rental_session_lines', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('computer_rental_session_id')->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();

            $table->string('description', 255);

            $table->decimal('quantity', 16, 4)->default(1);
            $table->decimal('unit_price', 16, 4)->default(0);

            /*
             * Precio unitario capturado con IVA incluido,
             * igual al comportamiento actual del PDV.
             */
            $table->decimal('tax_rate', 8, 4)->default(0.16);

            $table->decimal('subtotal', 16, 4)->default(0);
            $table->decimal('tax_total', 16, 4)->default(0);
            $table->decimal('total', 16, 4)->default(0);

            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(
                ['computer_rental_session_id', 'product_id'],
                'computer_rental_session_lines_session_product_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('computer_rental_session_lines');
    }
};
