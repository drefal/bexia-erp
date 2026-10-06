<?php

namespace App\Support\Expenses;

use App\Models\Company;
use Illuminate\Support\Facades\Storage;

class ExpenseReportBranding
{
    public static function forCompany(?int $companyId): array
    {
        $company = $companyId
            ? Company::query()->find($companyId)
            : null;

        return [
            'company' => $company,
            'companyName' => $company?->business_name
                ?: $company?->name
                ?: 'Empresa',
            'logoDataUri' => static::logoDataUri($company),
        ];
    }

    public static function logoDataUri(?Company $company): ?string
    {
        if (! $company) {
            return null;
        }

        foreach ([
            $company->logo_path,
            $company->logo_compact_path,
        ] as $relativePath) {
            $relativePath = trim((string) $relativePath);

            if ($relativePath === '') {
                continue;
            }

            try {
                $disk = Storage::disk('public');

                if (! $disk->exists($relativePath)) {
                    continue;
                }

                $contents = $disk->get($relativePath);

                if ($contents === '') {
                    continue;
                }

                $extension = strtolower(
                    pathinfo($relativePath, PATHINFO_EXTENSION)
                );

                $mime = match ($extension) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                    'svg' => 'image/svg+xml',
                    default => 'image/png',
                };

                return 'data:' . $mime . ';base64,'
                    . base64_encode($contents);
            } catch (\Throwable) {
                //
            }
        }

        return null;
    }
}
