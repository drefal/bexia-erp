<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SatCfdiUseTaxRegimeResource\Pages;
use App\Models\SatCfdiUse;
use App\Models\SatCfdiUseTaxRegime;
use App\Models\SatTaxRegime;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SatCfdiUseTaxRegimeResource extends Resource
{
    protected static ?string $model = SatCfdiUseTaxRegime::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Facturación';

    protected static ?string $navigationLabel = 'Uso CFDI por régimen';

    protected static ?string $modelLabel = 'Uso CFDI por régimen';

    protected static ?string $pluralModelLabel = 'Usos CFDI por régimen';

    protected static ?string $slug = 'cfdi-use-tax-regimes';

    protected static ?int $navigationSort = 40;

public static function canCreate(): bool
    {
        return static::canManage();
    }

    public static function canEdit($record): bool
    {
        return static::canManage();
    }

    public static function canDelete($record): bool
    {
        return static::canManage();
    }

    public static function canManage(): bool
    {
        $user = auth()->user();

        return (bool) ($user && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['taxRegime', 'cfdiUse'])
            ->orderBy('tax_regime_code')
            ->orderBy('cfdi_use_code');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.satcfdiusetaxregimeresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('invoicing.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('invoicing.view')
            );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Relación permitida')
                ->columns(12)
                ->schema([
                    Forms\Components\Select::make('tax_regime_code')
                        ->label('Régimen fiscal')
                        ->options(fn (): array => SatTaxRegime::query()
                            ->where('active', true)
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn ($row) => [$row->code => $row->code . ' - ' . $row->name])
                            ->all())
                        ->searchable()
                        ->required()
                        ->columnSpan(6),

                    Forms\Components\Select::make('cfdi_use_code')
                        ->label('Uso CFDI')
                        ->options(fn (): array => SatCfdiUse::query()
                            ->where('active', true)
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn ($row) => [$row->code => $row->code . ' - ' . $row->name])
                            ->all())
                        ->searchable()
                        ->required()
                        ->columnSpan(6),

                    Forms\Components\Toggle::make('active')
                        ->label('Activo')
                        ->default(true)
                        ->columnSpan(2),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notas internas')
                        ->rows(2)
                        ->columnSpan(10),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tax_regime_code')
                    ->label('Régimen')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record): string => $record->taxRegime
                        ? $record->taxRegime->code . ' - ' . $record->taxRegime->name
                        : (string) $state),

                Tables\Columns\TextColumn::make('cfdi_use_code')
                    ->label('Uso CFDI')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record): string => $record->cfdiUse
                        ? $record->cfdiUse->code . ' - ' . $record->cfdiUse->name
                        : (string) $state),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar relación'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSatCfdiUseTaxRegimes::route('/'),
            'create' => Pages\CreateSatCfdiUseTaxRegime::route('/create'),
            'edit' => Pages\EditSatCfdiUseTaxRegime::route('/{record}/edit'),
        ];
    }
}
