<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $companyId = Filament::getTenant()
            ? (int) Filament::getTenant()->getKey()
            : (int) (request()->route('tenant') ?? auth()->user()?->company_id ?? 0);

        $company = InvoiceResource::companyRow($companyId);

        $data['company_id'] = $companyId;
        $data['number'] = $data['number'] ?? $this->nextNumber($companyId);
        $data['status'] = 'draft';
        $data['source_type'] = $data['source_type'] ?? 'manual';
        $data['source_id'] = null;
        $data['invoice_date'] = $data['invoice_date'] ?? now()->toDateString();
        $data['currency_code'] = $data['currency_code'] ?? 'MXN';
        $data['created_by_user_id'] = auth()->id();

        $data['issuer_name'] = (string) ($company->business_name ?? $company->name ?? '');
        $data['issuer_tax_id'] = (string) ($company->tax_id ?? '');
        $data['issuer_tax_regime'] = (string) ($company->tax_regime ?? '');
        $data['issuer_postal_code'] = (string) ($company->fiscal_postal_code ?? $company->postal_code ?? '');

        $data['subtotal'] = 0;
        $data['discount_total'] = 0;
        $data['tax_total'] = 0;
        $data['total'] = 0;
        $data['paid_total'] = (float) ($data['paid_total'] ?? 0);
        $data['balance_total'] = 0;
        $data['metadata'] = [
            'source' => 'manual_invoice',
            'created_from' => 'invoice_resource',
        ];

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return InvoiceResource::getUrl('edit', ['record' => $this->record]);
    }

    public function getTitle(): string
    {
        return 'Nueva factura';
    }

    protected function nextNumber(int $companyId): string
    {
        $prefix = 'FAC-' . now()->format('Ymd') . '-';

        $last = DB::table('invoices')
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if ($last && preg_match('/-(\d+)$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        do {
            $number = $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $exists = DB::table('invoices')
                ->where('company_id', $companyId)
                ->where('number', $number)
                ->exists();

            $next++;
        } while ($exists);

        return $number;
    }
}
