<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sat_product_service_codes')) {
            return;
        }

        Schema::table('sat_product_service_codes', function (Blueprint $table): void {
            if (! Schema::hasColumn('sat_product_service_codes', 'include_iva')) {
                $table->boolean('include_iva')->default(false)->after('description');
            }

            if (! Schema::hasColumn('sat_product_service_codes', 'include_ieps')) {
                $table->boolean('include_ieps')->default(false)->after('include_iva');
            }

            if (! Schema::hasColumn('sat_product_service_codes', 'required_complement')) {
                $table->text('required_complement')->nullable()->after('include_ieps');
            }

            if (! Schema::hasColumn('sat_product_service_codes', 'border_stimulus')) {
                $table->boolean('border_stimulus')->default(false)->after('required_complement');
            }

            if (! Schema::hasColumn('sat_product_service_codes', 'similar_words')) {
                $table->text('similar_words')->nullable()->after('border_stimulus');
            }

            if (! Schema::hasColumn('sat_product_service_codes', 'valid_from')) {
                $table->date('valid_from')->nullable()->after('similar_words');
            }

            if (! Schema::hasColumn('sat_product_service_codes', 'valid_to')) {
                $table->date('valid_to')->nullable()->after('valid_from');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sat_product_service_codes')) {
            return;
        }

        Schema::table('sat_product_service_codes', function (Blueprint $table): void {
            foreach ([
                'valid_to',
                'valid_from',
                'similar_words',
                'border_stimulus',
                'required_complement',
                'include_ieps',
                'include_iva',
            ] as $column) {
                if (Schema::hasColumn('sat_product_service_codes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
