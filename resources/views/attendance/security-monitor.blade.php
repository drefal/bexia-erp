<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport"
      content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Bexia - Seguridad</title>
<style>
:root {
    font-family: system-ui, sans-serif;
    color: #172033;
    background: #f1f5f9;
}
* { box-sizing: border-box; }
body { margin: 0; }
header {
    background: #10213d;
    color: white;
    padding: 18px 22px;
}
header h1 { margin: 0; font-size: 25px; }
header p { margin: 5px 0 0; opacity: .8; }
main {
    max-width: 1100px;
    margin: auto;
    padding: 18px;
}
.toolbar {
    display: flex;
    gap: 12px;
    margin-bottom: 14px;
}
input {
    width: 100%;
    font: inherit;
    padding: 14px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
}
.status {
    background: white;
    padding: 14px;
    margin-bottom: 14px;
    border-radius: 10px;
}
.entry {
    background: white;
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 10px;
    padding: 13px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}
.avatar {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    object-fit: cover;
    background: #e2e8f0;
}
.info { flex: 1; min-width: 0; }
.name {
    font-weight: 700;
    font-size: 18px;
}
.meta {
    font-size: 13px;
    color: #64748b;
    margin-top: 4px;
}
.tag {
    border-radius: 8px;
    background: #e2e8f0;
    padding: 8px;
    font-size: 12px;
    font-weight: 700;
    text-align: center;
}
.empty {
    padding: 28px;
    text-align: center;
    color: #64748b;
}
@media(max-width:600px) {
    .entry { gap: 10px; }
    .avatar { width: 44px; height: 44px; }
    .name { font-size: 15px; }
    .tag { font-size: 10px; }
}

.access-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}
.access-counter {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 6px;
    text-align: center;
}
.access-counter strong {
    display: block;
    font-size: 27px;
}
.access-counter span {
    display: block;
    font-size: 12px;
    color: #475569;
}
.entry {
    border-left: 5px solid #94a3b8;
}
.entry.access-authorized {
    background: #f0fdf4;
    border-left-color: #16a34a;
}
.entry.access-hold,
.entry.access-denied {
    background: #fef2f2;
    border-left-color: #dc2626;
}
.entry.access-review {
    background: #fffbeb;
    border-left-color: #d97706;
}
.entry.access-not_evaluated {
    background: #f8fafc;
    border-left-color: #94a3b8;
}
.access-authorized .tag {
    color: #166534;
    background: #dcfce7;
}
.access-hold .tag,
.access-denied .tag {
    color: #991b1b;
    background: #fee2e2;
}
.access-review .tag {
    color: #92400e;
    background: #fef3c7;
}
.access-not_evaluated .tag {
    color: #475569;
    background: #e2e8f0;
}
.access-reason {
    color: #334155;
    margin-top: 5px;
    font-size: 13px;
    font-weight: 600;
}
.avatar-fallback {
    flex-shrink: 0;
    display: grid;
    place-items: center;
    font-size: 17px;
    font-weight: 700;
    color: #475569;
}
.avatar-fallback img {
    grid-area: 1 / 1;
}
.avatar-fallback span {
    grid-area: 1 / 1;
}
@media(max-width:600px) {
    .access-reason { font-size: 11px; }
    .access-counter strong { font-size: 23px; }
    .entry .tag { max-width: 96px; }
}
</style>
</head>
<body>
<header>
    <h1>BEXIA | SEGURIDAD</h1>
    <p id="terminal">Validando terminal...</p>
</header>

<main>
    <div class="status" id="status">
        Conectando con Bexia...
    </div>

    <div class="status" id="connection"
         role="status" aria-live="polite">
        Iniciando conexion...
    </div>

    <section class="access-summary"
             aria-label="Resumen de entradas consultadas">
        <div class="access-counter">
            <strong id="count-authorized"
                    style="color:#15803d">0</strong>
            <span>Autorizados</span>
        </div>
        <div class="access-counter">
            <strong id="count-hold"
                    style="color:#dc2626">0</strong>
            <span>Retener / Denegar</span>
        </div>
        <div class="access-counter">
            <strong id="count-review"
                    style="color:#b45309">0</strong>
            <span>Revisar / Sin evaluar</span>
        </div>
    </section>

    <div class="toolbar">
        <input
            id="search"
            type="search"
            placeholder="Buscar en los registros mostrados"
            autocomplete="off"
        >
    </div>

    <div id="entries"></div>
</main>

<script>
(() => {
    'use strict';

    const uuidKey = 'bexia_attendance_terminal_uuid';
    const tokenKey = 'bexia_attendance_terminal_token';

    const statusEl = document.getElementById('status');
    const terminalEl = document.getElementById('terminal');
    const entriesEl = document.getElementById('entries');
    const searchEl = document.getElementById('search');

    let rows = [];
    let loading = false;

    const realtimeEnabled = @json((bool) config('attendance_realtime.enabled'));
    const realtimeKey = @json(config('attendance_realtime.key'));
    const csrfToken = @json(csrf_token());

    const connectionEl = document.getElementById('connection');

    let socket = null;
    let connected = false;
    let retryTimer = null;
    let retryDelay = 3000;
    let authTimer = null;
    let heartbeatTimer = null;
    let subscribedChannel = null;
    let refreshTimer = null;
    let lastRefresh = 0;
    let pendingRefresh = false;
    let terminalDenied = false;

    function credentials() {
        return {
            uuid: localStorage.getItem(uuidKey),
            token: localStorage.getItem(tokenKey)
        };
    }

    function connectionState(message, color) {
        connectionEl.textContent = message;
        connectionEl.style.color = color;
    }

    function clearRealtimeTimers() {
        clearTimeout(authTimer);
        clearTimeout(heartbeatTimer);
        authTimer = null;
        heartbeatTimer = null;
    }

    function stopRealtime() {
        connected = false;
        subscribedChannel = null;

        clearTimeout(retryTimer);
        retryTimer = null;

        clearRealtimeTimers();

        const previous = socket;
        socket = null;

        if (previous) {
            previous.onclose = null;
            previous.onmessage = null;
            previous.close();
        }
    }

    function scheduleRefresh() {
        pendingRefresh = true;

        if (loading || refreshTimer || document.hidden) {
            return;
        }

        const delay = Math.max(
            0,
            2500 - (Date.now() - lastRefresh)
        );

        refreshTimer = setTimeout(() => {
            refreshTimer = null;

            if (loading || document.hidden) {
                return;
            }

            pendingRefresh = false;
            lastRefresh = Date.now();

            refresh();
        }, delay);
    }

    function scheduleReconnect() {
        if (
            retryTimer ||
            terminalDenied ||
            !realtimeEnabled ||
            document.hidden
        ) {
            return;
        }

        retryTimer = setTimeout(() => {
            retryTimer = null;
            connectRealtime();
        }, retryDelay);

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

    async function authorizeSocket(ws, socketId) {
        const {uuid, token} = credentials();

        const response = await fetch(
            '/asistencia/seguridad/realtime/auth',
            {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                signal: AbortSignal.timeout(10000),
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Bexia-Terminal-UUID': uuid,
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    socket_id: socketId
                })
            }
        );

        if (socket !== ws) {
            return;
        }

        if (
            response.status === 401 ||
            response.status === 403
        ) {
            terminalDenied = true;
            rows = [];
            render();
            stopRealtime();

            connectionState(
                'Terminal no autorizada',
                '#dc2626'
            );

            return;
        }

        if (!response.ok) {
            throw new Error('Authorization failed');
        }

        const data = await response.json();

        if (socket !== ws) {
            return;
        }

        if (
            !/^private-attendance\.security\.\d+\.\d+$/
                .test(data.channel || '') ||
            typeof data.auth !== 'string'
        ) {
            throw new Error('Invalid authorization');
        }

        subscribedChannel = data.channel;

        ws.send(JSON.stringify({
            event: 'pusher:subscribe',
            data: {
                channel: data.channel,
                auth: data.auth
            }
        }));
    }

    function connectRealtime() {
        if (
            !realtimeEnabled ||
            !realtimeKey ||
            terminalDenied ||
            document.hidden ||
            location.protocol !== 'https:' ||
            socket
        ) {
            return;
        }

        const {uuid, token} = credentials();

        if (!uuid || !token) {
            return;
        }

        connectionState(
            'Conectando en tiempo real...',
            '#64748b'
        );

        const url =
            `wss://${location.host}/asistencia/ws/app/` +
            encodeURIComponent(realtimeKey) +
            '?protocol=7&client=js&version=8.4.0&flash=false';

        const ws = new WebSocket(url);
        socket = ws;

        authTimer = setTimeout(() => {
            if (socket === ws && !connected) {
                ws.close();
            }
        }, 15000);

        ws.onopen = () => touchHeartbeat(ws);

        ws.onmessage = async message => {
            if (socket !== ws) {
                return;
            }

            touchHeartbeat(ws);

            try {
                const event = JSON.parse(message.data);

                if (
                    event.event ===
                    'pusher:connection_established'
                ) {
                    const data =
                        typeof event.data === 'string'
                            ? JSON.parse(event.data)
                            : event.data;

                    await authorizeSocket(
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
                        event.channel !== subscribedChannel
                    ) {
                        ws.close();
                        return;
                    }

                    connected = true;
                    retryDelay = 3000;

                    clearTimeout(authTimer);

                    connectionState(
                        'Conectado en tiempo real',
                        '#15803d'
                    );

                    scheduleRefresh();

                    // Renovar autorizacion del canal.
                    authTimer = setTimeout(() => {
                        if (socket === ws) {
                            ws.close();
                        }
                    }, 300000);

                } else if (
                    event.event === 'attendance.entry.created' &&
                    event.channel === subscribedChannel &&
                    connected
                ) {
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
            connected = false;
            subscribedChannel = null;

            clearRealtimeTimers();

            connectionState(
                'Sin WebSocket · Consulta de respaldo',
                '#b45309'
            );

            scheduleReconnect();
        };
    }


    function element(tag, cls, value) {
        const node = document.createElement(tag);
        if (cls) node.className = cls;
        if (value !== undefined) node.textContent = value;
        return node;
    }

    const accessLabels = Object.freeze({
        authorized: 'AUTORIZADO',
        hold: 'RETENER',
        denied: 'DENEGADO',
        review: 'REVISAR',
        not_evaluated: 'SIN EVALUAR'
    });

    function accessStatus(row) {
        const value = String(row.access_status || '');

        return Object.prototype.hasOwnProperty.call(
            accessLabels,
            value
        ) ? value : 'review';
    }

    function render() {
        entriesEl.replaceChildren();

        const counters = {
            authorized: 0,
            hold: 0,
            review: 0
        };

        rows.forEach(row => {
            const status = accessStatus(row);

            if (status === 'authorized') {
                counters.authorized++;
            } else if (
                status === 'hold' ||
                status === 'denied'
            ) {
                counters.hold++;
            } else {
                counters.review++;
            }
        });

        Object.keys(counters).forEach(key => {
            document.getElementById(
                'count-' + key
            ).textContent = String(counters[key]);
        });

        const search = searchEl.value
            .trim()
            .toLocaleLowerCase();

        const filtered = rows.filter(row =>
            [
                row.name,
                row.employee_number,
                row.department
            ].some(value =>
                String(value || '')
                    .toLocaleLowerCase()
                    .includes(search)
            )
        );

        if (filtered.length === 0) {
            entriesEl.appendChild(
                element(
                    'div',
                    'empty',
                    'No hay entradas que coincidan.'
                )
            );
            return;
        }

        filtered.forEach(row => {
            const state = accessStatus(row);

            const card = element(
                'div',
                'entry access-' + state
            );

            const avatarWrap = element(
                'div',
                'avatar avatar-fallback'
            );

            const initials = String(row.name || '?')
                .trim()
                .split(/\s+/)
                .filter(Boolean)
                .slice(0, 2)
                .map(part => part.charAt(0).toUpperCase())
                .join('');

            const fallback = element(
                'span',
                '',
                initials || '?'
            );

            const avatar = element('img', 'avatar');

            avatar.hidden = true;

            avatar.addEventListener('load', () => {
                avatar.hidden = false;
                fallback.hidden = true;
            });

            avatar.addEventListener('error', () => {
                avatar.hidden = true;
                fallback.hidden = false;
            });

            avatarWrap.append(fallback, avatar);

            // Solo se admiten fotografias servidas por Bexia.
            if (row.photo) {
                try {
                    const url = new URL(row.photo, location.origin);
                    if (
                        url.origin === location.origin &&
                        ['http:', 'https:'].includes(url.protocol)
                    ) {
                        avatar.src = url.href;
                    }
                } catch (_) {}
            }

            avatar.alt = 'Fotografia del empleado';

            const info = element('div', 'info');
            info.appendChild(
                element('div', 'name', row.name)
            );

            info.appendChild(
                element(
                    'div',
                    'meta',
                    `${row.employee_number || 'Sin numero'} · ` +
                    `${row.department || 'Sin departamento'} · ` +
                    `${row.clock_in || 'Sin hora'}`
                )
            );

            info.appendChild(
                element(
                    'div',
                    'access-reason',
                    row.access_reason ||
                        'Verificar decision de acceso'
                )
            );

            const tag = element(
                'div',
                'tag',
                accessLabels[state]
            );

            card.append(avatarWrap, info, tag);
            entriesEl.appendChild(card);
        });
    }

    async function refresh() {
        if (loading || document.hidden) return;
        loading = true;

        try {
            const uuid = localStorage.getItem(uuidKey);
            const token = localStorage.getItem(tokenKey);

            if (!uuid || !token) {
                rows = [];
                render();
                stopRealtime();
                statusEl.textContent =
                    'Tablet no vinculada. Vincular desde /asistencia/kiosco.';
                return;
            }

            const response = await fetch(
                '/asistencia/seguridad/entradas',
                {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Bexia-Terminal-UUID': uuid,
                        'Authorization': `Bearer ${token}`
                    },
                    cache: 'no-store',
                    signal: AbortSignal.timeout(10000)
                }
            );

            const data = await response.json();

            if (!response.ok || !data.ok) {
                if (
                    response.status === 401 ||
                    response.status === 403
                ) {
                    terminalDenied = true;
                    stopRealtime();
                    connectionState(
                        'Terminal no autorizada',
                        '#dc2626'
                    );
                }

                rows = [];
                render();
                statusEl.textContent =
                    data.message || 'Terminal no autorizada.';
                return;
            }

            terminalDenied = false;

            if (!socket && !retryTimer) {
                connectRealtime();
            }

            const terminal = data.terminal || {};

            terminalEl.textContent = [
                terminal.name,
                terminal.company,
                terminal.branch
            ].filter(Boolean).join(' · ');

            rows = Array.isArray(data.entries)
                ? data.entries
                : [];

            statusEl.textContent =
                `${rows.length} entradas consultadas · ` +
                'Resumen de los registros consultados';

            render();
        } catch (_) {
            rows = [];
            render();

            statusEl.textContent =
                'No se pudo conectar. Reintentando...';
        } finally {
            loading = false;

            if (pendingRefresh) {
                scheduleRefresh();
            }
        }
    }

    searchEl.addEventListener('input', render);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            scheduleRefresh();
            connectRealtime();
        } else {
            stopRealtime();
        }
    });

    window.addEventListener('online', () => {
        scheduleRefresh();
        connectRealtime();
    });

    window.addEventListener('offline', () => {
        stopRealtime();
        rows = [];
        render();

        connectionState(
            'Sin conexion a Internet',
            '#dc2626'
        );
    });

    window.addEventListener('pagehide', stopRealtime);

    window.addEventListener('storage', event => {
        if (
            event.key === uuidKey ||
            event.key === tokenKey
        ) {
            stopRealtime();
            rows = [];
            render();
            terminalDenied = false;
            scheduleRefresh();
            connectRealtime();
        }
    });

    refresh().then(connectRealtime);

    // Respaldo y revalidacion de permisos.
    setInterval(() => {
        if (!connected) {
            scheduleRefresh();
        }
    }, 30000);

    // Revalidacion aun cuando WebSocket este activo.
    setInterval(() => {
        if (connected) {
            scheduleRefresh();
        }
    }, 60000);

    if (!realtimeEnabled) {
        connectionState(
            'Modo consulta de respaldo',
            '#64748b'
        );
    }
})();
</script>
</body>
</html>
