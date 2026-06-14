<?php

namespace App\Filament\Pages;

use App\Support\AiInsights\AiInsightsSecurityScope;
use App\Support\AiInsights\AiInsightsToolRegistry;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class BexiaInsightsAi extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Bexia Insights AI';

    protected static ?string $title = 'Bexia Insights AI';

    protected static ?string $navigationGroup = 'Dirección';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.bexia-insights-ai';

    public string $question = '';

    public array $allowedCompanyIds = [];

    public array $tools = [];

    public ?string $demoAnswer = null;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->allowedCompanyIds = AiInsightsSecurityScope::allowedCompanyIdsForUser(auth()->user());
        $this->tools = AiInsightsToolRegistry::availableTools();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return AiInsightsSecurityScope::canAccess(auth()->user());
    }

    public static function canAccess(): bool
    {
        return AiInsightsSecurityScope::canAccess(auth()->user());
    }

    public function sendQuestion(): void
    {
        abort_unless(static::canAccess(), 403);

        $question = trim($this->question);

        if ($question === '') {
            $this->demoAnswer = 'Escribe una pregunta para iniciar.';
            return;
        }

        $tenant = Filament::getTenant();

        $this->demoAnswer = 'Bexia Insights AI ya está preparado para recibir consultas. '
            . 'En la siguiente fase conectaremos las herramientas internas seguras. '
            . 'Tu pregunta fue: "' . $question . '". '
            . 'Empresas permitidas detectadas: ' . implode(', ', $this->allowedCompanyIds ?: ['ninguna']) . '. '
            . 'Tenant actual: ' . ($tenant?->getKey() ?? 'sin tenant') . '.';

        $this->question = '';
    }
}
