@php
    $credentialNameLength = function_exists('mb_strlen')
        ? mb_strlen($card['name'])
        : strlen($card['name']);

    $credentialNameClass = 'employee-name';

    if ($credentialNameLength >= 38) {
        $credentialNameClass .= ' employee-name--xs';
    } elseif ($credentialNameLength >= 29) {
        $credentialNameClass .= ' employee-name--sm';
    }
@endphp

<div class="credential-card">

    <div class="credential-header">

        @if(! empty($card['company_logo_data_uri']))

            <img
                class="company-logo"
                src="{{ $card['company_logo_data_uri'] }}"
                alt="{{ $card['company'] }}"
            >

        @else

            <div class="company-name">
                {{ $card['company'] }}
            </div>

        @endif

    </div>

    <div class="credential-body">

        <div class="photo-frame">
            <img
                src="{{ $card['photo_data_uri'] }}"
                alt="Foto"
            >
        </div>

        <div class="employee-info">

            <div class="{{ $credentialNameClass }}">
                {{ $card['name'] }}
            </div>

            @if($card['employee_number'] !== '')

                <div class="employee-number">
                    No. {{ $card['employee_number'] }}
                </div>

            @endif

            <div class="employee-position">
                {{ $card['position'] }}
            </div>

            @if($card['branch'] !== '')

                <div class="employee-branch">
                    {{ $card['branch'] }}
                </div>

            @endif

        </div>

    </div>

    <div class="credential-qr-area">

        <div class="qr-box">
            <img
                src="{{ $card['qr_data_uri'] }}"
                alt="QR asistencia"
            >
        </div>

    </div>

</div>
