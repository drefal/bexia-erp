<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sat_company_credentials')) {
            Schema::create('sat_company_credentials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('rfc', 13)->index();
                $table->string('legal_name')->nullable();
                $table->string('certificate_serial')->nullable();
                $table->string('credential_status')->default('pending')->index();
                $table->boolean('is_enabled')->default(false)->index();
                $table->timestamp('last_verified_at')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'rfc'], 'sat_company_credentials_company_rfc_unique');
            });
        }

        if (! Schema::hasTable('sat_download_requests')) {
            Schema::create('sat_download_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('requested_by_id')->nullable()->index();
                $table->string('request_uuid')->nullable()->index();
                $table->string('direction')->index(); // issued, received
                $table->string('request_kind')->default('xml')->index(); // xml, metadata
                $table->dateTime('date_from')->index();
                $table->dateTime('date_to')->index();
                $table->string('status')->default('draft')->index();
                $table->dateTime('requested_at')->nullable();
                $table->dateTime('finished_at')->nullable();
                $table->string('sat_status_code')->nullable();
                $table->text('sat_message')->nullable();
                $table->json('payload')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('sat_download_packages')) {
            Schema::create('sat_download_packages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sat_download_request_id')->index();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('package_id')->nullable()->index();
                $table->string('status')->default('pending')->index();
                $table->string('file_path')->nullable();
                $table->unsignedInteger('documents_count')->default(0);
                $table->string('checksum')->nullable();
                $table->dateTime('downloaded_at')->nullable();
                $table->dateTime('imported_at')->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('sat_cfdi_documents')) {
            Schema::create('sat_cfdi_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('sat_download_package_id')->nullable()->index();
                $table->unsignedBigInteger('imported_by_id')->nullable()->index();

                $table->string('uuid', 36)->index();
                $table->string('direction')->index(); // issued, received
                $table->string('cfdi_type')->nullable()->index(); // I, E, P, N, T
                $table->string('status')->default('vigente')->index();
                $table->string('version')->nullable();

                $table->string('issuer_rfc', 13)->nullable()->index();
                $table->string('issuer_name')->nullable();
                $table->string('receiver_rfc', 13)->nullable()->index();
                $table->string('receiver_name')->nullable();

                $table->dateTime('issued_at')->nullable()->index();
                $table->dateTime('certified_at')->nullable()->index();
                $table->dateTime('cancelled_at')->nullable();

                $table->string('currency', 10)->nullable();
                $table->decimal('exchange_rate', 18, 6)->nullable();
                $table->decimal('subtotal', 18, 6)->default(0);
                $table->decimal('discount', 18, 6)->default(0);
                $table->decimal('total_transferred_taxes', 18, 6)->default(0);
                $table->decimal('total_withheld_taxes', 18, 6)->default(0);
                $table->decimal('total', 18, 6)->default(0);

                $table->string('payment_form')->nullable();
                $table->string('payment_method')->nullable();
                $table->string('usage_cfdi')->nullable();
                $table->string('export_status')->nullable();

                $table->string('xml_path')->nullable();
                $table->string('xml_sha256')->nullable()->index();
                $table->string('source')->default('manual')->index();
                $table->dateTime('imported_at')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'uuid', 'direction'], 'sat_cfdi_company_uuid_direction_unique');
            });
        }

        if (! Schema::hasTable('sat_cfdi_concepts')) {
            Schema::create('sat_cfdi_concepts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sat_cfdi_document_id')->index();
                $table->unsignedBigInteger('company_id')->index();

                $table->string('product_key')->nullable()->index();
                $table->string('identification_number')->nullable()->index();
                $table->text('description')->nullable();
                $table->decimal('quantity', 18, 6)->default(0);
                $table->string('unit_key')->nullable();
                $table->string('unit_name')->nullable();
                $table->decimal('unit_price', 18, 6)->default(0);
                $table->decimal('amount', 18, 6)->default(0);
                $table->decimal('discount', 18, 6)->default(0);
                $table->json('taxes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sat_cfdi_taxes')) {
            Schema::create('sat_cfdi_taxes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sat_cfdi_document_id')->index();
                $table->unsignedBigInteger('company_id')->index();

                $table->string('tax_direction')->index(); // transferred, withheld
                $table->string('tax')->index(); // IVA, ISR, IEPS
                $table->string('factor_type')->nullable();
                $table->decimal('rate_or_fee', 18, 6)->nullable();
                $table->decimal('base', 18, 6)->default(0);
                $table->decimal('amount', 18, 6)->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sat_cfdi_processing_logs')) {
            Schema::create('sat_cfdi_processing_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('sat_cfdi_document_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('event')->index();
                $table->string('level')->default('info')->index();
                $table->text('message')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sat_cfdi_processing_logs');
        Schema::dropIfExists('sat_cfdi_taxes');
        Schema::dropIfExists('sat_cfdi_concepts');
        Schema::dropIfExists('sat_cfdi_documents');
        Schema::dropIfExists('sat_download_packages');
        Schema::dropIfExists('sat_download_requests');
        Schema::dropIfExists('sat_company_credentials');
    }
};
