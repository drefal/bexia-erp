<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_concept_payroll_group')) {
            Schema::create(
                'payroll_concept_payroll_group',
                function (Blueprint $table): void {
                    $table->id();

                    $table
                        ->foreignId('payroll_concept_id')
                        ->constrained('payroll_concepts')
                        ->cascadeOnDelete();

                    $table
                        ->foreignId('payroll_group_id')
                        ->constrained('payroll_groups')
                        ->cascadeOnDelete();

                    $table->timestamps();

                    $table->unique(
                        [
                            'payroll_concept_id',
                            'payroll_group_id',
                        ],
                        'payroll_concept_group_unique'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'payroll_concept_payroll_group'
        );
    }
};
