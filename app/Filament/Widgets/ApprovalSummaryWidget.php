<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DashboardWidgetRegistry;
use App\Support\BexiaUserNotification;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApprovalSummaryWidget extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        // BEXIA_V57210G2_DASHBOARD_WIDGET_PERMISSION
        if (! \App\Support\Security\BexiaAccess::dashboard()) {
            return false;
        }

        if (! auth()->check()) {
            return false;
        }

        try {
            return app(DashboardWidgetRegistry::class)
                ->visibleForUser(auth()->user())
                ->pluck('key')
                ->contains('approvals_summary');
        } catch (\Throwable) {
            return true;
        }
    }

    protected static ?int $sort = 10;

    protected function getStats(): array
    {
        $userId = (int) auth()->id();

        $pendingToApprove = 0;
        $sentPending = 0;
        $unread = 0;

        if ($userId > 0 && Schema::hasTable('approval_requests') && Schema::hasTable('approval_request_steps')) {
            $pendingToApprove = DB::table('approval_request_steps as steps')
                ->join('approval_requests as requests', 'requests.id', '=', 'steps.approval_request_id')
                ->where('steps.status', 'pending')
                ->where('requests.status', 'pending')
                ->whereColumn('steps.step_order', 'requests.current_step_order')
                ->where('steps.approver_user_id', $userId)
                ->count();

            $sentPending = DB::table('approval_requests')
                ->where('requester_user_id', $userId)
                ->where('status', 'pending')
                ->count();
        }

        if (class_exists(BexiaUserNotification::class)) {
            $unread = BexiaUserNotification::unreadCountForUser($userId);
        }

        return [
            Stat::make('Por aprobar', $pendingToApprove)
                ->description('Documentos esperando tu decisión')
                ->color($pendingToApprove > 0 ? 'warning' : 'success'),

            Stat::make('Mis enviados pendientes', $sentPending)
                ->description('Documentos que enviaste a aprobación')
                ->color($sentPending > 0 ? 'info' : 'gray'),

            Stat::make('Avisos nuevos', $unread)
                ->description('Aprobaciones aprobadas/rechazadas')
                ->color($unread > 0 ? 'danger' : 'gray'),
        ];
    }
}
