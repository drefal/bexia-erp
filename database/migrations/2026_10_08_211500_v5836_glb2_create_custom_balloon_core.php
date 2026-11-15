<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * ================================================================
         * TAMAÑOS
         * ================================================================
         * El tamaño define la presentación.
         * NO guarda el precio actual directamente.
         */
        Schema::create('custom_balloon_sizes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('code', 50);
            $table->string('name', 120);

            $table->string('dimensions', 120)->nullable();
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(
                ['company_id', 'code'],
                'cb_sizes_company_code_uq'
            );

            $table->index(
                ['company_id', 'is_active'],
                'cb_sizes_company_active_ix'
            );
        });

        /*
         * ================================================================
         * HISTORIAL DE PRECIOS
         * ================================================================
         * Precio global POR TAMAÑO dentro de la empresa.
         *
         * No se sobrescribe el histórico:
         * al cambiar precio se cierra la vigencia anterior y se crea otra.
         */
        Schema::create('custom_balloon_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('size_id')
                ->constrained('custom_balloon_sizes')
                ->cascadeOnDelete();

            $table->decimal('price', 14, 2);

            $table->timestampTz('valid_from');
            $table->timestampTz('valid_to')->nullable();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(
                ['company_id', 'size_id', 'valid_from'],
                'cb_prices_lookup_ix'
            );

            $table->index(
                ['size_id', 'valid_to'],
                'cb_prices_current_ix'
            );
        });

        /*
         * ================================================================
         * MODELOS / DISEÑOS
         * ================================================================
         */
        Schema::create('custom_balloon_models', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('code', 60);
            $table->string('name', 180);

            $table->text('description')->nullable();

            $table->string('image_path')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->unique(
                ['company_id', 'code'],
                'cb_models_company_code_uq'
            );

            $table->index(
                ['company_id', 'is_active'],
                'cb_models_company_active_ix'
            );
        });

        /*
         * ================================================================
         * TAMAÑOS DISPONIBLES POR MODELO
         * ================================================================
         *
         * Un modelo sólo indica qué tamaños admite.
         *
         * price_adjustment:
         * excepción opcional. Normalmente será 0.
         */
        Schema::create('custom_balloon_model_sizes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('model_id')
                ->constrained('custom_balloon_models')
                ->cascadeOnDelete();

            $table->foreignId('size_id')
                ->constrained('custom_balloon_sizes')
                ->cascadeOnDelete();

            $table->decimal('price_adjustment', 14, 2)
                ->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['company_id', 'model_id', 'size_id'],
                'cb_model_sizes_uq'
            );
        });

        /*
         * ================================================================
         * EXTRAS
         * ================================================================
         *
         * fixed  = precio configurado
         * manual = el usuario captura importe
         */
        Schema::create('custom_balloon_extras', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('code', 60);
            $table->string('name', 160);

            $table->string('pricing_type', 20)
                ->default('fixed');

            $table->decimal('price', 14, 2)
                ->default(0);

            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(
                ['company_id', 'code'],
                'cb_extras_company_code_uq'
            );
        });

        /*
         * ================================================================
         * PEDIDOS
         * ================================================================
         */
        Schema::create('custom_balloon_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('number', 40);

            /*
             * Cliente real de Bexia.
             */
            $table->foreignId('customer_contact_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();

            /*
             * Snapshot histórico.
             * Aunque el contacto cambie después, sabemos a quién se vendió.
             */
            $table->string('customer_name_snapshot', 255);
            $table->string('customer_phone_snapshot', 80)->nullable();

            $table->string('status', 50)
                ->default('captured');

            $table->timestampTz('ordered_at');
            $table->timestampTz('promised_at')->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('extras_total', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->decimal('deposit_amount', 14, 2)->default(0);
            $table->decimal('balance_amount', 14, 2)->default(0);

            $table->string('payment_status', 30)
                ->default('pending');

            $table->text('notes')->nullable();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestampTz('cancelled_at')->nullable();

            $table->foreignId('cancelled_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('cancel_reason')->nullable();

            $table->timestampTz('delivered_at')->nullable();

            /*
             * Integración futura con PDV.
             */
            $table->foreignId('pos_order_id')
                ->nullable()
                ->constrained('pos_orders')
                ->nullOnDelete();

            $table->timestampTz('sent_to_pos_at')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->unique(
                ['company_id', 'number'],
                'cb_orders_company_number_uq'
            );

            $table->index(
                ['company_id', 'status', 'promised_at'],
                'cb_orders_work_ix'
            );

            $table->index(
                ['company_id', 'customer_contact_id'],
                'cb_orders_customer_ix'
            );
        });

        /*
         * ================================================================
         * PARTIDAS DEL PEDIDO
         * ================================================================
         *
         * Aquí se congela la fotografía comercial:
         * modelo, tamaño y precios usados en ese momento.
         */
        Schema::create('custom_balloon_order_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('order_id')
                ->constrained('custom_balloon_orders')
                ->cascadeOnDelete();

            $table->foreignId('model_id')
                ->nullable()
                ->constrained('custom_balloon_models')
                ->nullOnDelete();

            $table->foreignId('size_id')
                ->nullable()
                ->constrained('custom_balloon_sizes')
                ->nullOnDelete();

            /*
             * Snapshot histórico del catálogo.
             */
            $table->string('model_name_snapshot', 180);
            $table->string('size_name_snapshot', 120);

            $table->decimal('quantity', 12, 3)
                ->default(1);

            /*
             * Precio global vigente del tamaño.
             */
            $table->decimal('base_unit_price', 14, 2);

            /*
             * Excepción del modelo.
             */
            $table->decimal('model_adjustment', 14, 2)
                ->default(0);

            /*
             * Precio unitario congelado antes de extras.
             */
            $table->decimal('unit_price', 14, 2);

            $table->decimal('subtotal', 14, 2);

            /*
             * Personalización.
             */
            $table->text('personalization_text')->nullable();
            $table->string('personalization_color', 120)->nullable();
            $table->string('personalization_font', 160)->nullable();

            $table->text('notes')->nullable();

            /*
             * Imágenes propias del trabajo.
             */
            $table->string('customer_reference_image_path')->nullable();
            $table->string('design_image_path')->nullable();
            $table->string('final_image_path')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(
                ['company_id', 'order_id'],
                'cb_order_lines_order_ix'
            );
        });

        /*
         * ================================================================
         * EXTRAS POR PARTIDA
         * ================================================================
         */
        Schema::create('custom_balloon_order_line_extras', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('order_line_id')
                ->constrained('custom_balloon_order_lines')
                ->cascadeOnDelete();

            $table->foreignId('extra_id')
                ->nullable()
                ->constrained('custom_balloon_extras')
                ->nullOnDelete();

            /*
             * Snapshot histórico.
             */
            $table->string('extra_name_snapshot', 160);
            $table->string('pricing_type_snapshot', 20);

            $table->decimal('quantity', 12, 3)
                ->default(1);

            $table->decimal('unit_price', 14, 2);

            $table->decimal('total', 14, 2);

            $table->timestamps();

            $table->index(
                ['company_id', 'order_line_id'],
                'cb_line_extras_line_ix'
            );
        });

        /*
         * ================================================================
         * BITÁCORA DEL PEDIDO
         * ================================================================
         */
        Schema::create('custom_balloon_order_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('order_id')
                ->constrained('custom_balloon_orders')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('event', 80);

            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();

            $table->text('description')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestampTz('created_at')
                ->useCurrent();

            $table->index(
                ['company_id', 'order_id', 'created_at'],
                'cb_order_events_order_ix'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_balloon_order_events');
        Schema::dropIfExists('custom_balloon_order_line_extras');
        Schema::dropIfExists('custom_balloon_order_lines');
        Schema::dropIfExists('custom_balloon_orders');
        Schema::dropIfExists('custom_balloon_extras');
        Schema::dropIfExists('custom_balloon_model_sizes');
        Schema::dropIfExists('custom_balloon_models');
        Schema::dropIfExists('custom_balloon_prices');
        Schema::dropIfExists('custom_balloon_sizes');
    }
};
