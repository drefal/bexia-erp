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
            $supplierFlags = [
                'is_supplier',
                'supplier',
                'is_vendor',
                'vendor',
                'is_provider',
                'provider',
                'es_proveedor',
                'proveedor',
            ];

            $customerFlags = [
                'is_customer',
                'customer',
                'is_client',
                'client',
                'es_cliente',
                'cliente',
            ];

            $hasSupplierFlag = false;
            foreach ($supplierFlags as $column) {
                if (Schema::hasColumn('contacts', $column)) {
                    $hasSupplierFlag = true;
                    break;
                }
            }

            $hasCustomerFlag = false;
            foreach ($customerFlags as $column) {
                if (Schema::hasColumn('contacts', $column)) {
                    $hasCustomerFlag = true;
                    break;
                }
            }

            if (! $hasSupplierFlag) {
                $table->boolean('is_supplier')->default(false)->index();
            }

            if (! $hasCustomerFlag) {
                $table->boolean('is_customer')->default(false)->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contacts')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table): void {
            if (Schema::hasColumn('contacts', 'is_supplier')) {
                $table->dropColumn('is_supplier');
            }

            if (Schema::hasColumn('contacts', 'is_customer')) {
                $table->dropColumn('is_customer');
            }
        });
    }
};
