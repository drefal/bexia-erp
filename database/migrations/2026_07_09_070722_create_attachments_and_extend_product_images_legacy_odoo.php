<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $legacyColumns = [
        'source_system',
        'source_model',
        'source_id',
        'source_attachment_id',
        'source_reference',
        'legacy_reference',
        'legacy_company_id',
        'legacy_payload',
        'migrated_at',
        'migration_batch_id',
        'is_legacy',
        'locked',
        'original_filename',
        'mimetype',
        'file_size',
        'checksum',
        'store_fname',
        'storage_path',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('attachments')) {
            Schema::create('attachments', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('company_id')
                    ->nullable()
                    ->constrained('companies')
                    ->nullOnDelete();

                $table->string('attachable_type')->nullable();
                $table->unsignedBigInteger('attachable_id')->nullable();

                $table->string('target_table')->nullable();
                $table->unsignedBigInteger('target_id')->nullable();

                $table->string('title')->nullable();
                $table->text('description')->nullable();

                $table->string('disk')->default('local');
                $table->string('storage_path')->nullable();
                $table->string('url')->nullable();

                $table->string('original_filename')->nullable();
                $table->string('mimetype')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('checksum')->nullable();
                $table->string('store_fname')->nullable();

                $table->string('source_system')->nullable();
                $table->string('source_model')->nullable();
                $table->string('source_id')->nullable();
                $table->string('source_attachment_id')->nullable();
                $table->string('source_reference')->nullable();
                $table->string('legacy_reference')->nullable();
                $table->unsignedBigInteger('legacy_company_id')->nullable();
                $table->json('legacy_payload')->nullable();
                $table->timestamp('migrated_at')->nullable();
                $table->string('migration_batch_id')->nullable();

                $table->boolean('is_legacy')->default(false);
                $table->boolean('locked')->default(false);
                $table->boolean('is_active')->default(true);

                $table->timestamps();

                $table->index(['attachable_type', 'attachable_id'], 'att_attachable_idx');
                $table->index(['target_table', 'target_id'], 'att_target_idx');
                $table->index(['company_id', 'is_legacy'], 'att_company_legacy_idx');
                $table->index(['source_system', 'source_model', 'source_id'], 'att_source_model_idx');
                $table->index(['checksum'], 'att_checksum_idx');
            });
        } else {
            Schema::table('attachments', function (Blueprint $table): void {
                $this->addAttachmentColumnsIfMissing('attachments', $table);
            });
        }

        if (Schema::hasTable('product_images')) {
            Schema::table('product_images', function (Blueprint $table): void {
                $this->addAttachmentColumnsIfMissing('product_images', $table);
            });
        }

        $this->createPostgresIndexes();
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS attachments_source_attach_uid');
        DB::statement('DROP INDEX IF EXISTS attachments_source_model_idx2');
        DB::statement('DROP INDEX IF EXISTS product_images_source_attach_uid');
        DB::statement('DROP INDEX IF EXISTS product_images_source_model_idx');

        Schema::dropIfExists('attachments');

        if (Schema::hasTable('product_images')) {
            Schema::table('product_images', function (Blueprint $table): void {
                $existing = array_values(array_filter($this->legacyColumns, fn ($column) => Schema::hasColumn('product_images', $column)));

                if (! empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }
    }

    private function addAttachmentColumnsIfMissing(string $tableName, Blueprint $table): void
    {
        if (! Schema::hasColumn($tableName, 'source_system')) {
            $table->string('source_system')->nullable();
        }

        if (! Schema::hasColumn($tableName, 'source_model')) {
            $table->string('source_model')->nullable();
        }

        if (! Schema::hasColumn($tableName, 'source_id')) {
            $table->string('source_id')->nullable();
        }

        if (! Schema::hasColumn($tableName, 'source_attachment_id')) {
            $table->string('source_attachment_id')->nullable();
        }

        if (! Schema::hasColumn($tableName, 'source_reference')) {
            $table->string('source_reference')->nullable();
        }

        if (! Schema::hasColumn($tableName, 'legacy_reference')) {
            $table->string('legacy_reference')->nullable();
        }

        if (! Schema::hasColumn($tableName, 'legacy_company_id')) {
            $table->unsignedBigInteger('legacy_company_id')->nullable();
        }

        if (! Schema::hasColumn($tableName, 'legacy_payload')) {
            $table->json('legacy_payload')->nullable();
        }

        if (! Schema::hasColumn($tableName, 'migrated_at')) {
            $table->timestamp('migrated_at')->nullable();
        }

        if (! Schema::hasColumn($tableName, 'migration_batch_id')) {
            $table->string('migration_batch_id')->nullable();
        }

        if (! Schema::hasColumn($tableName, 'is_legacy')) {
            $table->boolean('is_legacy')->default(false);
        }

        if (! Schema::hasColumn($tableName, 'locked')) {
            $table->boolean('locked')->default(false);
        }

        if (! Schema::hasColumn($tableName, 'original_filename')) {
            $table->string('original_filename')->nullable();
        }

        if (! Schema::hasColumn($tableName, 'mimetype')) {
            $table->string('mimetype')->nullable();
        }

        if (! Schema::hasColumn($tableName, 'file_size')) {
            $table->unsignedBigInteger('file_size')->nullable();
        }

        if (! Schema::hasColumn($tableName, 'checksum')) {
            $table->string('checksum')->nullable();
        }

        if (! Schema::hasColumn($tableName, 'store_fname')) {
            $table->string('store_fname')->nullable();
        }

        if (! Schema::hasColumn($tableName, 'storage_path')) {
            $table->string('storage_path')->nullable();
        }
    }

    private function createPostgresIndexes(): void
    {
        if (Schema::hasTable('attachments')) {
            DB::statement("
                CREATE UNIQUE INDEX IF NOT EXISTS attachments_source_attach_uid
                ON attachments (source_system, source_attachment_id)
                WHERE source_system IS NOT NULL
                  AND source_attachment_id IS NOT NULL
            ");

            DB::statement("
                CREATE INDEX IF NOT EXISTS attachments_source_model_idx2
                ON attachments (source_system, source_model, source_id)
            ");
        }

        if (Schema::hasTable('product_images')) {
            DB::statement("
                CREATE UNIQUE INDEX IF NOT EXISTS product_images_source_attach_uid
                ON product_images (source_system, source_attachment_id)
                WHERE source_system IS NOT NULL
                  AND source_attachment_id IS NOT NULL
            ");

            DB::statement("
                CREATE INDEX IF NOT EXISTS product_images_source_model_idx
                ON product_images (source_system, source_model, source_id)
            ");
        }
    }
};
