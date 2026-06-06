<?php

namespace App\Support\Navigation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BexiaMenuSnapshotService
{
    public const DEFAULT_KEY = 'menu_lateral_base';

    public static function save(string $key = self::DEFAULT_KEY, ?string $label = null): array
    {
        if (! static::tablesReady()) {
            return static::error('No existen las tablas necesarias para guardar el estado del menú.');
        }

        $payload = static::currentPayload();

        $data = [
            'label' => $label ?: 'Estado base menú lateral',
            'payload' => static::json($payload),
            'saved_by_user_id' => auth()->id(),
            'saved_at' => now(),
            'updated_at' => now(),
        ];

        $existing = DB::table('bexia_menu_snapshots')
            ->where('key', $key)
            ->first();

        if ($existing) {
            DB::table('bexia_menu_snapshots')
                ->where('id', $existing->id)
                ->update($data);
        } else {
            $data['key'] = $key;
            $data['created_at'] = now();

            DB::table('bexia_menu_snapshots')->insert($data);
        }

        return [
            'ok' => true,
            'mode' => 'save',
            'key' => $key,
            'groups' => count($payload['groups'] ?? []),
            'items' => count($payload['items'] ?? []),
        ];
    }

    public static function restore(string $key = self::DEFAULT_KEY): array
    {
        if (! static::tablesReady()) {
            return static::error('No existen las tablas necesarias para restaurar el menú.');
        }

        $snapshot = DB::table('bexia_menu_snapshots')->where('key', $key)->first();

        if (! $snapshot) {
            return static::error('No existe un estado base guardado todavía.');
        }

        $payload = static::decodePayload($snapshot->payload);

        if (! is_array($payload) || ! isset($payload['groups'], $payload['items'])) {
            return static::error('El estado base guardado no tiene un formato válido.');
        }

        $groupChanges = 0;
        $itemChanges = 0;

        DB::transaction(function () use ($payload, &$groupChanges, &$itemChanges): void {
            $snapshotGroupKeys = collect($payload['groups'])->pluck('key')->filter()->values()->all();
            $snapshotItemKeys = collect($payload['items'])->pluck('key')->filter()->values()->all();

            if (count($snapshotGroupKeys) > 0) {
                DB::table('bexia_menu_groups')
                    ->whereNotIn('key', $snapshotGroupKeys)
                    ->update([
                        'is_visible' => false,
                        'updated_at' => now(),
                    ]);
            }

            if (count($snapshotItemKeys) > 0) {
                DB::table('bexia_menu_items')
                    ->whereNotIn('key', $snapshotItemKeys)
                    ->update([
                        'is_visible' => false,
                        'updated_at' => now(),
                    ]);
            }

            $groupIdByKey = [];

            foreach ($payload['groups'] as $group) {
                $key = (string) ($group['key'] ?? '');

                if ($key === '') {
                    continue;
                }

                $existing = DB::table('bexia_menu_groups')->where('key', $key)->first();

                $data = [
                    'key' => $key,
                    'label' => $group['label'] ?? $group['default_label'] ?? $key,
                    'default_label' => $group['default_label'] ?? $group['label'] ?? $key,
                    'sort' => (int) ($group['sort'] ?? 999),
                    'is_visible' => (bool) ($group['is_visible'] ?? true),
                    'is_system' => (bool) ($group['is_system'] ?? true),
                    'meta' => $group['meta'] ?? null,
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('bexia_menu_groups')->where('id', $existing->id)->update($data);
                    $groupId = (int) $existing->id;
                } else {
                    $data['created_at'] = now();
                    $groupId = (int) DB::table('bexia_menu_groups')->insertGetId($data);
                }

                $groupIdByKey[$key] = $groupId;
                $groupChanges++;
            }

            foreach ($payload['items'] as $item) {
                $key = (string) ($item['key'] ?? '');
                $groupKey = (string) ($item['group_key'] ?? '');

                if ($key === '' || $groupKey === '' || ! isset($groupIdByKey[$groupKey])) {
                    continue;
                }

                $existing = DB::table('bexia_menu_items')->where('key', $key)->first();

                $data = [
                    'group_id' => $groupIdByKey[$groupKey],
                    'key' => $key,
                    'label' => $item['label'] ?? $item['default_label'] ?? $key,
                    'default_label' => $item['default_label'] ?? $item['label'] ?? $key,
                    'sort' => (int) ($item['sort'] ?? 999),
                    'is_visible' => (bool) ($item['is_visible'] ?? true),
                    'is_system' => (bool) ($item['is_system'] ?? true),
                    'source' => $item['source'] ?? null,
                    'file_path' => $item['file_path'] ?? null,
                    'class_name' => $item['class_name'] ?? null,
                    'route_name' => $item['route_name'] ?? null,
                    'permission_name' => $item['permission_name'] ?? null,
                    'meta' => $item['meta'] ?? null,
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('bexia_menu_items')->where('id', $existing->id)->update($data);
                } else {
                    $data['created_at'] = now();
                    DB::table('bexia_menu_items')->insert($data);
                }

                $itemChanges++;
            }
        });

        return [
            'ok' => true,
            'mode' => 'restore',
            'key' => $key,
            'groups' => $groupChanges,
            'items' => $itemChanges,
            'saved_at' => $snapshot->saved_at,
        ];
    }

    public static function currentPayload(): array
    {
        $groups = DB::table('bexia_menu_groups')
            ->orderBy('sort')
            ->orderBy('label')
            ->get()
            ->map(fn ($row): array => [
                'key' => $row->key,
                'label' => $row->label,
                'default_label' => $row->default_label,
                'sort' => $row->sort,
                'is_visible' => (bool) $row->is_visible,
                'is_system' => (bool) $row->is_system,
                'meta' => $row->meta,
            ])
            ->values()
            ->all();

        $items = DB::table('bexia_menu_items as i')
            ->leftJoin('bexia_menu_groups as g', 'g.id', '=', 'i.group_id')
            ->select([
                'i.key',
                'i.label',
                'i.default_label',
                'i.sort',
                'i.is_visible',
                'i.is_system',
                'i.source',
                'i.file_path',
                'i.class_name',
                'i.route_name',
                'i.permission_name',
                'i.meta',
                'g.key as group_key',
            ])
            ->orderBy('g.sort')
            ->orderBy('i.sort')
            ->orderBy('i.label')
            ->get()
            ->map(fn ($row): array => [
                'group_key' => $row->group_key,
                'key' => $row->key,
                'label' => $row->label,
                'default_label' => $row->default_label,
                'sort' => $row->sort,
                'is_visible' => (bool) $row->is_visible,
                'is_system' => (bool) $row->is_system,
                'source' => $row->source,
                'file_path' => $row->file_path,
                'class_name' => $row->class_name,
                'route_name' => $row->route_name,
                'permission_name' => $row->permission_name,
                'meta' => $row->meta,
            ])
            ->values()
            ->all();

        return [
            'version' => 'v5.72.2f2',
            'saved_at' => now()->toDateTimeString(),
            'groups' => $groups,
            'items' => $items,
        ];
    }

    protected static function tablesReady(): bool
    {
        return Schema::hasTable('bexia_menu_groups')
            && Schema::hasTable('bexia_menu_items')
            && Schema::hasTable('bexia_menu_snapshots');
    }

    protected static function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        try {
            $decoded = json_decode((string) $payload, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }

    protected static function json(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected static function error(string $message): array
    {
        return [
            'ok' => false,
            'error' => $message,
        ];
    }
}
