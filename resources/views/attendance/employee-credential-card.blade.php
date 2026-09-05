<div class="credential-card">
    <div class="credential-header">
        <div class="brand">BEXIA</div>
        <div class="company">{{ $card['company'] }}</div>
    </div>

    <div class="credential-body">
        <div class="photo-frame">
            <img src="{{ $card['photo_data_uri'] }}" alt="Foto">
        </div>

        <div class="employee-info">
            <div class="employee-name">{{ $card['name'] }}</div>

            @if($card['employee_number'] !== '')
                <div class="employee-number">No. {{ $card['employee_number'] }}</div>
            @endif

            <div class="employee-position">{{ $card['position'] }}</div>

            @if($card['branch'] !== '')
                <div class="employee-branch">{{ $card['branch'] }}</div>
            @endif
        </div>
    </div>

    <div class="credential-qr-area">
        <div class="qr-box">
            <img src="{{ $card['qr_data_uri'] }}" alt="QR asistencia">
        </div>

        <div class="qr-copy">
            <div class="qr-title">CONTROL DE ASISTENCIA</div>
            <div class="qr-instruction">Pasa esta tarjeta por el lector del checador.</div>
            <div class="qr-security">QR personal. No compartir.</div>
        </div>
    </div>
</div>
