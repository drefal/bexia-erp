<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_cases')) {
            Schema::create('service_cases', function (Blueprint $table) {
                $table->id();

                // Multiempresa / tenant. Se deja sin FK para no romper si la tabla tenant usa otro nombre.
                $table->unsignedBigInteger('company_id')->nullable()->index();

                // Relaciones operativas opcionales. Se conectaran mejor en siguientes fases.
                $table->unsignedBigInteger('customer_id')->nullable()->index();

                $table->string('folio')->nullable()->unique();
                $table->string('subject');
                $table->longText('description')->nullable();

                $table->string('channel', 50)->default('manual')->index();
                $table->string('case_type', 80)->default('general')->index();
                $table->string('priority', 30)->default('media')->index();
                $table->string('status', 60)->default('nuevo')->index();

                $table->string('contact_name')->nullable();
                $table->string('contact_email')->nullable();
                $table->string('contact_phone')->nullable();

                $table->string('assigned_team')->nullable()->index();
                $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
                $table->timestamp('assigned_at')->nullable();
                $table->unsignedBigInteger('assigned_by')->nullable()->index();

                $table->timestamp('first_response_at')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable()->index();

                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->json('metadata')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'priority']);
                $table->index(['company_id', 'case_type']);
            });
        }

        if (! Schema::hasTable('repair_orders')) {
            Schema::create('repair_orders', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('service_case_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();

                $table->string('folio')->nullable()->unique();

                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('sale_id')->nullable()->index();
                $table->unsignedBigInteger('invoice_id')->nullable()->index();

                $table->string('product_name')->nullable();
                $table->string('serial_number')->nullable()->index();
                $table->string('lot_number')->nullable()->index();

                $table->string('status', 60)->default('recibido')->index();
                $table->string('warranty_status', 60)->default('pendiente')->index();
                $table->timestamp('warranty_expires_at')->nullable();

                $table->timestamp('received_at')->nullable();
                $table->timestamp('promised_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('closed_at')->nullable();

                $table->longText('received_condition')->nullable();
                $table->longText('initial_diagnosis')->nullable();
                $table->longText('technical_diagnosis')->nullable();
                $table->longText('resolution')->nullable();

                $table->decimal('estimated_cost', 14, 2)->default(0);
                $table->decimal('actual_cost', 14, 2)->default(0);

                $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
                $table->timestamp('assigned_at')->nullable();
                $table->unsignedBigInteger('assigned_by')->nullable()->index();

                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->json('metadata')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'warranty_status']);
            });
        }

        if (! Schema::hasTable('service_case_events')) {
            Schema::create('service_case_events', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('service_case_id')->nullable()->index();
                $table->unsignedBigInteger('repair_order_id')->nullable()->index();

                $table->string('event_type', 80)->index();
                $table->string('from_status')->nullable();
                $table->string('to_status')->nullable();

                $table->unsignedBigInteger('performed_by')->nullable()->index();
                $table->timestamp('performed_at')->nullable()->index();

                $table->longText('notes')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('metadata')->nullable();

                $table->string('ip_address', 80)->nullable();
                $table->text('user_agent')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'event_type']);
            });
        }

        if (! Schema::hasTable('repair_order_approvals')) {
            Schema::create('repair_order_approvals', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('service_case_id')->nullable()->index();
                $table->unsignedBigInteger('repair_order_id')->nullable()->index();

                $table->string('approval_type', 100)->index();
                $table->string('status', 40)->default('pendiente')->index();

                $table->unsignedBigInteger('requested_by')->nullable()->index();
                $table->timestamp('requested_at')->nullable();

                $table->unsignedBigInteger('decided_by')->nullable()->index();
                $table->timestamp('decided_at')->nullable();

                $table->decimal('amount', 14, 2)->default(0);
                $table->longText('reason')->nullable();
                $table->longText('comments')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'approval_type']);
                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('repair_order_parts')) {
            Schema::create('repair_order_parts', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('repair_order_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();

                $table->string('sku')->nullable();
                $table->string('description');
                $table->decimal('quantity', 14, 4)->default(1);
                $table->decimal('unit_cost', 14, 2)->default(0);
                $table->decimal('total_cost', 14, 2)->default(0);

                $table->unsignedBigInteger('stock_movement_id')->nullable()->index();

                $table->unsignedBigInteger('requested_by')->nullable()->index();
                $table->unsignedBigInteger('delivered_by')->nullable()->index();
                $table->timestamp('delivered_at')->nullable();

                $table->longText('notes')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'repair_order_id']);
            });
        }

        if (! Schema::hasTable('service_attachments')) {
            Schema::create('service_attachments', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('service_case_id')->nullable()->index();
                $table->unsignedBigInteger('repair_order_id')->nullable()->index();

                $table->unsignedBigInteger('uploaded_by')->nullable()->index();
                $table->string('stage', 80)->nullable()->index();

                $table->string('file_name');
                $table->string('file_path');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size')->nullable();

                $table->boolean('is_customer_visible')->default(false)->index();
                $table->longText('notes')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'stage']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_attachments');
        Schema::dropIfExists('repair_order_parts');
        Schema::dropIfExists('repair_order_approvals');
        Schema::dropIfExists('service_case_events');
        Schema::dropIfExists('repair_orders');
        Schema::dropIfExists('service_cases');
    }
};
