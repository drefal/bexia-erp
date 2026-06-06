<?php

namespace App\Support\Navigation;

use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class BexiaMenuSynchronizer
{
    public static function sync(): array
    {
        if (! Schema::hasTable('bexia_menu_groups') || ! Schema::hasTable('bexia_menu_items')) {
            return [
                'ok' => false,
                'error' => 'No existen tablas bexia_menu_groups / bexia_menu_items.',
                'detected' => 0,
                'group_changes' => 0,
                'item_changes' => 0,
            ];
        }

        $detected = static::detectNavigationFiles();

        $groupChanges = 0;
        $itemChanges = 0;
        $insertedGroups = [];
        $insertedItems = [];
        $updatedItems = [];

        foreach ($detected as $item) {
            $groupKey = $item['group_key'];
            $groupLabel = static::canonicalGroupLabel($groupKey, $item['group_label']);
            $groupSort = static::desiredGroupSort($groupKey);

            $group = DB::table('bexia_menu_groups')
                ->where('key', $groupKey)
                ->first();

            if (! $group) {
                $groupId = DB::table('bexia_menu_groups')->insertGetId([
                    'key' => $groupKey,
                    'label' => $groupLabel,
                    'default_label' => $groupLabel,
                    'sort' => $groupSort,
                    'is_visible' => true,
                    'is_system' => true,
                    'meta' => json_encode([
                        'version' => 'v5.72.2d',
                        'source' => 'ui_sync_detected_filament_navigation',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $groupChanges++;
                $insertedGroups[] = $groupLabel;
            } else {
                $groupId = (int) $group->id;

                $updates = [];

                if (blank($group->label)) {
                    $updates['label'] = $groupLabel;
                }

                if (blank($group->default_label)) {
                    $updates['default_label'] = $groupLabel;
                }

                if ($group->sort === null) {
                    $updates['sort'] = $groupSort;
                }

                if (! empty($updates)) {
                    $updates['updated_at'] = now();

                    DB::table('bexia_menu_groups')
                        ->where('id', $groupId)
                        ->update($updates);

                    $groupChanges++;
                }
            }

            $existing = DB::table('bexia_menu_items')
                ->where('key', $item['key'])
                ->first();

            $payload = [
                'group_id' => $groupId,
                'default_label' => $item['label'],
                'is_system' => true,
                'source' => 'filament_file',
                'file_path' => $item['file_path'],
                'class_name' => $item['class_name'],
                'route_name' => null,
                'meta' => json_encode([
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'group' => $groupLabel,
                    'sort' => $item['sort'],
                    'class' => $item['class_name'],
                    'file' => $item['file_path'],
                    'source' => 'filament_file',
                    'version' => 'v5.72.2d',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ];

            if ($existing) {
                $updates = $payload;

                if (blank($existing->label)) {
                    $updates['label'] = $item['label'];
                }

                if ($existing->sort === null) {
                    $updates['sort'] = $item['sort'];
                }

                if ($existing->is_visible === null) {
                    $updates['is_visible'] = true;
                }

                $needsUpdate =
                    (int) $existing->group_id !== $groupId
                    || (string) $existing->file_path !== $item['file_path']
                    || (string) $existing->class_name !== $item['class_name']
                    || (string) $existing->default_label !== $item['label']
                    || blank($existing->label)
                    || $existing->sort === null
                    || $existing->is_visible === null;

                if ($needsUpdate) {
                    DB::table('bexia_menu_items')
                        ->where('id', $existing->id)
                        ->update($updates);

                    $itemChanges++;
                    $updatedItems[] = $item['label'];
                }

                continue;
            }

            $payload['key'] = $item['key'];
            $payload['label'] = $item['label'];
            $payload['sort'] = $item['sort'];
            $payload['is_visible'] = true;
            $payload['permission_name'] = null;
            $payload['created_at'] = now();

            DB::table('bexia_menu_items')->insert($payload);

            $itemChanges++;
            $insertedItems[] = $item['label'];
        }

        return [
            'ok' => true,
            'detected' => count($detected),
            'group_changes' => $groupChanges,
            'item_changes' => $itemChanges,
            'inserted_groups' => array_values(array_unique($insertedGroups)),
            'inserted_items' => array_values(array_unique($insertedItems)),
            'updated_items' => array_values(array_unique($updatedItems)),
        ];
    }

    public static function detectNavigationFiles(): array
    {
        $basePath = base_path('app/Filament');

        if (! is_dir($basePath)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS)
        );

        $detected = [];

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
                continue;
            }

            $fullPath = $fileInfo->getPathname();
            $relative = str_replace(base_path() . '/', '', $fullPath);

            if (str_starts_with($relative, 'app/Filament/Admin/')) {
                continue;
            }

            if (str_contains($relative, '/Resources/') && str_contains($relative, '/Pages/')) {
                continue;
            }

            if (! preg_match('#^app/Filament/(Pages|Resources|Clusters)/#', $relative)) {
                continue;
            }

            $code = (string) file_get_contents($fullPath);

            $group = static::extractStaticString($code, 'navigationGroup');

            if (! $group) {
                continue;
            }

            $key = static::keyFromFile($relative);

            if (! $key) {
                continue;
            }

            $label = static::extractStaticString($code, 'navigationLabel')
                ?: static::extractStaticString($code, 'title')
                ?: static::classNameFromFile($relative);

            $sort = static::extractStaticInt($code, 'navigationSort') ?? 999;

            $detected[] = [
                'key' => $key,
                'group_label' => $group,
                'group_key' => static::normalizedGroupKey($group),
                'label' => $label,
                'sort' => $sort,
                'file_path' => $relative,
                'class_name' => static::classNameFromFile($relative),
            ];
        }

        usort($detected, fn (array $a, array $b): int => [
            static::desiredGroupSort($a['group_key']),
            $a['sort'],
            $a['label'],
        ] <=> [
            static::desiredGroupSort($b['group_key']),
            $b['sort'],
            $b['label'],
        ]);

        return $detected;
    }

    protected static function extractStaticString(string $code, string $property): ?string
    {
        $patterns = [
            '/protected\s+static\s+\??string\s+\$' . preg_quote($property, '/') . '\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/',
            '/public\s+static\s+\??string\s+\$' . preg_quote($property, '/') . '\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $code, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    protected static function extractStaticInt(string $code, string $property): ?int
    {
        $patterns = [
            '/protected\s+static\s+\??int\s+\$' . preg_quote($property, '/') . '\s*=\s*(\d+)\s*;/',
            '/public\s+static\s+\??int\s+\$' . preg_quote($property, '/') . '\s*=\s*(\d+)\s*;/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $code, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    protected static function classNameFromFile(string $file): string
    {
        return pathinfo($file, PATHINFO_FILENAME);
    }

    protected static function keyFromFile(string $file): ?string
    {
        $class = strtolower(static::classNameFromFile($file));

        if (str_starts_with($file, 'app/Filament/Pages/')) {
            return 'pages.' . $class;
        }

        if (str_starts_with($file, 'app/Filament/Resources/') && ! str_contains($file, '/Pages/')) {
            return 'resources.' . $class;
        }

        if (str_starts_with($file, 'app/Filament/Clusters/')) {
            return 'clusters.' . $class;
        }

        return null;
    }

    protected static function normalizedGroupKey(string $label): string
    {
        $map = [
            'Mi portal' => 'mi_portal',
            'Inicio' => 'inicio',
            'Contactos' => 'contactos',
            'RRHH' => 'recursos_humanos',
            'Recursos Humanos' => 'recursos_humanos',
            'Nómina' => 'nomina',
            'Nomina' => 'nomina',
            'Productos' => 'productos',
            'Compras' => 'compras',
            'Cuentas por pagar' => 'cuentas_por_pagar',
            'Ventas' => 'ventas',
            'Cuentas por cobrar' => 'cuentas_por_cobrar',
            'Punto de Venta' => 'punto_de_venta',
            'Inventario' => 'inventario',
            'Salidas' => 'salidas',
            'Tesorería' => 'tesorer_a',
            'Tesoreria' => 'tesorer_a',
            'Facturación' => 'facturaci_n',
            'Facturacion' => 'facturaci_n',
            'Contabilidad' => 'contabilidad',
            'Catálogos' => 'cat_logos',
            'Catalogos' => 'cat_logos',
            'Configuración empresa' => 'configuraci_n_empresa',
            'Configuracion empresa' => 'configuraci_n_empresa',
            'Configuración Bexia' => 'configuraci_n_bexia',
            'Configuracion Bexia' => 'configuraci_n_bexia',
            'Seguridad' => 'seguridad',
        ];

        if (isset($map[$label])) {
            return $map[$label];
        }

        return Str::of($label)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    protected static function desiredGroupSort(string $key): int
    {
        return [
            'mi_portal' => 5,
            'inicio' => 10,
            'contactos' => 20,
            'recursos_humanos' => 30,
            'nomina' => 40,
            'productos' => 50,
            'compras' => 60,
            'cuentas_por_pagar' => 70,
            'ventas' => 80,
            'cuentas_por_cobrar' => 90,
            'punto_de_venta' => 100,
            'inventario' => 110,
            'salidas' => 120,
            'tesorer_a' => 130,
            'facturaci_n' => 140,
            'contabilidad' => 150,
            'cat_logos' => 160,
            'configuraci_n_empresa' => 170,
            'configuraci_n_bexia' => 180,
            'seguridad' => 190,
            'sin_grupo' => 9990,
        ][$key] ?? 500;
    }

    protected static function canonicalGroupLabel(string $key, string $fallback): string
    {
        return [
            'mi_portal' => 'Mi portal',
            'inicio' => 'Inicio',
            'contactos' => 'Contactos',
            'recursos_humanos' => 'RRHH',
            'nomina' => 'Nómina',
            'productos' => 'Productos',
            'compras' => 'Compras',
            'cuentas_por_pagar' => 'Cuentas por pagar',
            'ventas' => 'Ventas',
            'cuentas_por_cobrar' => 'Cuentas por cobrar',
            'punto_de_venta' => 'Punto de Venta',
            'inventario' => 'Inventario',
            'salidas' => 'Salidas',
            'tesorer_a' => 'Tesorería',
            'facturaci_n' => 'Facturación',
            'contabilidad' => 'Contabilidad',
            'cat_logos' => 'Catálogos',
            'configuraci_n_empresa' => 'Configuración empresa',
            'configuraci_n_bexia' => 'Configuración Bexia',
            'seguridad' => 'Seguridad',
            'sin_grupo' => 'SIN_GRUPO',
        ][$key] ?? $fallback;
    }
}
