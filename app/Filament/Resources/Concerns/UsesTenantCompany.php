<?php

namespace App\Filament\Resources\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait UsesTenantCompany
{
    public static function currentCompanyId(): ?int
    {
        try {
            $tenant = Filament::getTenant();

            if (is_object($tenant)) {
                if (method_exists($tenant, 'getKey') && $tenant->getKey()) {
                    return (int) $tenant->getKey();
                }

                if (isset($tenant->company_id)) {
                    return (int) $tenant->company_id;
                }

                if (isset($tenant->id)) {
                    return (int) $tenant->id;
                }
            }

            if (is_numeric($tenant)) {
                return (int) $tenant;
            }
        } catch (\Throwable $e) {
            // Fallback al usuario autenticado.
        }

        $user = Auth::user();

        if ($user) {
            foreach (['current_company_id', 'active_company_id', 'company_id', 'tenant_company_id'] as $field) {
                if (isset($user->{$field}) && $user->{$field}) {
                    return (int) $user->{$field};
                }
            }
        }

        return null;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $companyId = static::currentCompanyId();

        $model = static::getModel();
        $table = (new $model())->getTable();

        if ($companyId && Schema::hasColumn($table, 'company_id')) {
            $query->where($table . '.company_id', $companyId);
        }

        return $query;
    }
}
