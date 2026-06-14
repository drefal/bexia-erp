<?php

namespace App\Filament\Pages;

use App\Support\AiInsights\AiInsightsAgentService;
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

    public array $allowedCompanies = [];

    public array $tools = [];

    public ?string $answer = null;

    public ?array $lastResult = null;

    public bool $openAiConfigured = false;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->allowedCompanyIds = AiInsightsSecurityScope::allowedCompanyIdsForUser(auth()->user());
        $this->allowedCompanies = AiInsightsSecurityScope::allowedCompaniesForUser(auth()->user());
        $this->tools = AiInsightsToolRegistry::availableTools();
        $this->openAiConfigured = app(AiInsightsAgentService::class)->openAiEnabled();
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
            $this->answer = 'Escribe una pregunta para iniciar.';
            return;
        }

        $result = app(AiInsightsAgentService::class)->ask(
            user: auth()->user(),
            tenantCompanyId: Filament::getTenant()?->getKey(),
            question: $question,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );

        $this->allowedCompanyIds = AiInsightsSecurityScope::allowedCompanyIdsForUser(auth()->user());
        $this->allowedCompanies = AiInsightsSecurityScope::allowedCompaniesForUser(auth()->user());
        $this->tools = AiInsightsToolRegistry::availableTools();
        $this->openAiConfigured = app(AiInsightsAgentService::class)->openAiEnabled();

        $this->answer = $result['answer'] ?? 'No se pudo generar respuesta.';
        $this->lastResult = $result;
        $this->question = '';
    }
}
