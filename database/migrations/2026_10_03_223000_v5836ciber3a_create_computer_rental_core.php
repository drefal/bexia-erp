<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('computer_rental_rates')) {
            Schema::create('computer_rental_rates', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('company_id')->index();

                /*
                 * Producto de Bexia que se utilizará para mandar el cargo al PDV.
                 * Debe ser product_type = service.
                 */
                $table->unsignedBigInteger('product_id')->nullable()->index();

                $table->string('name', 160);

                /*
                 * open:
                 *   tiempo abierto; importe calculado al finalizar.
                 *
                 * prepaid:
                 *   duración e importe predefinidos.
                 */
                $table->string('billing_mode', 30)
                    ->default('open')
                    ->index();

                /*
                 * Tarifa base para modo abierto.
                 */
                $table->decimal('hourly_rate', 16, 4)
                    ->default(0);

                /*
                 * Cobro mínimo.
                 * Ejemplo: 15 minutos.
                 */
                $table->unsignedInteger('minimum_minutes')
                    ->default(1);

                /*
                 * Fracción de redondeo.
                 * Ejemplo: cada 15 minutos.
                 *
                 * Valor 1 = cobro prácticamente por minuto.
                 */
                $table->unsignedInteger('billing_increment_minutes')
                    ->default(1);

                /*
                 * Para paquetes de prepago.
                 */
                $table->unsignedInteger('prepaid_minutes')
                    ->nullable();

                $table->decimal('prepaid_price', 16, 4)
                    ->nullable();

                /*
                 * El precio que mandamos al PDV será IVA incluido,
                 * igual que el carrito actual.
                 */
                $table->decimal('tax_rate', 8, 4)
                    ->default(0.1600);

                $table->boolean('is_active')
                    ->default(true)
                    ->index();

                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->index(
                    ['company_id', 'is_active'],
                    'computer_rental_rates_company_active_idx'
                );
            });
        }

        if (! Schema::hasTable('computer_rental_stations')) {
            Schema::create('computer_rental_stations', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('company_id')->index();

                /*
                 * PDV predeterminado donde posteriormente se enviará el cobro.
                 */
                $table->unsignedBigInteger('pos_point_id')
                    ->nullable()
                    ->index();

                /*
                 * Tarifa predeterminada.
                 */
                $table->unsignedBigInteger('default_rate_id')
                    ->nullable()
                    ->index();

                $table->string('code', 80);
                $table->string('name', 160);

                /*
                 * available   = disponible
                 * in_use      = renta activa
                 * reserved    = reservado para una siguiente fase
                 * maintenance = fuera de servicio
                 * offline     = futuro agente sin conexión
                 */
                $table->string('status', 40)
                    ->default('available')
                    ->index();

                /*
                 * Campos preparados para el agente Windows,
                 * aunque todavía no lo implementamos.
                 */
                $table->uuid('agent_uuid')
                    ->nullable()
                    ->unique();

                $table->timestamp('last_heartbeat_at')
                    ->nullable();

                $table->boolean('is_active')
                    ->default(true)
                    ->index();

                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->unique(
                    ['company_id', 'code'],
                    'computer_rental_stations_company_code_unique'
                );

                $table->index(
                    ['company_id', 'status'],
                    'computer_rental_stations_company_status_idx'
                );
            });
        }

        if (! Schema::hasTable('computer_rental_sessions')) {
            Schema::create('computer_rental_sessions', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('station_id')->index();
                $table->unsignedBigInteger('rate_id')->index();

                /*
                 * Contexto comercial.
                 */
                $table->unsignedBigInteger('pos_point_id')
                    ->nullable()
                    ->index();

                $table->unsignedBigInteger('pos_session_id')
                    ->nullable()
                    ->index();

                $table->unsignedBigInteger('customer_id')
                    ->nullable()
                    ->index();

                /*
                 * Usuario Bexia que inicia / termina / cancela.
                 */
                $table->unsignedBigInteger('opened_by_user_id')
                    ->nullable()
                    ->index();

                $table->unsignedBigInteger('closed_by_user_id')
                    ->nullable()
                    ->index();

                /*
                 * active
                 * finished
                 * pending_pos
                 * sent_to_pos
                 * paid
                 * cancelled
                 */
                $table->string('status', 40)
                    ->default('active')
                    ->index();

                $table->string('billing_mode', 30)
                    ->default('open');

                /*
                 * Snapshot de la tarifa para que modificar después el catálogo
                 * no cambie una renta histórica.
                 */
                $table->decimal('hourly_rate', 16, 4)
                    ->default(0);

                $table->unsignedInteger('minimum_minutes')
                    ->default(1);

                $table->unsignedInteger('billing_increment_minutes')
                    ->default(1);

                $table->unsignedInteger('prepaid_minutes')
                    ->nullable();

                $table->decimal('prepaid_price', 16, 4)
                    ->nullable();

                $table->decimal('tax_rate', 8, 4)
                    ->default(0.1600);

                /*
                 * Tiempos reales.
                 */
                $table->timestamp('started_at')->index();
                $table->timestamp('ended_at')->nullable()->index();

                $table->unsignedBigInteger('duration_seconds')
                    ->default(0);

                $table->unsignedInteger('billable_minutes')
                    ->default(0);

                /*
                 * Importe IVA incluido.
                 */
                $table->decimal('amount', 16, 4)
                    ->default(0);

                /*
                 * Producto servicio utilizado para el ticket.
                 */
                $table->unsignedBigInteger('product_id')
                    ->nullable()
                    ->index();

                /*
                 * Liga con el ticket real del PDV.
                 */
                $table->unsignedBigInteger('pos_order_id')
                    ->nullable()
                    ->index();

                $table->timestamp('sent_to_pos_at')
                    ->nullable();

                $table->timestamp('paid_at')
                    ->nullable();

                $table->timestamp('cancelled_at')
                    ->nullable();

                $table->text('cancel_reason')
                    ->nullable();

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    ['station_id', 'status'],
                    'computer_rental_sessions_station_status_idx'
                );

                $table->index(
                    ['company_id', 'started_at'],
                    'computer_rental_sessions_company_started_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('computer_rental_sessions');
        Schema::dropIfExists('computer_rental_stations');
        Schema::dropIfExists('computer_rental_rates');
    }
};
