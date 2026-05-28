<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class DashboardSectionSettingsController extends Controller
{
    public function update(Request $request, int|string $tenant, string $section): RedirectResponse
    {
        abort_unless(auth()->check(), 403);

        if ($section !== 'tesoreria') {
            abort(404);
        }

        $validated = $request->validate([
            'refresh_seconds' => [
                'required',
                'integer',
                Rule::in([30, 60, 120, 300, 600]),
            ],
        ]);

        if (! Schema::hasTable('dashboard_section_user_settings')) {
            abort(500, 'No existe la tabla dashboard_section_user_settings.');
        }

        $companyId = (int) $tenant;
        $userId = (int) auth()->id();
        $refreshSeconds = (int) $validated['refresh_seconds'];

        DB::table('dashboard_section_user_settings')->updateOrInsert(
            [
                'company_id' => $companyId,
                'user_id' => $userId,
                'section_key' => 'tesoreria',
            ],
            [
                'refresh_seconds' => $refreshSeconds,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return back()->with(
            'dashboard_section_settings_status',
            'Tiempo de actualización de Tesorería guardado: ' . $this->label($refreshSeconds) . '.'
        );
    }

    private function label(int $seconds): string
    {
        return match ($seconds) {
            30 => '30 segundos',
            60 => '1 minuto',
            120 => '2 minutos',
            300 => '5 minutos',
            600 => '10 minutos',
            default => $seconds . ' segundos',
        };
    }
}
