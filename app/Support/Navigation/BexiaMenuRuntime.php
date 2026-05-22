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
}
