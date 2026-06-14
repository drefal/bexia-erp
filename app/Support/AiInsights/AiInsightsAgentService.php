<?php

namespace App\Support\AiInsights;

use App\Models\AiInsightAuditLog;
use App\Models\AiInsightConversation;
use App\Models\AiInsightMessage;
use App\Models\AiInsightToolRun;
use App\Models\User;
use App\Support\AiInsights\Tools\SalesSummaryTool;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class AiInsightsAgentService
{
    public function ask(
        User $user,
        ?int $tenantCompanyId,
        string $question,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        bool $forceLocal = false,
    ): array {
        $question = trim($question);

        $allowedCompanyIds = AiInsightsSecurityScope::allowedCompanyIdsForUser($user);

        $conversation = AiInsightConversation::create([
            'company_id' => $tenantCompanyId,
            'user_id' => $user->id,
            'title' => Str::limit($question, 120),
            'allowed_company_ids' => $allowedCompanyIds,
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $userMessage = AiInsightMessage::create([
            'conversation_id' => $conversation->id,
            'company_id' => $tenantCompanyId,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $question,
            'metadata' => [
                'source' => 'bexia_insights_ai_agent_core',
            ],
        ]);

        if ($allowedCompanyIds === []) {
            return $this->finalizeWithoutTool(
                $conversation,
                $user,
                $tenantCompanyId,
                'No se detectaron empresas permitidas para tu usuario.',
                ['mode' => 'blocked_no_companies'],
            );
        }

        if ($forceLocal || ! $this->openAiEnabled()) {
            return $this->runLocalFallback(
                $conversation,
                $userMessage,
                $user,
                $tenantCompanyId,
                $question,
                $allowedCompanyIds,
                $ipAddress,
                $userAgent,
                $forceLocal ? 'forced_local_validation' : 'openai_not_configured',
            );
        }

        try {
            return $this->runOpenAiAgent(
                $conversation,
                $userMessage,
                $user,
                $tenantCompanyId,
                $question,
                $allowedCompanyIds,
                $ipAddress,
                $userAgent,
            );
        } catch (\Throwable $e) {
            return $this->runLocalFallback(
                $conversation,
                $userMessage,
                $user,
                $tenantCompanyId,
                $question,
                $allowedCompanyIds,
                $ipAddress,
                $userAgent,
                'openai_error',
                $e->getMessage(),
            );
        }
    }

    public function openAiEnabled(): bool
    {
        return filter_var(config('services.openai.enabled'), FILTER_VALIDATE_BOOLEAN)
            && filled((string) config('services.openai.api_key'));
    }

    private function runOpenAiAgent(
        AiInsightConversation $conversation,
        AiInsightMessage $userMessage,
        User $user,
        ?int $tenantCompanyId,
        string $question,
        array $allowedCompanyIds,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        $firstResponse = $this->openAiCreateResponse([
            'model' => (string) config('services.openai.model', 'gpt-5.5'),
            'instructions' => $this->systemInstructions($allowedCompanyIds),
            'input' => $question,
            'tools' => [
                $this->salesSummaryToolSchema(),
            ],
            'tool_choice' => 'auto',
        ]);

        $functionCalls = $this->extractFunctionCalls($firstResponse);

        if ($functionCalls === []) {
            $answer = $this->extractText($firstResponse)
                ?: 'No encontré una herramienta disponible para responder esa pregunta con datos reales.';

            return $this->finalizeWithoutTool(
                $conversation,
                $user,
                $tenantCompanyId,
                $answer,
                [
                    'mode' => 'openai_no_tool_call',
                    'openai_response_id' => $firstResponse['id'] ?? null,
                ],
            );
        }

        $toolOutputs = [];
        $toolRunIds = [];

        foreach ($functionCalls as $call) {
            if (($call['name'] ?? '') !== 'sales_summary') {
                continue;
            }

            $arguments = $this->safeJsonDecode((string) ($call['arguments'] ?? '{}'));
            [$from, $to, $label] = $this->dateRangeFromArguments($arguments, $question);

            $started = microtime(true);

            $toolResult = app(SalesSummaryTool::class)->run(
                $allowedCompanyIds,
                $from,
                $to,
            );

            $durationMs = (int) round((microtime(true) - $started) * 1000);

            $toolRun = AiInsightToolRun::create([
                'conversation_id' => $conversation->id,
                'message_id' => $userMessage->id,
                'company_id' => $tenantCompanyId,
                'user_id' => $user->id,
                'tool_name' => 'sales_summary',
                'allowed_company_ids' => $allowedCompanyIds,
                'input' => [
                    'question' => $question,
                    'arguments' => $arguments,
                    'from' => $from->toDateTimeString(),
                    'to' => $to->toDateTimeString(),
                    'label' => $label,
                ],
                'output_summary' => $toolResult,
                'status' => ($toolResult['ok'] ?? false) ? 'success' : 'error',
                'error_message' => ($toolResult['ok'] ?? false) ? null : ($toolResult['message'] ?? 'Error desconocido'),
                'duration_ms' => $durationMs,
            ]);

            $toolRunIds[] = $toolRun->id;

            $toolOutputs[] = [
                'type' => 'function_call_output',
                'call_id' => $call['call_id'],
                'output' => json_encode($toolResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];

            AiInsightAuditLog::create([
                'conversation_id' => $conversation->id,
                'message_id' => $userMessage->id,
                'company_id' => $tenantCompanyId,
                'user_id' => $user->id,
                'event' => 'openai_sales_summary_tool_run',
                'allowed_company_ids' => $allowedCompanyIds,
                'payload' => [
                    'question' => $question,
                    'arguments' => $arguments,
                    'date_range_label' => $label,
                    'tool_run_id' => $toolRun->id,
                    'duration_ms' => $durationMs,
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent ? substr($userAgent, 0, 1000) : null,
            ]);
        }

        if ($toolOutputs === []) {
            return $this->finalizeWithoutTool(
                $conversation,
                $user,
                $tenantCompanyId,
                'La pregunta fue entendida, pero aún no hay una herramienta autorizada para ese módulo.',
                [
                    'mode' => 'openai_tool_not_implemented',
                    'openai_response_id' => $firstResponse['id'] ?? null,
                ],
            );
        }

        $secondResponse = $this->openAiCreateResponse([
            'model' => (string) config('services.openai.model', 'gpt-5.5'),
            'previous_response_id' => $firstResponse['id'] ?? null,
            'input' => $toolOutputs,
        ]);

        $answer = $this->extractText($secondResponse)
            ?: 'La herramienta se ejecutó correctamente, pero no se pudo redactar la respuesta final.';

        AiInsightMessage::create([
            'conversation_id' => $conversation->id,
            'company_id' => $tenantCompanyId,
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => $answer,
            'metadata' => [
                'source' => 'openai_agent_core',
                'openai_first_response_id' => $firstResponse['id'] ?? null,
                'openai_final_response_id' => $secondResponse['id'] ?? null,
                'tool_run_ids' => $toolRunIds,
            ],
        ]);

        return [
            'ok' => true,
            'mode' => 'openai_agent_core',
            'answer' => $answer,
            'tool_run_ids' => $toolRunIds,
            'openai_response_id' => $secondResponse['id'] ?? null,
        ];
    }

    private function runLocalFallback(
        AiInsightConversation $conversation,
        AiInsightMessage $userMessage,
        User $user,
        ?int $tenantCompanyId,
        string $question,
        array $allowedCompanyIds,
        ?string $ipAddress,
        ?string $userAgent,
        string $mode,
        ?string $errorMessage = null,
    ): array {
        if (! $this->looksLikeSalesQuestion($question)) {
            $answer = $errorMessage
                ? 'OpenAI no pudo responder en este momento. Por ahora solo puedo resolver localmente preguntas de ventas.'
                : 'Para preguntas abiertas de inventario, nómina, tesorería u otros módulos falta activar OpenAI y conectar más herramientas. Por ahora está disponible ventas.';

            return $this->finalizeWithoutTool(
                $conversation,
                $user,
                $tenantCompanyId,
                $answer,
                [
                    'mode' => $mode,
                    'error' => $errorMessage,
                ],
            );
        }

        [$from, $to, $label] = $this->dateRangeFromQuestion($question);

        $started = microtime(true);

        $toolResult = app(SalesSummaryTool::class)->run(
            $allowedCompanyIds,
            $from,
            $to,
        );

        $durationMs = (int) round((microtime(true) - $started) * 1000);

        $toolRun = AiInsightToolRun::create([
            'conversation_id' => $conversation->id,
            'message_id' => $userMessage->id,
            'company_id' => $tenantCompanyId,
            'user_id' => $user->id,
            'tool_name' => 'sales_summary',
            'allowed_company_ids' => $allowedCompanyIds,
            'input' => [
                'question' => $question,
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
                'label' => $label,
                'mode' => $mode,
            ],
            'output_summary' => $toolResult,
            'status' => ($toolResult['ok'] ?? false) ? 'success' : 'error',
            'error_message' => ($toolResult['ok'] ?? false) ? null : ($toolResult['message'] ?? 'Error desconocido'),
            'duration_ms' => $durationMs,
        ]);

        $answer = $this->formatSalesSummaryAnswer($toolResult, $label);

        if ($errorMessage) {
            $answer .= PHP_EOL . PHP_EOL . 'Nota técnica: OpenAI falló y se respondió con el modo local seguro.';
        }

        AiInsightMessage::create([
            'conversation_id' => $conversation->id,
            'company_id' => $tenantCompanyId,
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => $answer,
            'metadata' => [
                'source' => 'local_agent_fallback',
                'mode' => $mode,
                'tool_run_id' => $toolRun->id,
            ],
        ]);

        AiInsightAuditLog::create([
            'conversation_id' => $conversation->id,
            'message_id' => $userMessage->id,
            'company_id' => $tenantCompanyId,
            'user_id' => $user->id,
            'event' => 'local_sales_summary_tool_run',
            'allowed_company_ids' => $allowedCompanyIds,
            'payload' => [
                'question' => $question,
                'date_range_label' => $label,
                'tool_run_id' => $toolRun->id,
                'duration_ms' => $durationMs,
                'mode' => $mode,
                'error' => $errorMessage,
            ],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? substr($userAgent, 0, 1000) : null,
        ]);

        return [
            'ok' => true,
            'mode' => $mode,
            'answer' => $answer,
            'tool_run_ids' => [$toolRun->id],
        ];
    }

    private function finalizeWithoutTool(
        AiInsightConversation $conversation,
        User $user,
        ?int $tenantCompanyId,
        string $answer,
        array $metadata = [],
    ): array {
        AiInsightMessage::create([
            'conversation_id' => $conversation->id,
            'company_id' => $tenantCompanyId,
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => $answer,
            'metadata' => [
                'source' => 'agent_core_no_tool',
            ] + $metadata,
        ]);

        return [
            'ok' => true,
            'mode' => $metadata['mode'] ?? 'no_tool',
            'answer' => $answer,
        ];
    }

    private function openAiCreateResponse(array $payload): array
    {
        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $apiKey = (string) config('services.openai.api_key');

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(60)
            ->post($baseUrl . '/responses', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI HTTP ' . $response->status() . ': ' . Str::limit($response->body(), 500));
        }

        return $response->json() ?: [];
    }

    private function systemInstructions(array $allowedCompanyIds): string
    {
        return implode(PHP_EOL, [
            'Eres Bexia Insights AI, un asistente directivo dentro de Bexia ERP.',
            'Responde siempre en español claro, ejecutivo y práctico.',
            'No inventes datos. Si necesitas datos reales, usa una herramienta disponible.',
            'No digas que consultaste módulos que no tienen herramienta conectada.',
            'Nunca pidas ni generes SQL libre.',
            'Nunca reveles datos de empresas fuera del alcance permitido.',
            'Empresas permitidas para este usuario: ' . implode(', ', $allowedCompanyIds) . '.',
            'Herramienta disponible: sales_summary para preguntas de ventas, ingresos, tickets, PDV, comparativos o desempeño comercial.',
            'Si la pregunta es de inventario, nómina, tesorería, CxC o CxP, explica que el módulo aún falta conectarlo como herramienta segura.',
        ]);
    }

    private function salesSummaryToolSchema(): array
    {
        return [
            'type' => 'function',
            'name' => 'sales_summary',
            'description' => 'Consulta resumen combinado de ventas del módulo Ventas, tickets PDV cobrados y devoluciones PDV, filtrado por empresas permitidas.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'period' => [
                        'type' => 'string',
                        'enum' => ['today', 'yesterday', 'this_week', 'this_month', 'this_year', 'custom'],
                        'description' => 'Periodo solicitado por el usuario.',
                    ],
                    'from' => [
                        'type' => 'string',
                        'description' => 'Fecha inicial YYYY-MM-DD. Solo usar si period=custom.',
                    ],
                    'to' => [
                        'type' => 'string',
                        'description' => 'Fecha final YYYY-MM-DD. Solo usar si period=custom.',
                    ],
                    'compare_with' => [
                        'type' => 'string',
                        'description' => 'Periodo comparativo solicitado, si aplica. En esta version puede ignorarse.',
                    ],
                ],
                'required' => ['period'],
                'additionalProperties' => false,
            ],
        ];
    }

    private function extractFunctionCalls(array $response): array
    {
        $calls = [];

        foreach (($response['output'] ?? []) as $item) {
            if (($item['type'] ?? null) === 'function_call') {
                $calls[] = [
                    'call_id' => $item['call_id'] ?? $item['id'] ?? null,
                    'name' => $item['name'] ?? null,
                    'arguments' => $item['arguments'] ?? '{}',
                ];
            }
        }

        return array_values(array_filter($calls, fn ($call) => filled($call['call_id']) && filled($call['name'])));
    }

    private function extractText(array $response): ?string
    {
        if (filled($response['output_text'] ?? null)) {
            return trim((string) $response['output_text']);
        }

        $parts = [];

        foreach (($response['output'] ?? []) as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach (($item['content'] ?? []) as $content) {
                if (filled($content['text'] ?? null)) {
                    $parts[] = (string) $content['text'];
                }

                if (filled($content['output_text'] ?? null)) {
                    $parts[] = (string) $content['output_text'];
                }
            }
        }

        $text = trim(implode(PHP_EOL, $parts));

        return $text !== '' ? $text : null;
    }

    private function safeJsonDecode(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function dateRangeFromArguments(array $arguments, string $question): array
    {
        $period = (string) ($arguments['period'] ?? '');

        if ($period === 'custom' && filled($arguments['from'] ?? null) && filled($arguments['to'] ?? null)) {
            try {
                $from = Carbon::parse((string) $arguments['from'])->startOfDay();
                $to = Carbon::parse((string) $arguments['to'])->endOfDay();

                if ($to->greaterThanOrEqualTo($from) && $from->diffInDays($to) <= 370) {
                    return [$from, $to, 'periodo personalizado'];
                }
            } catch (\Throwable) {
                //
            }
        }

        return match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay(), 'hoy'],
            'yesterday' => [
                now()->subDay()->startOfDay(),
                now()->subDay()->endOfDay(),
                'ayer',
            ],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek(), 'esta semana'],
            'this_month' => [now()->startOfMonth(), now()->endOfDay(), 'este mes'],
            'this_year' => [now()->startOfYear(), now()->endOfDay(), 'este año'],
            default => $this->dateRangeFromQuestion($question),
        };
    }

    private function dateRangeFromQuestion(string $question): array
    {
        $q = Str::lower($question);
        $now = now();

        if (str_contains($q, 'ayer')) {
            $date = $now->copy()->subDay();

            return [$date->copy()->startOfDay(), $date->copy()->endOfDay(), 'ayer'];
        }

        if (str_contains($q, 'hoy')) {
            return [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'hoy'];
        }

        if (str_contains($q, 'semana')) {
            return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek(), 'esta semana'];
        }

        if (str_contains($q, 'año') || str_contains($q, 'anio')) {
            return [$now->copy()->startOfYear(), $now->copy()->endOfDay(), 'este año'];
        }

        return [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), 'este mes'];
    }

    private function looksLikeSalesQuestion(string $question): bool
    {
        $q = Str::lower($question);

        return str_contains($q, 'venta')
            || str_contains($q, 'vend')
            || str_contains($q, 'pdv')
            || str_contains($q, 'ticket')
            || str_contains($q, 'ingreso')
            || str_contains($q, 'factur')
            || str_contains($q, 'comercial')
            || str_contains($q, 'comerciales')
            || str_contains($q, 'desempeño comercial')
            || str_contains($q, 'resumen comercial')
            || str_contains($q, 'resultado comercial');
    }

    private function formatSalesSummaryAnswer(array $result, string $label): string
    {
        if (! ($result['ok'] ?? false)) {
            return (string) ($result['message'] ?? 'No se pudo generar el resumen de ventas.');
        }

        $summary = $result['summary'];

        $lines = [];
        $lines[] = 'Resumen de ventas de ' . $label . ':';
        $lines[] = '';
        $lines[] = 'Total general neto: $' . number_format((float) $summary['total_net'], 2) . ' MXN';
        $lines[] = 'Ventas módulo Ventas: $' . number_format((float) $summary['sales_orders_total'], 2) . ' MXN en ' . (int) $summary['sales_orders_documents'] . ' documento(s).';
        $lines[] = 'PDV bruto: $' . number_format((float) $summary['pos_orders_gross_total'], 2) . ' MXN en ' . (int) $summary['pos_orders_documents'] . ' ticket(s).';
        $lines[] = 'Devoluciones PDV: -$' . number_format((float) $summary['pos_refunds_total'], 2) . ' MXN en ' . (int) $summary['pos_refunds_documents'] . ' devolución(es).';
        $lines[] = 'PDV neto: $' . number_format((float) $summary['pos_net_total'], 2) . ' MXN.';
        $lines[] = '';
        $lines[] = 'Por empresa:';

        foreach ($result['by_company'] as $company) {
            $lines[] = '- ' . $company['company_name']
                . ': total neto $' . number_format((float) $company['total_net'], 2)
                . ' MXN | Ventas $' . number_format((float) $company['sales_orders_total'], 2)
                . ' | PDV neto $' . number_format((float) $company['pos_net_total'], 2)
                . '.';
        }

        $lines[] = '';
        $lines[] = 'Rango: ' . Carbon::parse($result['range']['from'])->format('d/m/Y H:i')
            . ' a ' . Carbon::parse($result['range']['to'])->format('d/m/Y H:i') . '.';
        $lines[] = 'Nota: no se usaron facturas para evitar duplicar ventas facturadas desde PDV o ventas.';

        return implode(PHP_EOL, $lines);
    }
}
