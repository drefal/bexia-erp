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

        $businessName = $this->cleanName($parsed['business_name'] ?? null);
        $commercialName = $this->cleanName($parsed['commercial_name'] ?? null);

        $name = $this->resolveDisplayName(
            $commercialName,
            $businessName,
            $parsed['rfc'] ?? null,
        );

        // Guardamos tambien el parsed_data ya normalizado para auditoria futura.
        $parsed['business_name'] = $businessName ?: $name;
        $parsed['commercial_name'] = $name;

        $attributes = [
            'name' => $name,
            'business_name' => $businessName ?: $name,
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

    private function cleanName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $value !== '' ? $value : null;
    }

    private function compactName(?string $value): ?string
    {
        $value = $this->cleanName($value);

        if (! $value) {
            return null;
        }

        return preg_replace('/[^A-Z0-9Ñ&]/u', '', mb_strtoupper($value));
    }

    private function resolveDisplayName(?string $commercialName, ?string $businessName, ?string $rfc): string
    {
        $commercialName = $this->cleanName($commercialName);
        $businessName = $this->cleanName($businessName);

        if (! $businessName && ! $commercialName) {
            return $rfc ?: 'Empresa sin nombre';
        }

        if (! $commercialName) {
            return $businessName ?: ($rfc ?: 'Empresa sin nombre');
        }

        if (! $businessName) {
            return $commercialName ?: ($rfc ?: 'Empresa sin nombre');
        }

        $commercialCompact = $this->compactName($commercialName);
        $businessCompact = $this->compactName($businessName);

        /*
         * Regla general para Constancias SAT:
         * En algunos PDF el campo Nombre Comercial viene vacio,
         * pero el parser puede tomar un texto del encabezado o tabla sin espacios.
         *
         * Si el nombre comercial viene todo pegado y la razon social ya viene legible
         * con espacios, usamos la razon social como nombre comercial.
         *
         * Esto evita correcciones por empresa:
         * - MEMMONKELECTRICMOBILITY -> MEM MONK MOVILIDAD ELECTRICA
         * - BIKESCYCLESANDPARTSMEXICO -> BIKES CYCLES AND PARTS MEXICO
         */
        $commercialLooksCompacted = $commercialCompact
            && $commercialName === $commercialCompact
            && substr_count($commercialName, ' ') === 0
            && mb_strlen($commercialCompact) >= 12;

        $businessLooksReadable = substr_count($businessName, ' ') >= 1;

        if ($commercialLooksCompacted && $businessLooksReadable) {
            return $businessName;
        }

        $sameNameDifferentSpacing = $commercialCompact
            && $businessCompact
            && $commercialCompact === $businessCompact
            && substr_count($businessName, ' ') > substr_count($commercialName, ' ');

        if ($sameNameDifferentSpacing) {
            return $businessName;
        }

        return $commercialName;
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
