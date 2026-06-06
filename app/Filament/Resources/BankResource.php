<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankResource\Pages;
use App\Models\Bank;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BankResource extends Resource
{
    protected static ?string $navigationLabel = 'Bancos';
    protected static ?string $model = Bank::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $tenantRelationshipName = 'banks';

    protected static ?string $navigationGroup = 'Tesorería';

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $modelLabel = 'banco';

    protected static ?string $pluralModelLabel = 'bancos';

    protected static ?int $navigationSort = 10;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.bankresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('treasury.view');
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->can('treasury.view');
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->can('treasury.create');
    }

    public static function canEdit($record): bool
    {
        return auth()->check() && auth()->user()->can('treasury.update');
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->can('treasury.delete');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')
                ->default(fn (): ?int => static::companyId()),

            Forms\Components\TextInput::make('name')
                ->label('Nombre corto')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('legal_name')
                ->label('Razón social')
                ->maxLength(255),

            Forms\Components\TextInput::make('code')
                ->label('Clave bancaria sugerida')
                ->helperText('Clave de 3 dígitos sugerida por catálogo. Puedes cambiarla si tu operación lo requiere.')
                ->maxLength(50),

            Forms\Components\Toggle::make('is_active')
                ->label('Activo')
                ->default(true),

            Forms\Components\Textarea::make('notes')
                ->label('Notas')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('company_id', static::companyId()))
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Clave')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre corto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('legal_name')
                    ->label('Razón social')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanks::route('/'),
            'create' => Pages\CreateBank::route('/create'),
            'edit' => Pages\EditBank::route('/{record}/edit'),
        ];
    }

    public static function companyId(): ?int
    {
        return Filament::getTenant()?->id
            ?? auth()->user()?->company_id
            ?? null;
    }
}
