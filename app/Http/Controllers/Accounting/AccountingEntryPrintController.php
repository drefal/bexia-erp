<?php

namespace App\Http\Controllers\Accounting;

use App\Filament\Resources\AccountingEntryResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class AccountingEntryPrintController extends Controller
{
    public function __invoke(string|int $tenant, string|int $accountingEntry): Response
    {
        abort_unless(auth()->check(), 403);

        /*
         * La ruta ya está protegida por auth y abajo se valida que el asiento
         * pertenezca al tenant/empresa solicitado. No usamos aquí $user->can()
         * porque fuera del panel Filament puede devolver 403 aunque el usuario
         * sí vea el recurso dentro del panel.
         */
        $tenantId = (int) $tenant;
        $entryId = (int) $accountingEntry;

        $entry = DB::table('accounting_entries as e')
            ->leftJoin('accounting_journals as j', 'j.id', '=', 'e.journal_id')
            ->where('e.id', $entryId)
            ->where('e.company_id', $tenantId)
            ->first([
                'e.*',
                'j.code as journal_code',
                'j.name as journal_name',
            ]);

        abort_if(! $entry, 404);

        $company = DB::table('companies')->where('id', $tenantId)->first();

        $lines = DB::table('accounting_entry_lines as l')
            ->leftJoin('accounting_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('l.accounting_entry_id', $entryId)
            ->orderBy('l.line_number')
            ->get([
                'l.*',
                'a.code as account_code',
                'a.name as account_name',
            ]);

        $totals = [
            'debit' => (float) $lines->sum('debit'),
            'credit' => (float) $lines->sum('credit'),
        ];

        $pdf = Pdf::loadView('prints.accounting-entry', [
            'company' => $company,
            'logoSrc' => $this->companyLogoSrc($company),
            'entry' => $entry,
            'lines' => $lines,
            'totals' => $totals,
            'sourceLabel' => AccountingEntryResource::sourceLabel((string) ($entry->source_type ?? '')),
            'statusLabel' => AccountingEntryResource::statusLabel((string) ($entry->status ?? '')),
        ])->setPaper('letter', 'portrait');

        $filename = 'asiento-contable-' . preg_replace('/[^A-Za-z0-9\-_]/', '-', (string) $entry->entry_number) . '.pdf';

        return $pdf->stream($filename);
    }

    protected function companyLogoSrc(?object $company): ?string
    {
        if (! $company) {
            return null;
        }

        $fields = [
            'logo_url',
            'logo_path',
            'logo',
            'image_path',
            'image',
            'avatar',
        ];

        foreach ($fields as $field) {
            $value = isset($company->{$field}) ? trim((string) $company->{$field}) : '';

            if ($value === '') {
                continue;
            }

            if (str_starts_with($value, 'data:image')) {
                return $value;
            }

            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }

            $candidates = [
                $value,
                'storage/' . ltrim($value, '/'),
                'images/' . ltrim($value, '/'),
                'logos/' . ltrim($value, '/'),
            ];

            foreach ($candidates as $candidate) {
                $path = public_path(ltrim($candidate, '/'));

                if (is_file($path) && is_readable($path)) {
                    $mime = function_exists('mime_content_type') ? mime_content_type($path) : 'image/png';

                    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
                }
            }
        }

        return null;
    }
}
