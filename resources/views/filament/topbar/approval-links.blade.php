@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;
    use App\Support\BexiaUserNotification;

    $userId = (int) auth()->id();

    $tenantId = request()->route('tenant');

    if (! is_numeric($tenantId) || (int) $tenantId <= 0) {
        $tenantId = (int) (auth()->user()?->company_id ?? 0);
    }

    $pendingApprovals = 0;
    $sentPending = 0;
    $unreadNotifications = 0;

    if ($userId > 0 && Schema::hasTable('approval_requests') && Schema::hasTable('approval_request_steps')) {
        $pendingApprovals = DB::table('approval_request_steps as steps')
            ->join('approval_requests as requests', 'requests.id', '=', 'steps.approval_request_id')
            ->where('steps.status', 'pending')
            ->where('requests.status', 'pending')
            ->where('steps.approver_user_id', $userId)
            ->count();

        $sentPending = DB::table('approval_requests')
            ->where('requester_user_id', $userId)
            ->where('status', 'pending')
            ->count();
    }

    if ($userId > 0 && class_exists(BexiaUserNotification::class)) {
        $unreadNotifications = BexiaUserNotification::unreadCountForUser($userId);
    }

    $base = $tenantId ? url('/admin/' . $tenantId) : url('/admin');

    $items = [
        [
            'label' => 'Aprobaciones',
            'count' => $pendingApprovals,
            'url' => $base . '/my-pending-approvals',
            'active' => request()->is('admin/*/my-pending-approvals'),
            'tone' => $pendingApprovals > 0 ? 'amber' : 'gray',
        ],
        [
            'label' => 'Avisos',
            'count' => $unreadNotifications,
            'url' => $base . '/my-notifications',
            'active' => request()->is('admin/*/my-notifications'),
            'tone' => $unreadNotifications > 0 ? 'blue' : 'gray',
        ],
        [
            'label' => 'Enviados',
            'count' => $sentPending,
            'url' => $base . '/my-sent-approval-statuses',
            'active' => request()->is('admin/*/my-sent-approval-statuses'),
            'tone' => $sentPending > 0 ? 'amber' : 'gray',
        ],
    ];
@endphp

<style>
    .bexia-approval-topbar {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-right: 12px;
    }

    .bexia-approval-topbar__link {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        height: 34px;
        padding: 0 10px;
        border-radius: 999px;
        border: 1px solid rgb(229 231 235);
        background: rgb(255 255 255);
        color: rgb(55 65 81);
        font-size: 12px;
        font-weight: 600;
        line-height: 1;
        text-decoration: none;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }

    .bexia-approval-topbar__link:hover {
        background: rgb(249 250 251);
        border-color: rgb(209 213 219);
    }

    .bexia-approval-topbar__link.is-active {
        background: rgb(239 246 255);
        border-color: rgb(191 219 254);
        color: rgb(29 78 216);
    }

    .bexia-approval-topbar__link.is-amber {
        background: rgb(255 251 235);
        border-color: rgb(252 211 77);
        color: rgb(146 64 14);
    }

    .bexia-approval-topbar__link.is-blue {
        background: rgb(239 246 255);
        border-color: rgb(147 197 253);
        color: rgb(29 78 216);
    }

    .bexia-approval-topbar__badge {
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: rgb(243 244 246);
        color: rgb(75 85 99);
        font-size: 11px;
        font-weight: 700;
    }

    .bexia-approval-topbar__badge.is-blue {
        background: rgb(219 234 254);
        color: rgb(29 78 216);
    }

    .bexia-approval-topbar__badge.is-amber {
        background: rgb(254 243 199);
        color: rgb(180 83 9);
    }

    @media (max-width: 900px) {
        .bexia-approval-topbar__link span:first-child {
            display: none;
        }

        .bexia-approval-topbar {
            gap: 4px;
            margin-right: 6px;
        }
    }
</style>

<div class="bexia-approval-topbar">
    @foreach($items as $item)
        <a
            href="{{ $item['url'] }}"
            class="bexia-approval-topbar__link {{ $item['active'] ? 'is-active' : '' }} {{ $item['tone'] === 'blue' ? 'is-blue' : '' }} {{ $item['tone'] === 'amber' ? 'is-amber' : '' }}"
        >
            <span>{{ $item['label'] }}</span>
            <span class="bexia-approval-topbar__badge {{ $item['tone'] === 'blue' ? 'is-blue' : '' }} {{ $item['tone'] === 'amber' ? 'is-amber' : '' }}">
                {{ $item['count'] }}
            </span>
        </a>
    @endforeach
</div>
