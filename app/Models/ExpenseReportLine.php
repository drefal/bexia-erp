<?php

namespace App\Models;

use App\Support\Expenses\ExpenseReceiptService;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ExpenseReportLine extends Model
{
    protected $fillable = [
        'expense_report_id',
        'expense_category_id',
        'expense_project_id',
        'spent_by_employee_id',
        'expense_date',
        'supplier_name',
        'supplier_rfc',
        'description',
        'subtotal',
        'tax_amount',
        'total_amount',
        'currency_code',
        'payment_method',
        'cfdi_uuid',
        'sat_cfdi_document_id',
        'has_receipt',
        'requires_receipt',
        'receipt_exception_reason',
        'notes',
        'metadata',
    ];

    protected static function booted(): void
    {
        /*
         * has_receipt es NOT NULL en BD.
         *
         * Los componentes dinámicos/Repetater de Filament pueden
         * enviar explícitamente NULL cuando el Toggle está apagado.
         * En ese caso PostgreSQL no utiliza el default FALSE de la
         * columna, porque recibió un NULL explícito.
         *
         * Se normaliza aquí para proteger cualquier vía de guardado:
         * Filament, relación, importación o código de backend.
         */
        static::saving(function (ExpenseReportLine $line): void {
            if ($line->getAttribute('has_receipt') === null) {
                $line->setAttribute('has_receipt', false);
            }
        });

        static::saved(function (ExpenseReportLine $line): void {
            ExpenseReceiptService::syncAttachments($line);
        });
    }

    protected $casts = [
        'expense_date' => 'date',
        'subtotal' => 'decimal:6',
        'tax_amount' => 'decimal:6',
        'total_amount' => 'decimal:6',
        'has_receipt' => 'boolean',
        'requires_receipt' => 'boolean',
        'metadata' => 'array',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(
            ExpenseReport::class,
            'expense_report_id'
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            ExpenseCategory::class,
            'expense_category_id'
        );
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(
            ExpenseProject::class,
            'expense_project_id'
        );
    }

    public function spentByEmployee(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'spent_by_employee_id'
        );
    }

    public function satCfdiDocument(): BelongsTo
    {
        return $this->belongsTo(
            SatCfdiDocument::class,
            'sat_cfdi_document_id'
        );
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
