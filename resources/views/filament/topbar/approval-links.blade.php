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
        /*
         * V5.83.6J2AP3S2K
         *
         * La misma regla de Mis aprobaciones:
         * usuario explicito OR rol aprobador.
         * No mantener una segunda implementacion aqui.
         */
        $pendingApprovals = \App\Filament\Pages\MyPendingApprovals::pendingCountForUser(
            $userId
        );

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

    // BEXIA_RRHH_SEG02_TOPBAR_START

    $hrActor = auth()->user();

    $hrCompany = \Filament\Facades\Filament::getTenant();

    if (
        $hrActor instanceof \App\Models\User
        && $hrCompany instanceof \App\Models\Company
        && class_exists(
            \App\Support\Attendance\AttendanceAccessPendingInbox::class
        )
        && Schema::hasTable('hr_access_decisions')
    ) {
        try {

            if (
                \App\Support\Attendance\AttendanceAccessPendingInbox
                    ::canAccess($hrActor, $hrCompany)
            ) {

                $hrPendingCount =
                    \App\Support\Attendance\AttendanceAccessPendingInbox
                        ::pendingCount(
                            $hrActor,
                            $hrCompany
                        );

                $hrItem = [
                    'label' => 'Aprobaciones RRHH',
                    'count' => $hrPendingCount,
                    'url' => url(
                        '/admin/'
                        . $hrCompany->getKey()
                        . '/aprobaciones-rrhh'
                    ),
                    'active' => request()->is(
                        'admin/*/aprobaciones-rrhh'
                    ),
                    'tone' => $hrPendingCount > 0
                        ? 'red'
                        : 'gray',
                    'is_hr' => true,
                ];

                array_splice(
                    $items,
                    1,
                    0,
                    [$hrItem]
                );
            }

        } catch (\Throwable $e) {

            // A failure in the new HR counter must
            // not prevent the rest of Bexia rendering.

            report($e);
        }
    }

    // BEXIA_RRHH_SEG02_TOPBAR_END

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

    /* BEXIA_RRHH_SEG02_TOPBAR_STYLE_START */

    .bexia-approval-topbar
    .bexia-approval-topbar__link.is-hr.is-red {
        background: #fef2f2 !important;
        border-color: #fca5a5 !important;
        color: #991b1b !important;
    }

    .bexia-approval-topbar
    .bexia-approval-topbar__link.is-hr.is-red:hover {
        background: #fee2e2 !important;
        border-color: #ef4444 !important;
    }

    .bexia-approval-topbar
    .bexia-approval-topbar__link.is-hr.is-red span {
        color: #991b1b !important;
    }

    .bexia-approval-topbar
    .bexia-approval-topbar__link.is-hr.is-red
    .bexia-approval-topbar__badge.is-red {
        background: #dc2626 !important;
        color: white !important;
    }

    @media (max-width: 900px) {

        .bexia-approval-topbar
        .bexia-approval-topbar__link.is-hr {
            padding: 0 6px;
        }

        .bexia-approval-topbar
        .bexia-approval-topbar__link.is-hr
        span:first-child {
            display: inline !important;
            font-size: 10px;
        }

        .bexia-approval-topbar
        .bexia-approval-topbar__link.is-hr
        .hr-full-label {
            display: none !important;
        }

        .bexia-approval-topbar
        .bexia-approval-topbar__link.is-hr
        .hr-short-label {
            display: inline !important;
        }
    }

    .bexia-approval-topbar
    .bexia-approval-topbar__link.is-hr
    .hr-short-label {
        display: none;
    }


    /* BEXIA_RRHH_FIX09D_COLORS_START */

    .bexia-approval-topbar
    .bexia-approval-topbar__link.is-hr:not(.is-red),
    .bexia-approval-topbar
    .bexia-approval-topbar__link.is-hr.is-active:not(.is-red) {
        background: #f3f4f6 !important;
        border-color: #d1d5db !important;
        color: #6b7280 !important;
        box-shadow: 0 1px 2px rgba(15,23,42,.04) !important;
    }

    .bexia-approval-topbar
    .bexia-approval-topbar__link.is-hr:not(.is-red):hover {
        background: #e5e7eb !important;
        border-color: #9ca3af !important;
    }

    .bexia-approval-topbar
    .bexia-approval-topbar__link.is-hr:not(.is-red) span {
        color: #6b7280 !important;
    }

    .bexia-approval-topbar
    .bexia-approval-topbar__link.is-hr:not(.is-red)
    .bexia-approval-topbar__badge {
        background: #e5e7eb !important;
        color: #6b7280 !important;
    }

    .bexia-approval-topbar
    .bexia-approval-topbar__link.is-hr.is-active.is-red {
        background: #fef2f2 !important;
        border-color: #fca5a5 !important;
        color: #991b1b !important;
        box-shadow: 0 1px 2px rgba(15,23,42,.04) !important;
    }

    .bexia-approval-topbar
    .bexia-approval-topbar__link.is-hr.is-active.is-red
    span {
        color: #991b1b !important;
    }

    .bexia-approval-topbar
    .bexia-approval-topbar__link.is-hr.is-active.is-red
    .bexia-approval-topbar__badge.is-red {
        background: #dc2626 !important;
        color: #ffffff !important;
    }

    /* BEXIA_RRHH_FIX09D_COLORS_END */

    /* BEXIA_RRHH_SEG02_TOPBAR_STYLE_END */

</style>

<div class="bexia-approval-topbar">
    @foreach($items as $item)
        <a
            href="{{ $item['url'] }}"
            class="bexia-approval-topbar__link {{ $item['active'] ? 'is-active' : '' }} {{ $item['tone'] === 'blue' ? 'is-blue' : '' }} {{ $item['tone'] === 'amber' ? 'is-amber' : '' }} {{ $item['tone'] === 'red' ? 'is-red' : '' }} {{ !empty($item['is_hr']) ? 'is-hr' : '' }}"
            @if(!empty($item['is_hr']))
                data-bexia-hr-approvals="true"
                data-hr-company="{{ $hrCompany->getKey() }}"
                data-hr-user="{{ $hrActor->getKey() }}"
                data-hr-realtime-enabled="{{ config('attendance_realtime.enabled') ? '1' : '0' }}"
                data-hr-realtime-key="{{ config('attendance_realtime.key') }}"
                data-hr-csrf="{{ csrf_token() }}"
                aria-label="Aprobaciones RRHH: {{ $item['count'] }} pendientes"
                title="Aprobaciones RRHH"
            @endif
        >
            @if(!empty($item['is_hr']))
                <span>
                    <span class="hr-full-label">
                        Aprobaciones RRHH
                    </span>
                    <span class="hr-short-label">
                        RRHH
                    </span>
                </span>
            @else
                <span>{{ $item['label'] }}</span>
            @endif
            <span class="bexia-approval-topbar__badge {{ $item['tone'] === 'blue' ? 'is-blue' : '' }} {{ $item['tone'] === 'amber' ? 'is-amber' : '' }} {{ $item['tone'] === 'red' ? 'is-red' : '' }}"
                @if(!empty($item['is_hr']))
                    data-bexia-hr-count="true"
                @endif>
                {{ $item['count'] }}
            </span>
        </a>
    @endforeach
</div>

{{-- BEXIA_RRHH_SEG02_REALTIME_START --}}
<script>
(() => {
    'use strict';

    const globalKey = '__bexiaHrApprovalRealtime';

    if (window[globalKey]) {
        window[globalKey].mount();
        return;
    }

    let current = null;

    function mount() {
        const link = document.querySelector(
            '[data-bexia-hr-approvals="true"]'
        );

        if (
            current &&
            current.link === link &&
            link &&
            link.isConnected
        ) {
            return;
        }

        if (current) {
            current.destroy();
            current = null;
        }

        if (!link) {
            return;
        }

        current = createClient(link);
    }

    function createClient(link) {
        const countEl = link.querySelector(
            '[data-bexia-hr-count="true"]'
        );

        if (!countEl) {
            return null;
        }

        const company = link.dataset.hrCompany;
        const user = link.dataset.hrUser;
        const key = link.dataset.hrRealtimeKey;
        const csrf = link.dataset.hrCsrf;

        const realtimeEnabled =
            link.dataset.hrRealtimeEnabled === '1';

        if (
            !/^[1-9]\d*$/.test(company || '') ||
            !/^[1-9]\d*$/.test(user || '')
        ) {
            return null;
        }

        const expectedChannel =
            `private-attendance.approvals.${company}.${user}`;

        const base = `/rrhh/accesos/${company}`;

        let socket = null;
        let subscribed = false;
        let disposed = false;
        let denied = false;

        let reconnectTimer = null;
        let authTimer = null;
        let heartbeatTimer = null;
        let refreshTimer = null;
        let retryDelay = 3000;

        let loading = false;
        let pendingRefresh = false;
        let lastRefresh = 0;

        let lastCount = Number(
            countEl.textContent.trim()
        ) || 0;

        const lifecycle = new AbortController();

        function active() {
            return !disposed &&
                link.isConnected &&
                !denied;
        }

        function clearSocketTimers() {
            clearTimeout(authTimer);
            clearTimeout(heartbeatTimer);

            authTimer = null;
            heartbeatTimer = null;
        }

        function stopSocket() {
            subscribed = false;

            clearTimeout(reconnectTimer);
            reconnectTimer = null;

            clearSocketTimers();

            const previous = socket;
            socket = null;

            if (previous) {
                previous.onclose = null;
                previous.onmessage = null;
                previous.onerror = null;
                previous.close();
            }
        }

        function denyAccess() {
            denied = true;
            stopSocket();
            link.hidden = true;
            link.style.display = 'none';
        }

        function setStale(value) {
            link.title = value
                ? 'Aprobaciones RRHH - pendiente de actualizar'
                : 'Aprobaciones RRHH';

            link.dataset.hrStale = value
                ? '1'
                : '0';
        }

        function setCount(value) {
            if (
                !Number.isSafeInteger(value) ||
                value < 0
            ) {
                return;
            }

            const changed = lastCount !== value;

            lastCount = value;

            countEl.textContent = String(value);

            link.classList.toggle(
                'is-red',
                value > 0
            );

            countEl.classList.toggle(
                'is-red',
                value > 0
            );

            link.setAttribute(
                'aria-label',
                `Aprobaciones RRHH: ${value} pendientes`
            );

            setStale(false);

            if (changed) {
                refreshInbox();
            }
        }

        function refreshInbox() {
            if (
                location.pathname.replace(/\/$/, '') ===
                    `/admin/${company}/aprobaciones-rrhh` &&
                window.Livewire &&
                typeof window.Livewire.dispatch === 'function'
            ) {
                window.Livewire.dispatch(
                    'bexia-hr-access-changed'
                );
            }
        }

        async function requestJson(path, options = {}) {
            const controller = new AbortController();

            const abort = () => controller.abort();

            lifecycle.signal.addEventListener(
                'abort',
                abort,
                {once: true}
            );

            const timer = setTimeout(
                abort,
                10000
            );

            try {
                const response = await fetch(
                    path,
                    {
                        ...options,
                        credentials: 'same-origin',
                        cache: 'no-store',
                        redirect: 'error',
                        signal: controller.signal,
                        headers: {
                            'Accept': 'application/json',
                            ...(options.headers || {})
                        }
                    }
                );

                if (
                    response.status === 401 ||
                    response.status === 403 ||
                    response.status === 419
                ) {
                    denyAccess();
                    return null;
                }

                if (!response.ok) {
                    throw new Error(
                        `HTTP ${response.status}`
                    );
                }

                return await response.json();

            } finally {
                clearTimeout(timer);

                lifecycle.signal.removeEventListener(
                    'abort',
                    abort
                );
            }
        }

        async function refreshCount() {
            if (
                !active() ||
                loading ||
                document.hidden
            ) {
                return;
            }

            loading = true;
            pendingRefresh = false;
            lastRefresh = Date.now();

            try {
                const data = await requestJson(
                    `${base}/pendientes/count`
                );

                if (
                    active() &&
                    data &&
                    data.ok === true
                ) {
                    setCount(data.count);
                }

            } catch (_) {

                // Never report zero merely because
                // the request failed.

                if (active()) {
                    setStale(true);
                }

            } finally {
                loading = false;

                if (pendingRefresh) {
                    scheduleRefresh();
                }
            }
        }

        function scheduleRefresh() {
            if (!active()) {
                return;
            }

            pendingRefresh = true;

            if (
                loading ||
                refreshTimer ||
                document.hidden
            ) {
                return;
            }

            const delay = Math.max(
                0,
                2500 - (
                    Date.now() - lastRefresh
                )
            );

            refreshTimer = setTimeout(() => {
                refreshTimer = null;
                refreshCount();
            }, delay);
        }

        function scheduleReconnect() {
            if (
                !active() ||
                !realtimeEnabled ||
                reconnectTimer ||
                document.hidden ||
                !navigator.onLine
            ) {
                return;
            }

            const jitter = Math.floor(
                Math.random() * 1000
            );

            reconnectTimer = setTimeout(() => {
                reconnectTimer = null;
                connect();
            }, retryDelay + jitter);

            retryDelay = Math.min(
                retryDelay * 2,
                60000
            );
        }

        function touchHeartbeat(ws) {
            clearTimeout(heartbeatTimer);

            heartbeatTimer = setTimeout(() => {
                if (socket === ws) {
                    ws.close();
                }
            }, 100000);
        }

        async function authorize(ws, socketId) {
            const data = await requestJson(
                `${base}/realtime/auth`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type':
                            'application/json',

                        'X-CSRF-TOKEN': csrf
                    },
                    body: JSON.stringify({
                        socket_id: socketId
                    })
                }
            );

            if (
                !active() ||
                socket !== ws
            ) {
                return;
            }

            if (
                !data ||
                data.channel !== expectedChannel ||
                typeof data.auth !== 'string'
            ) {
                throw new Error(
                    'Invalid private channel'
                );
            }

            ws.send(JSON.stringify({
                event: 'pusher:subscribe',
                data: {
                    channel: expectedChannel,
                    auth: data.auth
                }
            }));
        }

        function connect() {
            if (
                !active() ||
                !realtimeEnabled ||
                !key ||
                !csrf ||
                socket ||
                document.hidden ||
                !navigator.onLine ||
                location.protocol !== 'https:' ||
                typeof WebSocket === 'undefined'
            ) {
                return;
            }

            const address =
                `wss://${location.host}/asistencia/ws/app/` +
                encodeURIComponent(key) +
                '?protocol=7&client=js&version=8.4.0&flash=false';

            let ws;

            try {
                ws = new WebSocket(address);
            } catch (_) {
                scheduleReconnect();
                return;
            }

            socket = ws;

            authTimer = setTimeout(() => {
                if (
                    socket === ws &&
                    !subscribed
                ) {
                    ws.close();
                }
            }, 15000);

            ws.onopen = () => {
                touchHeartbeat(ws);
            };

            ws.onmessage = async message => {
                if (
                    !active() ||
                    socket !== ws
                ) {
                    return;
                }

                touchHeartbeat(ws);

                try {
                    const event = JSON.parse(
                        message.data
                    );

                    if (
                        event.event ===
                        'pusher:connection_established'
                    ) {
                        const data =
                            typeof event.data === 'string'
                                ? JSON.parse(event.data)
                                : event.data;

                        await authorize(
                            ws,
                            data.socket_id
                        );

                    } else if (
                        event.event ===
                            'pusher_internal:subscription_succeeded' ||
                        event.event ===
                            'pusher:subscription_succeeded'
                    ) {

                        if (
                            event.channel !==
                            expectedChannel
                        ) {
                            ws.close();
                            return;
                        }

                        subscribed = true;
                        retryDelay = 3000;

                        clearTimeout(authTimer);

                        scheduleRefresh();

                        // Periodically revalidate
                        // current user permissions.

                        authTimer = setTimeout(() => {
                            if (socket === ws) {
                                ws.close();
                            }
                        }, 300000);

                    } else if (
                        event.event ===
                            'attendance.approvals.changed' &&
                        event.channel === expectedChannel &&
                        subscribed
                    ) {

                        // Events contain no trusted
                        // business data or counters.

                        scheduleRefresh();

                    } else if (
                        event.event === 'pusher:ping'
                    ) {
                        ws.send(JSON.stringify({
                            event: 'pusher:pong',
                            data: {}
                        }));

                    } else if (
                        event.event === 'pusher:error'
                    ) {
                        ws.close();
                    }

                } catch (_) {
                    ws.close();
                }
            };

            ws.onerror = () => {
                if (socket === ws) {
                    ws.close();
                }
            };

            ws.onclose = () => {
                if (socket !== ws) {
                    return;
                }

                socket = null;
                subscribed = false;

                clearSocketTimers();

                scheduleRefresh();
                scheduleReconnect();
            };
        }

        function visibilityChanged() {
            if (document.hidden) {
                stopSocket();
                return;
            }

            scheduleRefresh();
            connect();
        }

        function online() {
            scheduleRefresh();
            connect();
        }

        function offline() {
            stopSocket();
            setStale(true);
        }

        const listenerOptions = {
            signal: lifecycle.signal
        };

        document.addEventListener(
            'visibilitychange',
            visibilityChanged,
            listenerOptions
        );

        window.addEventListener(
            'online',
            online,
            listenerOptions
        );

        window.addEventListener(
            'offline',
            offline,
            listenerOptions
        );

        // Fallback only for authorized visible tabs.
        // No complete-page refresh.

        const fallbackTimer = setInterval(() => {
            if (!subscribed) {
                scheduleRefresh();
            }
        }, 30000);

        const validationTimer = setInterval(() => {
            if (subscribed) {
                scheduleRefresh();
            }
        }, 60000);

        connect();

        return {
            link,

            destroy() {
                disposed = true;

                lifecycle.abort();

                stopSocket();

                clearTimeout(refreshTimer);
                clearInterval(fallbackTimer);
                clearInterval(validationTimer);
            }
        };
    }

    function unmount() {
        if (current) {
            current.destroy();
            current = null;
        }
    }

    window[globalKey] = {
        mount,
        unmount
    };

    document.addEventListener(
        'livewire:navigating',
        unmount
    );

    document.addEventListener(
        'livewire:navigated',
        mount
    );

    window.addEventListener(
        'pagehide',
        unmount
    );

    window.addEventListener(
        'pageshow',
        mount
    );

    mount();
})();

</script>
{{-- BEXIA_RRHH_SEG02_REALTIME_END --}}
