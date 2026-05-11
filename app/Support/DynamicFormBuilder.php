<?php

namespace App\Support;

use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;

class DynamicFormBuilder
{
    /**
     * Construye un formulario vertical (sin Wizard): secciones una debajo de otra.
     *
     * @return array<Component>
     */
    public static function form(array $schema): array
    {
        $steps = $schema['steps'] ?? [];

        if (count($steps) === 0) {
            return [
                Forms\Components\Placeholder::make('sin_pasos')
                    ->content('El formulario no tiene secciones configuradas.'),
            ];
        }

        return array_map(function ($step) use ($schema) {
            return Forms\Components\Section::make($step['title'] ?? 'Sección')
                ->columns(1)
                ->schema(self::fields($step['fields'] ?? [], $schema))
                ->collapsed(false);
        }, $steps);
    }

    /**
     * Extrae rutas de fields tipo "file" del schema.
     * @return array<int, string>
     */
    public static function extractFileFieldPaths(array $schema): array
    {
        $paths = [];

        $steps = $schema['steps'] ?? [];
        foreach ($steps as $step) {
            $fields = $step['fields'] ?? [];

            foreach ($fields as $f) {
                $type = $f['type'] ?? null;

                if ($type === 'file') {
                    $paths[] = $f['name']; // ej: "comprobante"
                }

                if ($type === 'items') {
                    $repeaterName = $f['name'] ?? 'items';
                    $itemFields = $f['item_fields'] ?? [];

                    foreach ($itemFields as $it) {
                        if (($it['type'] ?? null) === 'file') {
                            // items.*.foto
                            $paths[] = $repeaterName . '.*.' . ($it['name'] ?? 'file');
                        }
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * @return array<Component>
     */
    private static function fields(array $fields, array $rootSchema): array
    {
        $out = [];

        foreach ($fields as $f) {
            $type = $f['type'] ?? 'text';

            if ($type === 'items') {
                $maxGlobal = $rootSchema['max_items'] ?? null;
                $maxLocal = $f['max_items'] ?? null;

                $minLocal = $f['min_items'] ?? null; // opcional si lo agregas al schema

                $repeater = Forms\Components\Repeater::make($f['name'])
                    ->label($f['label'] ?? 'Artículos')
                    ->schema(self::itemFields($f['item_fields'] ?? []))
                    ->columns(1);

                // required => minItems 1 (como ya lo tenías)
                if (($f['required'] ?? false) === true) {
                    $repeater->minItems(1);
                } elseif (is_int($minLocal)) {
                    $repeater->minItems($minLocal);
                } else {
                    $repeater->minItems(0);
                }

                // maxItems: prioriza max local si existe, si no usa global
                $max = is_int($maxLocal) ? $maxLocal : (is_int($maxGlobal) ? $maxGlobal : null);
                if (is_int($max)) {
                    $repeater->maxItems($max);
                }

                $out[] = $repeater;
                continue;
            }

            $out[] = self::field($f);
        }

        return $out;
    }

    /**
     * @return array<Component>
     */
    private static function itemFields(array $itemFields): array
    {
        return array_map(fn ($f) => self::field($f), $itemFields);
    }

    private static function field(array $f): Component
    {
        $name = $f['name'] ?? 'campo';
        $label = $f['label'] ?? $name;
        $required = (bool) ($f['required'] ?? false);

        return match ($f['type'] ?? 'text') {
            'date' => Forms\Components\DatePicker::make($name)
                ->label($label)
                ->required($required),

            'email' => Forms\Components\TextInput::make($name)
                ->label($label)
                ->email()
                ->required($required),

            // ✅ decimal-friendly
            'number' => Forms\Components\TextInput::make($name)
                ->label($label)
                ->numeric()
                ->inputMode('decimal')
                ->step($f['step'] ?? 'any') // permite decimales (ej 0.001)
                ->minValue($f['min'] ?? null)
                ->rule('numeric')
                ->required($required),

            'textarea' => Forms\Components\Textarea::make($name)
                ->label($label)
                ->rows(4)
                ->required($required),

            // ✅ soporta options como lista ['A','B'] o asociativo ['a'=>'A']
            'select' => Forms\Components\Select::make($name)
                ->label($label)
                ->options(self::normalizeSelectOptions($f['options'] ?? []))
                ->searchable()
                ->required($required),

            /**
             * ✅ FileUpload single: state debe quedar STRING|null.
             * Normalizamos array->string en hydrate y dehydrate.
             */
            'file' => FileUpload::make($name)
                ->label($label)
                ->disk($f['disk'] ?? 'public')
                ->directory($f['dir'] ?? 'uploads')
                ->visibility(($f['disk'] ?? 'public') === 'public' ? 'public' : 'private')
                ->maxSize((int) ($f['max_kb'] ?? 10240))
                ->multiple(false)
                ->downloadable()
                ->openable()

                // BD/estado -> UI
                ->afterStateHydrated(function (FileUpload $component, $state): void {
                    if ($state === '' || $state === [] || $state === null) {
                        $component->state(null);
                        return;
                    }

                    if (is_array($state) && array_is_list($state)) {
                        $component->state($state[0] ?? null);
                        return;
                    }
                })

                // UI -> BD
                ->dehydrateStateUsing(function ($state) {
                    if ($state === '' || $state === [] || $state === null) {
                        return null;
                    }

                    if (is_array($state) && array_is_list($state)) {
                        return $state[0] ?? null;
                    }

                    return $state ?: null;
                })

                ->required($required),

            default => Forms\Components\TextInput::make($name)
                ->label($label)
                ->required($required),
        };
    }

    /**
     * @return array<string, string>
     */
    private static function normalizeSelectOptions(array $options): array
    {
        // caso 1: ya viene asociativo ['a'=>'A']
        $isAssoc = array_keys($options) !== range(0, count($options) - 1);
        if ($isAssoc) {
            // asegura strings
            $out = [];
            foreach ($options as $k => $v) {
                $out[(string) $k] = (string) $v;
            }
            return $out;
        }

        // caso 2: lista ['A','B'] => ['A'=>'A','B'=>'B']
        $out = [];
        foreach ($options as $v) {
            $out[(string) $v] = (string) $v;
        }
        return $out;
    }
}
