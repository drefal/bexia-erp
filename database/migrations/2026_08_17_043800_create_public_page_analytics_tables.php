<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'public_page_visit_stats',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('company_id')
                    ->constrained('companies')
                    ->cascadeOnDelete();

                $table->string('page_key', 100);
                $table->date('stat_date');

                $table
                    ->unsignedBigInteger('total_views')
                    ->default(0);

                $table
                    ->unsignedBigInteger('unique_visitors')
                    ->default(0);

                $table
                    ->unsignedBigInteger('pdf_downloads')
                    ->default(0);

                $table->timestamps();

                $table->unique(
                    [
                        'company_id',
                        'page_key',
                        'stat_date',
                    ],
                    'public_page_stats_unique'
                );

                $table->index(
                    [
                        'company_id',
                        'page_key',
                    ],
                    'public_page_stats_lookup'
                );
            }
        );

        Schema::create(
            'public_page_visitors',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('company_id')
                    ->constrained('companies')
                    ->cascadeOnDelete();

                $table->string('page_key', 100);
                $table->date('stat_date');

                $table
                    ->char('visitor_hash', 64);

                $table->timestamp('created_at');

                $table->unique(
                    [
                        'company_id',
                        'page_key',
                        'stat_date',
                        'visitor_hash',
                    ],
                    'public_page_visitor_day_unique'
                );

                $table->index(
                    [
                        'company_id',
                        'page_key',
                        'visitor_hash',
                    ],
                    'public_page_visitor_lookup'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'public_page_visitors'
        );

        Schema::dropIfExists(
            'public_page_visit_stats'
        );
    }
};
