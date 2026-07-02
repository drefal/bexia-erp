<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomsOfficeResource\Pages;
use App\Models\CustomsOffice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CustomsOfficeResource extends Resource
{
    /**
     * BEXIA_CUSTOMS_OFFICE_RESOURCE_RESPONSIVE_V5_79_100C
     *
     * Visual-only responsive classes for CustomsOfficeResource.
     */
    protected static ?string $model = CustomsOffice::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Catálogos';

    protected static ?string $navigationLabel = 'Aduanas';

    protected static ?string $modelLabel = 'Aduana';

    protected static ?string $pluralModelLabel = 'Aduanas';

    protected static ?int $navigationSort = 90;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.customsofficeresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        return auth()->check()
            && auth()->user()->can('settings.access');
    }

    public static function canViewAny(): bool
    {
        return auth()->check()
            && auth()->user()->can('settings.access');
    }

    public static function canCreate(): bool
    {
        return auth()->check()
            && auth()->user()->can('settings.access');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->check()
            && auth()->user()->can('settings.access');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->check()
            && auth()->user()->can('settings.access');
    }

    public static function canDeleteAny(): bool
    {
        return auth()->check()
            && auth()->user()->can('settings.access');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Aduana')
                    ->extraAttributes([
                        'class' => 'bexia-coff-section bexia-coff-customs-section',
                    ])
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->extraAttributes([
                                'class' => 'bexia-coff-field bexia-coff-code-field bexia-coff-compact-field',
                            ])
                            ->label('Código')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('name')
                            ->extraAttributes([
                                'class' => 'bexia-coff-field bexia-coff-name-field bexia-coff-main-field',
                            ])
                            ->label('Nombre')
                            ->required()
                            ->maxLength(160)
                            ->helperText('Ej. MANZANILLO'),

                        Forms\Components\TextInput::make('display_name')
                            ->extraAttributes([
                                'class' => 'bexia-coff-field bexia-coff-display-name-field bexia-coff-wide-field',
                            ])
                            ->label('Nombre para mostrar')
                            ->maxLength(220)
                            ->helperText('Opcional. Si se deja vacío se usa el nombre.'),

                        Forms\Components\Toggle::make('is_active')
                            ->extraAttributes([
                                'class' => 'bexia-coff-field bexia-coff-active-field bexia-coff-bool-field',
                            ])
                            ->label('Activa')
                            ->default(true),

                        Forms\Components\Textarea::make('notes')
                            ->extraAttributes([
                                'class' => 'bexia-coff-field bexia-coff-notes-field bexia-coff-wide-field bexia-coff-textarea-field',
                            ])
                            ->label('Notas')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-coff-header bexia-coff-col-code',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-coff-cell bexia-coff-col-code bexia-coff-col-compact',
                    ])
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-coff-header bexia-coff-col-name',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-coff-cell bexia-coff-col-name bexia-coff-col-main',
                    ])
                    ->label('Aduana')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('display_name')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-coff-header bexia-coff-col-display-name',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-coff-cell bexia-coff-col-display-name bexia-coff-col-wide',
                    ])
                    ->label('Nombre para mostrar')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->extraHeaderAttributes([
                        'class' => 'bexia-coff-header bexia-coff-col-active',
                    ])
                    ->extraCellAttributes([
                        'class' => 'bexia-coff-cell bexia-coff-col-active bexia-coff-col-bool',
                    ])
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomsOffices::route('/'),
            'create' => Pages\CreateCustomsOffice::route('/create'),
            'edit' => Pages\EditCustomsOffice::route('/{record}/edit'),
        ];
    }
}
