<?php

namespace App\Support\Navigation;

use App\Models\BexiaMenuGroup;
use App\Models\BexiaMenuItem;
use Filament\Navigation\NavigationGroup;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BexiaMenuRuntime
{
    public static function tablesReady(): bool
    {
        try {
            return Schema::hasTable('bexia_menu_groups')
                && Schema::hasTable('bexia_menu_items');
        } catch (Throwable) {
            return false;
        }
    }

    public static function groupLabel(string $key, string $fallback): string
    {
        if (! static::tablesReady()) {
            return $fallback;
        }

        try {
            $group = BexiaMenuGroup::query()
                ->where('key', $key)
                ->first();

            if (! $group) {
                return $fallback;
            }

            /*
             * Importante:
             * Los nombres de grupos NO se toman de label editable.
             * Filament todavía tiene muchos Resources con navigationGroup fijo.
             * Si el grupo cambia de nombre, Filament puede crear/reacomodar grupos.
             */
            $label = $group->default_label ?: $fallback;

            return filled($label) ? (string) $label : $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }

    public static function itemLabel(string $key, string $fallback): string
    {
        if (! static::tablesReady()) {
            return $fallback;
        }

        try {
            $label = BexiaMenuItem::query()
                ->where('key', $key)
                ->value('label');

            return filled($label) ? (string) $label : $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }

    public static function itemSort(string $key, int $fallback): int
    {
        if (! static::tablesReady()) {
            return $fallback;
        }

        try {
            $sort = BexiaMenuItem::query()
                ->where('key', $key)
                ->value('sort');

            return is_numeric($sort) ? (int) $sort : $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }

    public static function itemVisible(string $key, bool $fallback = true): bool
    {
        if (! static::tablesReady()) {
            return $fallback;
        }

        try {
            $item = BexiaMenuItem::query()
                ->where('key', $key)
                ->first();

            if (! $item) {
                return $fallback;
            }

            return (bool) $item->is_visible;
        } catch (Throwable) {
            return $fallback;
        }
    }

    public static function itemGroupLabel(string $itemKey, string $fallbackGroupKey, string $fallbackGroupLabel): string
    {
        if (! static::tablesReady()) {
            return $fallbackGroupLabel;
        }

        try {
            $item = BexiaMenuItem::query()
                ->with('group')
                ->where('key', $itemKey)
                ->first();

            if ($item && $item->group) {
                $label = $item->group->default_label ?: $item->group->label;

                if (filled($label)) {
                    return (string) $label;
                }
            }

            return static::groupLabel($fallbackGroupKey, $fallbackGroupLabel);
        } catch (Throwable) {
            return $fallbackGroupLabel;
        }
    }

    public static function navigationGroups(array $fallbackGroups): array
    {
        if (! static::tablesReady()) {
            return collect($fallbackGroups)
                ->map(fn (string $label) => NavigationGroup::make($label))
                ->values()
                ->all();
        }

        try {
            $groups = BexiaMenuGroup::query()
                ->orderBy('sort')
                ->orderBy('default_label')
                ->get();

            if ($groups->isEmpty()) {
                return collect($fallbackGroups)
                    ->map(fn (string $label) => NavigationGroup::make($label))
                    ->values()
                    ->all();
            }

            return $groups
                ->map(function (BexiaMenuGroup $group): NavigationGroup {
                    $label = $group->default_label ?: $group->label;

                    return NavigationGroup::make((string) $label);
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return collect($fallbackGroups)
                ->map(fn (string $label) => NavigationGroup::make($label))
                ->values()
                ->all();
        }
    }

    public static function shouldRegister(string $key, mixed $fallback = true): bool
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('bexia_menu_items')
                || ! \Illuminate\Support\Facades\Schema::hasTable('bexia_menu_groups')) {
                return static::evaluateFallback($fallback);
            }

            $item = \Illuminate\Support\Facades\DB::table('bexia_menu_items as i')
                ->join('bexia_menu_groups as g', 'g.id', '=', 'i.group_id')
                ->where('i.key', $key)
                ->select([
                    'i.is_visible as item_visible',
                    'i.permission_name',
                    'g.is_visible as group_visible',
                ])
                ->first();

            if (! $item) {
                return static::evaluateFallback($fallback);
            }

            if (! (bool) $item->group_visible || ! (bool) $item->item_visible) {
                return false;
            }

            $permissionName = trim((string) ($item->permission_name ?? ''));

            if ($permissionName === '') {
                return true;
            }

            return static::userCanSeePermission($permissionName);
        } catch (\Throwable) {
            return static::evaluateFallback($fallback);
        }
    }


    protected static function evaluateFallback(mixed $fallback): bool
    {
        try {
            if ($fallback instanceof \Closure) {
                return (bool) $fallback();
            }

            if (is_callable($fallback) && ! is_string($fallback)) {
                return (bool) $fallback();
            }

            return (bool) $fallback;
        } catch (\Throwable) {
            return true;
        }
    }

    protected static function userCanSeePermission(string $permissionName): bool
    {
        try {
            $user = auth()->user();

            if (! $user) {
                return false;
            }

            if ((bool) ($user->is_system_admin ?? false)) {
                return true;
            }

            $companyId = static::currentCompanyId();

            $modelTypes = array_values(array_unique([
                get_class($user),
                \App\Models\User::class,
            ]));

            $query = \Illuminate\Support\Facades\DB::table('model_has_roles as mhr')
                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                ->join('role_has_permissions as rhp', 'rhp.role_id', '=', 'r.id')
                ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
                ->whereIn('mhr.model_type', $modelTypes)
                ->where('mhr.model_id', $user->getKey())
                ->where('p.name', $permissionName)
                ->where('p.guard_name', 'web');

            if ($companyId !== null && \Illuminate\Support\Facades\Schema::hasColumn('model_has_roles', 'company_id')) {
                $query->where(function ($subQuery) use ($companyId): void {
                    $subQuery->where('mhr.company_id', $companyId)
                        ->orWhereNull('mhr.company_id');
                });
            }

            if ($companyId !== null && \Illuminate\Support\Facades\Schema::hasColumn('roles', 'company_id')) {
                $query->where(function ($subQuery) use ($companyId): void {
                    $subQuery->where('r.company_id', $companyId)
                        ->orWhereNull('r.company_id');
                });
            }

            if ($query->exists()) {
                return true;
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('model_has_permissions')) {
                $directQuery = \Illuminate\Support\Facades\DB::table('model_has_permissions as mhp')
                    ->join('permissions as p', 'p.id', '=', 'mhp.permission_id')
                    ->whereIn('mhp.model_type', $modelTypes)
                    ->where('mhp.model_id', $user->getKey())
                    ->where('p.name', $permissionName)
                    ->where('p.guard_name', 'web');

                if ($companyId !== null && \Illuminate\Support\Facades\Schema::hasColumn('model_has_permissions', 'company_id')) {
                    $directQuery->where(function ($subQuery) use ($companyId): void {
                        $subQuery->where('mhp.company_id', $companyId)
                            ->orWhereNull('mhp.company_id');
                    });
                }

                if ($directQuery->exists()) {
                    return true;
                }
            }

            if ($companyId === null && method_exists($user, 'can')) {
                return (bool) $user->can($permissionName);
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    protected static function currentCompanyId(): ?int
    {
        try {
            if (class_exists(\Filament\Facades\Filament::class)) {
                $tenant = \Filament\Facades\Filament::getTenant();

                if (is_object($tenant) && isset($tenant->id)) {
                    return (int) $tenant->id;
                }

                if (is_numeric($tenant)) {
                    return (int) $tenant;
                }
            }
        } catch (\Throwable) {
            // Continuar con ruta/segmento.
        }

        try {
            foreach (['tenant', 'company', 'company_id'] as $parameter) {
                $value = request()->route($parameter);

                if (is_object($value) && isset($value->id)) {
                    return (int) $value->id;
                }

                if (is_numeric($value)) {
                    return (int) $value;
                }
            }

            $segment = request()->segment(2);

            if (is_numeric($segment)) {
                return (int) $segment;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

}
