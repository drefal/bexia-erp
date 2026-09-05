<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bexia - Control de asistencia</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: #f3f4f6;
            color: #111827;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }
        .shell {
            width: min(1080px, 100%);
            background: #fff;
            border-radius: 28px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .14);
            overflow: hidden;
        }
        .header {
            padding: 22px 30px 14px;
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
        }
        .brand { font-size: clamp(34px, 5vw, 58px); font-weight: 900; letter-spacing: .12em; }
        .subtitle { margin-top: 2px; font-size: clamp(15px, 2vw, 21px); font-weight: 700; color: #4b5563; }
        .body { padding: 24px 28px 18px; text-align: center; }
        .clock { font-size: clamp(42px, 7vw, 76px); font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1; }
        .date { margin-top: 7px; font-size: 16px; color: #6b7280; }
        .panel {
            margin: 22px auto 0;
            max-width: 900px;
            padding: 22px;
            border-radius: 22px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
        }
        .state { font-size: 15px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: #6b7280; }
        .pair-code { margin: 12px 0; font-size: clamp(50px, 9vw, 88px); font-weight: 900; letter-spacing: .14em; font-variant-numeric: tabular-nums; }
        .terminal-name { margin-top: 10px; font-size: clamp(24px, 3vw, 38px); font-weight: 800; }
        .meta { margin-top: 7px; font-size: 15px; color: #4b5563; }
        .message { margin-top: 8px; font-size: 17px; line-height: 1.4; color: #374151; }
        .error { color: #b91c1c; font-weight: 700; }
        .ready-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 250px;
            gap: 22px;
            align-items: center;
            margin-top: 16px;
        }
        .scan-box {
            min-height: 185px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border: 2px dashed #d1d5db;
            border-radius: 20px;
            padding: 20px;
            background: #fff;
        }
        .scan-title { font-size: clamp(30px, 4vw, 48px); font-weight: 900; letter-spacing: .03em; }
        .scan-subtitle { margin-top: 8px; color: #6b7280; font-size: 16px; }
        .camera-wrap {
            position: relative;
            width: 250px;
            height: 188px;
            border-radius: 18px;
            overflow: hidden;
            background: #111827;
            border: 1px solid #d1d5db;
        }
        #cameraVideo { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
        .camera-label {
            position: absolute;
            left: 8px;
            right: 8px;
            bottom: 8px;
            padding: 5px 8px;
            background: rgba(17, 24, 39, .75);
            color: #fff;
            border-radius: 8px;
            font-size: 12px;
        }
        .status-line {
            margin-top: 18px;
            display: flex;
            justify-content: center;
            gap: 18px;
            flex-wrap: wrap;
            font-size: 13px;
            color: #6b7280;
        }
        .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; background: #9ca3af; }
        .dot.ok { background: #16a34a; }
        .dot.bad { background: #dc2626; }
        .dot.warn { background: #d97706; }
        .small { margin-top: 12px; font-size: 12px; color: #9ca3af; }
        .overlay {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
            background: rgba(15, 23, 42, .72);
            z-index: 1000;
        }
        .overlay[hidden] { display: none; }
        .result-card {
            width: min(680px, 94vw);
            border-radius: 28px;
            background: #fff;
            padding: 42px 34px;
            text-align: center;
            box-shadow: 0 30px 90px rgba(0,0,0,.25);
        }
        .result-card.success { border-top: 12px solid #16a34a; }
        .result-card.failure { border-top: 12px solid #dc2626; }
        .result-kicker { font-size: 20px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
        .result-direction { margin-top: 12px; font-size: clamp(48px, 9vw, 84px); font-weight: 900; }
        .result-name { margin-top: 10px; font-size: clamp(25px, 4vw, 38px); font-weight: 800; }
        .result-time { margin-top: 8px; font-size: 28px; font-variant-numeric: tabular-nums; color: #4b5563; }
        .result-message { margin-top: 12px; font-size: 19px; color: #4b5563; }
        @media (max-width: 720px) {
            body { padding: 8px; align-items: flex-start; }
            .body { padding: 18px 14px; }
            .ready-grid { grid-template-columns: 1fr; }
            .camera-wrap { margin: 0 auto; width: min(300px, 100%); }
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="header">
        <div class="brand">BEXIA</div>
        <div class="subtitle">CONTROL DE ASISTENCIA</div>
    </div>

    <div class="body">
        <div id="clock" class="clock">--:--:--</div>
        <div id="date" class="date">--</div>

        <div id="panel" class="panel">
            <div id="state" class="state">Iniciando terminal</div>
            <div id="pairCode" class="pair-code" hidden></div>
            <div id="terminalName" class="terminal-name" hidden></div>
            <div id="terminalMeta" class="meta" hidden></div>
            <div id="message" class="message">Comprobando configuracion...</div>

            <div id="readyArea" class="ready-grid" hidden>
                <div class="scan-box">
                    <div id="scanTitle" class="scan-title">PASA TU TARJETA</div>
                    <div id="scanSubtitle" class="scan-subtitle">Acerca el QR al lector. La entrada o salida se determina automaticamente.</div>
                </div>
                <div class="camera-wrap">
                    <video id="cameraVideo" autoplay playsinline muted></video>
                    <div id="cameraLabel" class="camera-label">Preparando camara frontal...</div>
                </div>
            </div>
        </div>

        <div class="status-line">
            <span><span id="networkDot" class="dot"></span><span id="networkText">Comprobando red</span></span>
            <span><span id="cameraDot" class="dot"></span><span id="cameraText">Camara: iniciando</span></span>
            <span><span id="scannerDot" class="dot"></span><span id="scannerText">Lector QR: esperando terminal</span></span>
        </div>

        <div class="small">UUID + token seguro identifican la tablet. La fotografia de cada marcacion se guarda como evidencia privada.</div>
    </div>
</div>

<div id="resultOverlay" class="overlay" hidden>
    <div id="resultCard" class="result-card success">
        <div id="resultKicker" class="result-kicker">REGISTRO CORRECTO</div>
        <div id="resultDirection" class="result-direction">ENTRADA</div>
        <div id="resultName" class="result-name"></div>
        <div id="resultTime" class="result-time"></div>
        <div id="resultMessage" class="result-message"></div>
    </div>
</div>

<canvas id="captureCanvas" hidden></canvas>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const uuidKey = 'bexia_attendance_terminal_uuid';
    const tokenKey = 'bexia_attendance_terminal_token';

    const stateEl = document.getElementById('state');
    const pairCodeEl = document.getElementById('pairCode');
    const terminalNameEl = document.getElementById('terminalName');
    const terminalMetaEl = document.getElementById('terminalMeta');
    const messageEl = document.getElementById('message');
    const readyArea = document.getElementById('readyArea');
    const scanTitle = document.getElementById('scanTitle');
    const scanSubtitle = document.getElementById('scanSubtitle');

    const networkDot = document.getElementById('networkDot');
    const networkText = document.getElementById('networkText');
    const cameraDot = document.getElementById('cameraDot');
    const cameraText = document.getElementById('cameraText');
    const scannerDot = document.getElementById('scannerDot');
    const scannerText = document.getElementById('scannerText');

    const video = document.getElementById('cameraVideo');
    const cameraLabel = document.getElementById('cameraLabel');
    const canvas = document.getElementById('captureCanvas');

    const resultOverlay = document.getElementById('resultOverlay');
    const resultCard = document.getElementById('resultCard');
    const resultKicker = document.getElementById('resultKicker');
    const resultDirection = document.getElementById('resultDirection');
    const resultName = document.getElementById('resultName');
    const resultTime = document.getElementById('resultTime');
    const resultMessage = document.getElementById('resultMessage');

    let pairing = null;
    let pairingTimer = null;
    let heartbeatTimer = null;
    let terminalReady = false;
    let currentTerminal = null;
    let cameraStream = null;
    let cameraReady = false;
    let processingScan = false;
    let scanBuffer = '';
    let scanLastKeyAt = 0;
    let scanCommitTimer = null;
    let overlayTimer = null;

    function updateClock() {
        const now = new Date();
        document.getElementById('clock').textContent = new Intl.DateTimeFormat('es-MX', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
        }).format(now);
        document.getElementById('date').textContent = new Intl.DateTimeFormat('es-MX', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
        }).format(now);
    }

    function updateNetwork() {
        const online = navigator.onLine;
        networkDot.className = 'dot ' + (online ? 'ok' : 'bad');
        networkText.textContent = online ? 'En linea' : 'Sin conexion';
    }

    function setCameraStatus(status, text) {
        cameraDot.className = 'dot ' + status;
        cameraText.textContent = text;
        cameraLabel.textContent = text;
    }

    function setScannerStatus(status, text) {
        scannerDot.className = 'dot ' + status;
        scannerText.textContent = text;
    }

    async function postJson(url, body = {}, extraHeaders = {}) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                ...extraHeaders,
            },
            body: JSON.stringify(body),
        });

        let data = {};
        try { data = await response.json(); } catch (_) {}
        return { response, data };
    }

    function stopCamera() {
        if (cameraStream) {
            for (const track of cameraStream.getTracks()) track.stop();
        }
        cameraStream = null;
        cameraReady = false;
        video.srcObject = null;
        setCameraStatus('warn', 'Camara: detenida');
    }

    async function startCamera() {
        if (! terminalReady) return false;
        if (cameraReady && cameraStream) return true;

        if (! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia) {
            cameraReady = false;
            setCameraStatus('bad', 'Camara: navegador no compatible');
            return false;
        }

        try {
            stopCamera();
            setCameraStatus('warn', 'Camara: solicitando permiso');

            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'user' },
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
                audio: false,
            });

            video.srcObject = cameraStream;
            await video.play();

            if (video.readyState < 2) {
                await new Promise(resolve => video.addEventListener('loadeddata', resolve, { once: true }));
            }

            cameraReady = video.videoWidth > 0 && video.videoHeight > 0;
            setCameraStatus(cameraReady ? 'ok' : 'bad', cameraReady ? 'Camara: frontal activa' : 'Camara: sin imagen');
            return cameraReady;
        } catch (error) {
            cameraReady = false;
            setCameraStatus('bad', 'Camara: permiso requerido');
            messageEl.className = 'message error';
            messageEl.textContent = 'Permite el acceso a la camara frontal. Sin fotografia no se puede registrar asistencia.';
            return false;
        }
    }

    function showPairing(code) {
        terminalReady = false;
        currentTerminal = null;
        stopCamera();
        readyArea.hidden = true;
        stateEl.textContent = 'Terminal no configurada';
        pairCodeEl.hidden = false;
        pairCodeEl.textContent = code;
        terminalNameEl.hidden = true;
        terminalMetaEl.hidden = true;
        messageEl.className = 'message';
        messageEl.textContent = 'En Bexia ve a RRHH > Terminales de asistencia, abre la terminal y pulsa "Vincular tablet". Captura este codigo.';
        setScannerStatus('warn', 'Lector QR: esperando vinculacion');
    }

    async function showReady(terminal) {
        terminalReady = true;
        currentTerminal = terminal || {};
        stateEl.textContent = 'Terminal vinculada';
        pairCodeEl.hidden = true;
        terminalNameEl.hidden = false;
        terminalMetaEl.hidden = false;
        readyArea.hidden = false;
        terminalNameEl.textContent = terminal.name || terminal.code || 'Terminal de asistencia';
        const company = terminal.company?.name || 'Empresa sin nombre';
        const branch = terminal.branch?.name || 'Sucursal sin nombre';
        terminalMetaEl.textContent = `${company} · ${branch} · ${terminal.code || ''}`;
        messageEl.className = 'message';
        messageEl.textContent = 'Lista para registrar asistencia.';
        scanTitle.textContent = 'PASA TU TARJETA';
        scanSubtitle.textContent = 'Acerca el QR al lector. La entrada o salida se determina automaticamente.';
        setScannerStatus('ok', 'Lector QR: esperando tarjeta');
        await startCamera();
    }

    function showError(message) {
        terminalReady = false;
        readyArea.hidden = true;
        stateEl.textContent = 'Atencion';
        pairCodeEl.hidden = true;
        terminalNameEl.hidden = true;
        terminalMetaEl.hidden = true;
        messageEl.className = 'message error';
        messageEl.textContent = message;
        setScannerStatus('bad', 'Lector QR: terminal no disponible');
    }

    async function requestPairing() {
        clearInterval(pairingTimer);
        pairingTimer = null;

        try {
            const { response, data } = await postJson('/asistencia/kiosco/vinculacion/solicitar', {
                device_name: 'Tablet de asistencia',
                device_model: navigator.userAgent,
                platform: navigator.platform || 'web',
                app_version: 'web-kiosk-v5.83.4D',
            });

            if (! response.ok || ! data.request_id || ! data.exchange_secret || ! data.pairing_code) {
                throw new Error(data.message || 'No se pudo generar el codigo de vinculacion.');
            }

            pairing = data;
            showPairing(data.pairing_code);
            pairingTimer = setInterval(checkPairingStatus, 2000);
        } catch (error) {
            showError(error.message || 'No se pudo iniciar la vinculacion.');
            setTimeout(requestPairing, 5000);
        }
    }

    async function checkPairingStatus() {
        if (! pairing) return;

        try {
            const { response, data } = await postJson('/asistencia/kiosco/vinculacion/estado', {
                request_id: pairing.request_id,
                exchange_secret: pairing.exchange_secret,
            });

            if (response.status === 202) return;

            if (response.ok && data.state === 'paired' && data.terminal_uuid && data.terminal_token) {
                localStorage.setItem(uuidKey, data.terminal_uuid);
                localStorage.setItem(tokenKey, data.terminal_token);
                clearInterval(pairingTimer);
                pairingTimer = null;
                pairing = null;
                await showReady(data.terminal || {});
                startHeartbeat();
                return;
            }

            if (response.status === 410) {
                clearInterval(pairingTimer);
                pairingTimer = null;
                pairing = null;
                await requestPairing();
                return;
            }

            if (response.status === 403) {
                clearInterval(pairingTimer);
                pairingTimer = null;
                showError(data.message || 'La terminal esta bloqueada.');
            }
        } catch (_) {
            updateNetwork();
        }
    }

    async function verifyTerminal() {
        const uuid = localStorage.getItem(uuidKey);
        const token = localStorage.getItem(tokenKey);

        if (! uuid || ! token) {
            await requestPairing();
            return false;
        }

        try {
            const { response, data } = await postJson('/asistencia/kiosco/terminal/estado', {}, {
                'X-Bexia-Terminal-UUID': uuid,
                'Authorization': `Bearer ${token}`,
            });

            if (response.ok && data.state === 'ready') {
                await showReady(data.terminal || {});
                return true;
            }

            if (response.status === 401) {
                localStorage.removeItem(uuidKey);
                localStorage.removeItem(tokenKey);
                await requestPairing();
                return false;
            }

            if (response.status === 403) {
                showError(data.message || 'Esta terminal esta bloqueada o desactivada.');
                return false;
            }

            showError(data.message || 'No se pudo validar esta terminal.');
            return false;
        } catch (_) {
            messageEl.className = 'message error';
            messageEl.textContent = 'Sin conexion con Bexia. La vinculacion guardada se conserva.';
            return false;
        }
    }

    function startHeartbeat() {
        clearInterval(heartbeatTimer);
        heartbeatTimer = setInterval(verifyTerminal, 60000);
    }

    async function capturePhotoBlob() {
        if (! cameraReady || ! video.videoWidth || ! video.videoHeight) {
            const ok = await startCamera();
            if (! ok) throw new Error('La camara frontal no esta disponible. Permite el acceso antes de registrar.');
        }

        const sourceWidth = video.videoWidth;
        const sourceHeight = video.videoHeight;
        const maxWidth = 1280;
        const scale = Math.min(1, maxWidth / sourceWidth);
        canvas.width = Math.max(320, Math.round(sourceWidth * scale));
        canvas.height = Math.max(240, Math.round(sourceHeight * scale));

        const ctx = canvas.getContext('2d', { alpha: false });
        ctx.save();
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        ctx.restore();

        return await new Promise((resolve, reject) => {
            canvas.toBlob(blob => {
                if (blob) resolve(blob);
                else reject(new Error('No fue posible capturar la fotografia.'));
            }, 'image/jpeg', 0.82);
        });
    }

    function firstErrorMessage(data, fallback) {
        if (data?.message) return data.message;
        if (data?.errors) {
            for (const value of Object.values(data.errors)) {
                if (Array.isArray(value) && value.length) return value[0];
            }
        }
        return fallback;
    }

    function showResult(success, data = {}) {
        clearTimeout(overlayTimer);
        resultCard.className = 'result-card ' + (success ? 'success' : 'failure');
        resultKicker.textContent = success ? 'REGISTRO CORRECTO' : 'NO SE REGISTRO';
        resultDirection.textContent = success ? (data.direction_label || 'OK') : 'ATENCION';
        resultName.textContent = success ? (data.employee_name || '') : '';
        resultTime.textContent = success ? (data.time || '') : '';
        resultMessage.textContent = success ? 'Puedes retirar tu tarjeta.' : (data.message || 'Intenta nuevamente.');
        resultOverlay.hidden = false;

        overlayTimer = setTimeout(() => {
            resultOverlay.hidden = true;
            processingScan = false;
            scanTitle.textContent = 'PASA TU TARJETA';
            scanSubtitle.textContent = 'Acerca el QR al lector. La entrada o salida se determina automaticamente.';
            setScannerStatus(terminalReady ? 'ok' : 'bad', terminalReady ? 'Lector QR: esperando tarjeta' : 'Lector QR: terminal no disponible');
        }, success ? 3000 : 4200);
    }

    async function processEmployeeQr(rawQr) {
        rawQr = String(rawQr || '').trim();
        if (! rawQr || processingScan || ! terminalReady) return;

        processingScan = true;
        scanTitle.textContent = 'REGISTRANDO...';
        scanSubtitle.textContent = 'Mira hacia la camara. No retires tu tarjeta todavia.';
        setScannerStatus('warn', 'Lector QR: procesando');

        try {
            if (! navigator.onLine) throw new Error('Sin conexion. No se registro la asistencia.');

            const uuid = localStorage.getItem(uuidKey);
            const token = localStorage.getItem(tokenKey);
            if (! uuid || ! token) throw new Error('La terminal perdio su vinculacion.');

            const photoBlob = await capturePhotoBlob();
            const formData = new FormData();
            formData.append('employee_qr', rawQr);
            formData.append('photo', photoBlob, 'attendance.jpg');

            const response = await fetch('/asistencia/kiosco/registrar', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Bexia-Terminal-UUID': uuid,
                    'Authorization': `Bearer ${token}`,
                },
                body: formData,
            });

            let data = {};
            try { data = await response.json(); } catch (_) {}

            if (response.ok && data.ok) {
                setScannerStatus('ok', 'Lector QR: lectura correcta');
                showResult(true, data);
                return;
            }

            if (response.status === 401) {
                localStorage.removeItem(uuidKey);
                localStorage.removeItem(tokenKey);
                throw new Error('La vinculacion de esta tablet ya no es valida. Se requiere vincular nuevamente.');
            }

            throw new Error(firstErrorMessage(data, 'No fue posible registrar la asistencia.'));
        } catch (error) {
            showResult(false, { message: error.message || 'No fue posible registrar la asistencia.' });
        }
    }

    function commitScannerBuffer() {
        clearTimeout(scanCommitTimer);
        scanCommitTimer = null;
        const value = scanBuffer.trim();
        scanBuffer = '';
        if (value.length >= 6) processEmployeeQr(value);
    }

    document.addEventListener('keydown', event => {
        if (! terminalReady || processingScan || resultOverlay.hidden === false) return;
        if (event.ctrlKey || event.altKey || event.metaKey) return;

        const now = performance.now();
        if (now - scanLastKeyAt > 220) scanBuffer = '';
        scanLastKeyAt = now;

        if (event.key === 'Enter' || event.key === 'Tab') {
            event.preventDefault();
            commitScannerBuffer();
            return;
        }

        if (event.key.length === 1) {
            scanBuffer += event.key;
            if (scanBuffer.length > 2048) scanBuffer = scanBuffer.slice(-2048);
            clearTimeout(scanCommitTimer);
            scanCommitTimer = setTimeout(commitScannerBuffer, 180);
            setScannerStatus('ok', 'Lector QR: leyendo');
        }
    }, true);

    async function boot() {
        updateClock();
        updateNetwork();
        setInterval(updateClock, 1000);
        window.addEventListener('online', updateNetwork);
        window.addEventListener('offline', updateNetwork);
        document.addEventListener('visibilitychange', () => {
            if (! document.hidden && terminalReady && ! cameraReady) startCamera();
        });

        const ready = await verifyTerminal();
        if (ready) startHeartbeat();
    }

    boot();
})();
</script>
</body>
</html>
