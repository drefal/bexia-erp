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
            $label = BexiaMenuGroup::query()
                ->where('key', $key)
                ->where('is_visible', true)
                ->value('label');

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

            if (! $item->is_visible) {
                return false;
            }

            $group = $item->group;

            if ($group && ! $group->is_visible) {
                return false;
            }

            return true;
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

            if ($item && $item->group && $item->group->is_visible && filled($item->group->label)) {
                return (string) $item->group->label;
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
                ->where('is_visible', true)
                ->orderBy('sort')
                ->orderBy('label')
                ->get();

            if ($groups->isEmpty()) {
                return collect($fallbackGroups)
                    ->map(fn (string $label) => NavigationGroup::make($label))
                    ->values()
                    ->all();
            }

            return $groups
                ->map(fn (BexiaMenuGroup $group) => NavigationGroup::make((string) $group->label))
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
