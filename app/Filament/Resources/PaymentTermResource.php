<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentTermResource\Pages;
use App\Models\PaymentTerm;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentTermResource extends Resource
{

public static function shouldRegisterNavigation(): bool
{
    return auth()->user()?->can('payment_terms.view') ?? false;
}

public static function canViewAny(): bool
{
    return auth()->user()?->can('payment_terms.view') ?? false;
}

    protected static ?string $model = PaymentTerm::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Catálogos de facturación';

    protected static ?string $navigationLabel = 'Términos de pago';

    protected static ?string $modelLabel = 'término de pago';

    protected static ?string $pluralModelLabel = 'términos de pago';

    protected static ?int $navigationSort = 60;

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        // No usamos el tenant automático de Filament aquí porque
        // payment_terms maneja:
        // - términos globales company_id = null
        // - términos propios de empresa company_id = empresa actual
        $query = PaymentTerm::query();

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where(function (Builder $query) use ($companyId): void {
                $query
                    ->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            });
        } else {
            $query->whereNull('company_id');
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => static::currentCompanyId()),

                Forms\Components\Section::make('Término de pago')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(50)
                            ->helperText('Ej. CONTADO, CREDITO_15, CREDITO_30.')
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(150)
                            ->helperText('Ej. Contado, Crédito 15 días, Crédito 30 días.')
                            ->columnSpan(5),

                        Forms\Components\TextInput::make('days')
                            ->label('Días')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required()
                            ->columnSpan(2),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->columnSpan(2),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(12),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('days')
                    ->label('Días')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ])
            ->defaultSort('days');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentTerms::route('/'),
            'create' => Pages\CreatePaymentTerm::route('/create'),
            'edit' => Pages\EditPaymentTerm::route('/{record}/edit'),
        ];
    }

    protected static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        if ($tenant && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        $user = auth()->user();

        if ($user && isset($user->company_id)) {
            return (int) $user->company_id;
        }

        return null;
    }
}
