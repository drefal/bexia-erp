<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'address_line1')) {
                $table->string('address_line1')->nullable()->after('tax_id');
            }

            if (! Schema::hasColumn('companies', 'address_line2')) {
                $table->string('address_line2')->nullable()->after('address_line1');
            }

            if (! Schema::hasColumn('companies', 'city')) {
                $table->string('city')->nullable()->after('address_line2');
            }

            if (! Schema::hasColumn('companies', 'state')) {
                $table->string('state')->nullable()->after('city');
            }

            if (! Schema::hasColumn('companies', 'postal_code')) {
                $table->string('postal_code')->nullable()->after('state');
            }

            if (! Schema::hasColumn('companies', 'country')) {
                $table->string('country')->nullable()->after('postal_code');
            }

            if (! Schema::hasColumn('companies', 'contact_name')) {
                $table->string('contact_name')->nullable()->after('country');
            }

            if (! Schema::hasColumn('companies', 'contact_phone')) {
                $table->string('contact_phone')->nullable()->after('contact_name');
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
                'address_line1',
                'address_line2',
                'city',
                'state',
                'postal_code',
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
