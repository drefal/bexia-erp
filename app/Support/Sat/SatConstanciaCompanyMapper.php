<?php

namespace App\Support\Sat;

use App\Models\Company;
use App\Models\CompanyGroup;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SatConstanciaCompanyMapper
{
    public function __construct(
        private readonly SatConstanciaParser $parser,
    ) {
    }

    public function normalizeStoredPath(mixed $value): ?string
    {
        if (is_array($value)) {
            $first = reset($value);

            return is_string($first) ? $first : null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function attributesFromStoredPath(string $storedPath, ?int $companyGroupId = null): array
    {
        $absolutePath = Storage::disk('local')->path($storedPath);
        $parsed = $this->parser->parseFile($absolutePath);

        $name = $parsed['commercial_name']
            ?: $parsed['business_name']
            ?: $parsed['rfc']
            ?: 'Empresa sin nombre';

        $attributes = [
            'name' => $name,
            'business_name' => $parsed['business_name'] ?: $name,
            'tax_id' => $parsed['rfc'],
            'tax_regime' => $parsed['tax_regime_code'],
            'fiscal_postal_code' => $parsed['fiscal_postal_code'],
            'street' => $parsed['street'],
            'ext_number' => $parsed['ext_number'],
            'int_number' => $parsed['int_number'],
            'neighborhood' => $parsed['neighborhood'],
            'municipality' => $parsed['municipality'],
            'city' => $parsed['locality'] ?: $parsed['municipality'],
            'state' => $parsed['state'],
            'country' => 'MEXICO',
            'sat_constancia_path' => $storedPath,
            'sat_constancia_uploaded_at' => now(),
            'sat_constancia_parsed_at' => now(),
            'sat_constancia_parsed_data' => $parsed,
        ];

        if ($companyGroupId) {
            $group = CompanyGroup::query()->find($companyGroupId);

            if ($group) {
                $attributes['company_group_id'] = $group->id;
                $attributes['organization_id'] = $group->organization_id;
            }
        }

        return $attributes;
    }

    public function uniqueSlug(string $baseName, ?int $ignoreCompanyId = null): string
    {
        $base = Str::slug($baseName);

        if ($base === '') {
            $base = 'empresa';
        }

        $slug = $base;
        $i = 2;

        while (
            Company::query()
                ->when($ignoreCompanyId, fn ($query) => $query->whereKeyNot($ignoreCompanyId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    public function requiredDataIsPresent(array $attributes): bool
    {
        return filled($attributes['tax_id'] ?? null)
            && filled($attributes['business_name'] ?? null)
            && filled($attributes['fiscal_postal_code'] ?? null)
            && filled($attributes['tax_regime'] ?? null);
    }
}
