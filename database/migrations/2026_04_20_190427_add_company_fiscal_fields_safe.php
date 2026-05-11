<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'business_name')) {
                $table->string('business_name')->nullable()->after('name');
            }

            if (! Schema::hasColumn('companies', 'tax_id')) {
                $table->string('tax_id', 30)->nullable()->after('business_name');
            }

            if (! Schema::hasColumn('companies', 'tax_regime')) {
                $table->string('tax_regime', 120)->nullable()->after('tax_id');
            }

            if (! Schema::hasColumn('companies', 'fiscal_postal_code')) {
                $table->string('fiscal_postal_code', 10)->nullable()->after('tax_regime');
            }

            if (! Schema::hasColumn('companies', 'street')) {
                $table->string('street')->nullable()->after('fiscal_postal_code');
            }

            if (! Schema::hasColumn('companies', 'ext_number')) {
                $table->string('ext_number', 30)->nullable()->after('street');
            }

            if (! Schema::hasColumn('companies', 'int_number')) {
                $table->string('int_number', 30)->nullable()->after('ext_number');
            }

            if (! Schema::hasColumn('companies', 'neighborhood')) {
                $table->string('neighborhood')->nullable()->after('int_number');
            }

            if (! Schema::hasColumn('companies', 'municipality')) {
                $table->string('municipality')->nullable()->after('neighborhood');
            }

            if (! Schema::hasColumn('companies', 'city')) {
                $table->string('city')->nullable()->after('municipality');
            }

            if (! Schema::hasColumn('companies', 'state')) {
                $table->string('state')->nullable()->after('city');
            }

            if (! Schema::hasColumn('companies', 'country')) {
                $table->string('country')->nullable()->after('state');
            }

            if (! Schema::hasColumn('companies', 'contact_name')) {
                $table->string('contact_name')->nullable()->after('country');
            }

            if (! Schema::hasColumn('companies', 'contact_phone')) {
                $table->string('contact_phone', 50)->nullable()->after('contact_name');
            }

            if (! Schema::hasColumn('companies', 'contact_email')) {
                $table->string('contact_email')->nullable()->after('contact_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            foreach ([
                'business_name',
                'tax_id',
                'tax_regime',
                'fiscal_postal_code',
                'street',
                'ext_number',
                'int_number',
                'neighborhood',
                'municipality',
                'city',
                'state',
                'country',
                'contact_name',
                'contact_phone',
                'contact_email',
            ] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
