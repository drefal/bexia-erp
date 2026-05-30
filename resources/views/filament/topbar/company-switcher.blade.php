@php
    $user = auth()->user();
    $tenant = filament()->getTenant();

    $companies = collect();

    if ($user && method_exists($user, 'companies')) {
        $companies = $user->companies()
            ->where('companies.active', true)
            ->orderBy('companies.name')
            ->get();
    }

    $currentTenantId = $tenant && method_exists($tenant, 'getKey')
        ? (string) $tenant->getKey()
        : null;

    $currentLogoUrl = $tenant && method_exists($tenant, 'getFilamentAvatarUrl')
        ? $tenant->getFilamentAvatarUrl()
        : null;
@endphp

@once
    <style>
        .fi-sidebar .fi-tenant-menu {
            display: none !important;
        }

        .bexia-topbar-company-switcher {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            height: 2.25rem;
            margin-inline: .25rem;
            padding: .18rem .50rem .18rem .30rem;
            border: 1px solid rgb(191, 219, 254);
            border-radius: 9999px;
            background: linear-gradient(180deg, rgb(239, 246, 255), rgb(255, 255, 255));
            box-shadow: 0 8px 18px rgba(37, 99, 235, .08);
            color: rgb(29, 78, 216);
            min-width: 0;
            max-width: 18rem;
            overflow: hidden;
        }

        .bexia-topbar-company-switcher:hover {
            border-color: rgb(147, 197, 253);
            background: linear-gradient(180deg, rgb(239, 246, 255), rgb(248, 250, 252));
        }

        .bexia-topbar-company-switcher__logo-wrap {
            width: 1.45rem;
            height: 1.45rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #fff;
            border: 1px solid rgba(147, 197, 253, .8);
            flex: 0 0 auto;
        }

        .bexia-topbar-company-switcher__logo {
            width: 1.15rem;
            height: 1.15rem;
            object-fit: contain;
            display: block;
        }

        .bexia-topbar-company-switcher__fallback {
            font-size: .62rem;
            font-weight: 800;
            color: rgb(37, 99, 235);
            line-height: 1;
        }

        .bexia-topbar-company-switcher__field {
            display: flex;
            align-items: center;
            flex: 1 1 auto;
            min-width: 0;
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
        }

        .bexia-topbar-company-switcher__select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            flex: 1 1 auto;
            min-width: 8rem;
            max-width: 14rem;
            width: 100%;
            height: 1.6rem;
            border: 0 !important;
            outline: none !important;
            box-shadow: none !important;
            background: transparent !important;
            background-color: transparent !important;
            color: rgb(30, 64, 175);
            font-size: .76rem;
            font-weight: 700;
            line-height: 1rem;
            padding: .10rem 1.15rem .10rem .10rem;
            margin: 0;
            cursor: pointer;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .bexia-topbar-company-switcher__select:focus,
        .bexia-topbar-company-switcher__select:focus-visible,
        .bexia-topbar-company-switcher__select:hover,
        .bexia-topbar-company-switcher__select:active {
            border: 0 !important;
            outline: none !important;
            box-shadow: none !important;
            background: transparent !important;
            background-color: transparent !important;
        }

        .bexia-topbar-company-switcher__select::-ms-expand {
            display: none;
        }

        .bexia-topbar-company-switcher__arrow {
            width: .72rem;
            height: .72rem;
            margin-left: -.95rem;
            color: rgb(37, 99, 235);
            pointer-events: none;
            flex: 0 0 auto;
        }

        @media (max-width: 1100px) {
            .bexia-topbar-company-switcher {
                max-width: 14rem;
            }

            .bexia-topbar-company-switcher__select {
                max-width: 10.5rem;
                min-width: 7.5rem;
            }
        }

        @media (max-width: 760px) {
            .bexia-topbar-company-switcher {
                display: none;
            }
        }
    </style>
@endonce

@if ($companies->isNotEmpty() && $currentTenantId)
    <div class="bexia-topbar-company-switcher" title="Cambiar empresa">
        <span class="bexia-topbar-company-switcher__logo-wrap">
            @if ($currentLogoUrl)
                <img
                    src="{{ $currentLogoUrl }}"
                    alt=""
                    class="bexia-topbar-company-switcher__logo"
                >
            @else
                <span class="bexia-topbar-company-switcher__fallback">
                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($tenant?->name ?? 'E', 0, 1)) }}
                </span>
            @endif
        </span>

        <div class="bexia-topbar-company-switcher__field">
            <select
                class="bexia-topbar-company-switcher__select"
                aria-label="Cambiar empresa"
                onchange="if (this.value) window.location.href = this.value"
            >
                @foreach ($companies as $company)
                    <option
                        value="{{ url('/admin/' . $company->getKey()) }}"
                        @selected((string) $company->getKey() === $currentTenantId)
                    >
                        {{ \Illuminate\Support\Str::limit($company->name, 36) }}
                    </option>
                @endforeach
            </select>
        </div>

        <svg class="bexia-topbar-company-switcher__arrow" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
        </svg>
    </div>
@endif
