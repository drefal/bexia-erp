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
        return self::bexiaPerf7iTablesReady();
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
        return self::bexiaPerf7iItemLabel(...func_get_args());
    }

    public static function itemSort(string $key, int $fallback): int
    {
        return self::bexiaPerf7iItemSort(...func_get_args());
    }

    public static function itemVisible(string $key, bool $fallback = true): bool
    {
        return self::bexiaPerf7iItemVisible(...func_get_args());
    }

    public static function itemGroupLabel(string $itemKey, string $fallbackGroupKey, string $fallbackGroupLabel): string
    {
        return self::bexiaPerf7iItemGroupLabel(...func_get_args());
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
        return self::bexiaPerf7iShouldRegister(...func_get_args());
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
        // BEXIA_V582_PERF7E_MENU_PERMISSION_CACHE
        try {
            // BEXIA_V582_PERF7G_MENU_SYSTEM_ADMIN_FIRST
            $user = auth()->user();

            if (! $user) {
                return false;
            }

            if ((bool) ($user->is_system_admin ?? false) || (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin())) {
                return true;
            }

            $permissionName = trim((string) $permissionName);

            if ($permissionName === '') {
                return true;
            }

            $cached = \App\Support\Security\BexiaPermissionRequestCache::allows(auth()->user(), $permissionName);

            if ($cached !== null) {
                return $cached;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return false;
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



    // BEXIA_V582_PERF7I_MENU_RUNTIME_REQUEST_CACHE_START
    private static ?bool $bexiaPerf7iTablesReady = null;

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $bexiaPerf7iItemsByKey = null;

    private static function bexiaPerf7iTablesReady(): bool
    {
        if (self::$bexiaPerf7iTablesReady !== null) {
            return self::$bexiaPerf7iTablesReady;
        }

        try {
            if (class_exists(\App\Support\Security\BexiaPermissionRequestCache::class)) {
                self::$bexiaPerf7iTablesReady =
                    \App\Support\Security\BexiaPermissionRequestCache::tableExists('bexia_menu_groups')
                    && \App\Support\Security\BexiaPermissionRequestCache::tableExists('bexia_menu_items');

                return self::$bexiaPerf7iTablesReady;
            }

            self::$bexiaPerf7iTablesReady =
                \Illuminate\Support\Facades\Schema::hasTable('bexia_menu_groups')
                && \Illuminate\Support\Facades\Schema::hasTable('bexia_menu_items');

            return self::$bexiaPerf7iTablesReady;
        } catch (\Throwable) {
            return self::$bexiaPerf7iTablesReady = false;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function bexiaPerf7iItemsByKey(): array
    {
        if (self::$bexiaPerf7iItemsByKey !== null) {
            return self::$bexiaPerf7iItemsByKey;
        }

        self::$bexiaPerf7iItemsByKey = [];

        if (! self::bexiaPerf7iTablesReady()) {
            return self::$bexiaPerf7iItemsByKey;
        }

        try {
            $cacheClass = \App\Support\Security\BexiaPermissionRequestCache::class;

            $itemHasLabel = class_exists($cacheClass)
                ? $cacheClass::columnExists('bexia_menu_items', 'label')
                : \Illuminate\Support\Facades\Schema::hasColumn('bexia_menu_items', 'label');

            $itemHasSort = class_exists($cacheClass)
                ? $cacheClass::columnExists('bexia_menu_items', 'sort')
                : \Illuminate\Support\Facades\Schema::hasColumn('bexia_menu_items', 'sort');

            $itemHasVisible = class_exists($cacheClass)
                ? $cacheClass::columnExists('bexia_menu_items', 'is_visible')
                : \Illuminate\Support\Facades\Schema::hasColumn('bexia_menu_items', 'is_visible');

            $itemHasPermission = class_exists($cacheClass)
                ? $cacheClass::columnExists('bexia_menu_items', 'permission_name')
                : \Illuminate\Support\Facades\Schema::hasColumn('bexia_menu_items', 'permission_name');

            $groupHasLabel = class_exists($cacheClass)
                ? $cacheClass::columnExists('bexia_menu_groups', 'label')
                : \Illuminate\Support\Facades\Schema::hasColumn('bexia_menu_groups', 'label');

            $groupHasName = class_exists($cacheClass)
                ? $cacheClass::columnExists('bexia_menu_groups', 'name')
                : \Illuminate\Support\Facades\Schema::hasColumn('bexia_menu_groups', 'name');

            $groupHasVisible = class_exists($cacheClass)
                ? $cacheClass::columnExists('bexia_menu_groups', 'is_visible')
                : \Illuminate\Support\Facades\Schema::hasColumn('bexia_menu_groups', 'is_visible');

            $select = [
                'i.key as item_key',
            ];

            $select[] = $itemHasLabel
                ? \Illuminate\Support\Facades\DB::raw('i.label as item_label')
                : \Illuminate\Support\Facades\DB::raw('NULL as item_label');

            $select[] = $itemHasSort
                ? \Illuminate\Support\Facades\DB::raw('i.sort as item_sort')
                : \Illuminate\Support\Facades\DB::raw('NULL as item_sort');

            $select[] = $itemHasVisible
                ? \Illuminate\Support\Facades\DB::raw('i.is_visible as item_visible')
                : \Illuminate\Support\Facades\DB::raw('true as item_visible');

            $select[] = $itemHasPermission
                ? \Illuminate\Support\Facades\DB::raw('i.permission_name as permission_name')
                : \Illuminate\Support\Facades\DB::raw('NULL as permission_name');

            if ($groupHasLabel) {
                $select[] = \Illuminate\Support\Facades\DB::raw('g.label as group_label');
            } elseif ($groupHasName) {
                $select[] = \Illuminate\Support\Facades\DB::raw('g.name as group_label');
            } else {
                $select[] = \Illuminate\Support\Facades\DB::raw('NULL as group_label');
            }

            $select[] = $groupHasVisible
                ? \Illuminate\Support\Facades\DB::raw('g.is_visible as group_visible')
                : \Illuminate\Support\Facades\DB::raw('true as group_visible');

            $rows = \Illuminate\Support\Facades\DB::table('bexia_menu_items as i')
                ->leftJoin('bexia_menu_groups as g', 'g.id', '=', 'i.group_id')
                ->select($select)
                ->get();

            foreach ($rows as $row) {
                $key = trim((string) ($row->item_key ?? ''));

                if ($key === '') {
                    continue;
                }

                self::$bexiaPerf7iItemsByKey[$key] = [
                    'key' => $key,
                    'label' => $row->item_label ?? null,
                    'sort' => $row->item_sort ?? null,
                    'item_visible' => (bool) ($row->item_visible ?? true),
                    'group_label' => $row->group_label ?? null,
                    'group_visible' => (bool) ($row->group_visible ?? true),
                    'permission_name' => $row->permission_name ?? null,
                ];
            }
        } catch (\Throwable $e) {
            report($e);
            self::$bexiaPerf7iItemsByKey = [];
        }

        return self::$bexiaPerf7iItemsByKey;
    }

    private static function bexiaPerf7iMenuRow(mixed $key): ?array
    {
        $key = trim((string) $key);

        if ($key === '') {
            return null;
        }

        $items = self::bexiaPerf7iItemsByKey();

        return $items[$key] ?? null;
    }

    private static function bexiaPerf7iFallbackLabel(mixed $key): string
    {
        $text = trim((string) $key);

        if ($text === '') {
            return '';
        }

        $text = str_replace(['.', '_', '-'], ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text) ?: $text;

        return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
    }

    private static function bexiaPerf7iItemLabel(mixed $key, mixed $fallback = null): string
    {
        $row = self::bexiaPerf7iMenuRow($key);
        $label = trim((string) ($row['label'] ?? ''));

        if ($label !== '') {
            return $label;
        }

        if (is_string($fallback) && trim($fallback) !== '') {
            return $fallback;
        }

        return self::bexiaPerf7iFallbackLabel($key);
    }

    private static function bexiaPerf7iItemSort(mixed $key, mixed $fallback = null): int
    {
        $row = self::bexiaPerf7iMenuRow($key);

        if ($row && is_numeric($row['sort'] ?? null)) {
            return (int) $row['sort'];
        }

        return is_numeric($fallback) ? (int) $fallback : 0;
    }

    private static function bexiaPerf7iItemVisible(mixed $key, mixed $default = true): bool
    {
        $row = self::bexiaPerf7iMenuRow($key);

        if (! $row) {
            return (bool) $default;
        }

        return (bool) ($row['item_visible'] ?? true) && (bool) ($row['group_visible'] ?? true);
    }

    private static function bexiaPerf7iItemGroupLabel(mixed $key, mixed $fallback = null): string
    {
        $row = self::bexiaPerf7iMenuRow($key);
        $label = trim((string) ($row['group_label'] ?? ''));

        if ($label !== '') {
            return $label;
        }

        return is_string($fallback) ? $fallback : '';
    }

    private static function bexiaPerf7iShouldRegister(mixed $key, mixed ...$args): bool
    {
        $row = self::bexiaPerf7iMenuRow($key);

        if (! $row) {
            return true;
        }

        if (! ((bool) ($row['item_visible'] ?? true) && (bool) ($row['group_visible'] ?? true))) {
            return false;
        }

        $permissionName = trim((string) ($row['permission_name'] ?? ''));

        if ($permissionName === '') {
            return true;
        }

        try {
            $cached = \App\Support\Security\BexiaPermissionRequestCache::allows(auth()->user(), $permissionName);

            if ($cached !== null) {
                return $cached;
            }

            return (bool) (auth()->user()?->can($permissionName) ?? false);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
    // BEXIA_V582_PERF7I_MENU_RUNTIME_REQUEST_CACHE_END

}
