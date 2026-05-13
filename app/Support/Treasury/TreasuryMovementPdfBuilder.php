<?php

namespace App\Support\Treasury;

use App\Models\Company;
use App\Models\TreasuryMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class TreasuryMovementPdfBuilder
{
    public function stream(TreasuryMovement $movement)
    {
        $movement->loadMissing([
            'treasuryAccount',
        ]);

        $company = $this->resolveCompany($movement);

        $pdf = Pdf::loadView('pdf.treasury.movement', [
            'movement' => $movement,
            'company' => $company,
            'logoPath' => $this->resolveLogoPath($company),
            'generatedAt' => now(),
            'printedBy' => auth()->user(),
        ])->setPaper('letter', 'portrait');

        $filename = $this->filename($movement);

        return $pdf->stream($filename);
    }

    private function filename(TreasuryMovement $movement): string
    {
        $folio = $movement->id ?: 'movimiento';

        return 'movimiento_tesoreria_'.$folio.'.pdf';
    }

    private function resolveCompany(TreasuryMovement $movement): ?Company
    {
        if (method_exists($movement, 'company') && $movement->relationLoaded('company') && $movement->company) {
            return $movement->company;
        }

        $companyId = $movement->company_id ?? null;

        if (! $companyId) {
            return null;
        }

        return Company::query()->find($companyId);
    }

    private function resolveLogoPath(?Company $company): ?string
    {
        if (! $company) {
            return null;
        }

        $candidates = [
            $company->logo_path ?? null,
            $company->logo ?? null,
            $company->image_path ?? null,
            $company->image ?? null,
        ];

        foreach ($candidates as $value) {
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            if (str_starts_with($value, '/var/www/html/') && is_file($value)) {
                return $value;
            }

            $publicPath = Storage::disk('public')->path($value);

            if (is_file($publicPath)) {
                return $publicPath;
            }

            $localPath = Storage::disk('local')->path($value);

            if (is_file($localPath)) {
                return $localPath;
            }

            if (is_file(public_path($value))) {
                return public_path($value);
            }
        }

        return null;
    }
}
