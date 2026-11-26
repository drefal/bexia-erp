<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'report_exports',
            function (Blueprint $table): void {

                $table->uuid('id')->primary();

                $table->foreignId('company_id')
                    ->constrained('companies')
                    ->restrictOnDelete();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->string('report_type', 80);

                $table->string('status', 20)
                    ->default('pending');

                $table->jsonb('filters');

                $table->unsignedBigInteger('queue_job_id')
                    ->nullable()
                    ->unique();

                $table->unsignedInteger('row_count')
                    ->default(0);

                $table->unsignedInteger('total_blocks')
                    ->default(0);

                $table->unsignedInteger('completed_blocks')
                    ->default(0);

                $table->unsignedSmallInteger('progress')
                    ->default(0);

                $table->unsignedBigInteger('file_size')
                    ->nullable();

                $table->string('file_sha256', 64)
                    ->nullable();

                $table->string('error_code', 80)
                    ->nullable();

                $table->timestampTz('started_at')
                    ->nullable();

                $table->timestampTz('heartbeat_at')
                    ->nullable();

                $table->timestampTz('completed_at')
                    ->nullable();

                $table->timestampTz('expires_at')
                    ->nullable();

                $table->timestampsTz();

                $table->index(
                    ['company_id', 'user_id', 'created_at'],
                    'rex_company_user_created_idx'
                );

                $table->index(
                    ['status', 'created_at'],
                    'rex_status_created_idx'
                );

                $table->index(
                    ['status', 'expires_at'],
                    'rex_status_expiration_idx'
                );

                $table->index(
                    ['status', 'heartbeat_at'],
                    'rex_status_heartbeat_idx'
                );
            }
        );

        DB::statement("
            ALTER TABLE report_exports
            ADD CONSTRAINT rex_valid_status
            CHECK (
                status IN (
                    'pending',
                    'processing',
                    'completed',
                    'failed',
                    'expired'
                )
            )
        ");

        DB::statement("
            ALTER TABLE report_exports
            ADD CONSTRAINT rex_valid_progress
            CHECK (
                progress >= 0
                AND progress <= 100
            )
        ");

        DB::statement("
            ALTER TABLE report_exports
            ADD CONSTRAINT rex_valid_blocks
            CHECK (
                row_count >= 0
                AND total_blocks >= 0
                AND completed_blocks >= 0
                AND completed_blocks <= total_blocks
            )
        ");

        DB::statement("
            ALTER TABLE report_exports
            ADD CONSTRAINT rex_valid_completed
            CHECK (
                status <> 'completed'
                OR (
                    progress = 100
                    AND total_blocks >= 1
                    AND completed_blocks = total_blocks
                    AND file_size IS NOT NULL
                    AND file_size > 0
                    AND file_sha256 IS NOT NULL
                    AND completed_at IS NOT NULL
                    AND expires_at IS NOT NULL
                    AND expires_at > completed_at
                )
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
