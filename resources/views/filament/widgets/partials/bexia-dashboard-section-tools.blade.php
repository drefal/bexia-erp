<style id="bexia-dashboard-section-tools-style">
    .bexia-section-shell {
        border-radius: 18px;
        border: 1px solid rgba(148, 163, 184, .30);
        box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
    }

    .bexia-section-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }

    .bexia-section-button {
        border: 1px solid rgba(148, 163, 184, .48);
        border-radius: 10px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 700;
        background: rgba(255, 255, 255, .90);
        color: #0f172a;
        cursor: pointer;
        transition: all .15s ease;
    }

    .bexia-section-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(15, 23, 42, .08);
    }

    [data-bexia-section-item] {
        border-radius: 18px;
        padding: 10px;
        transition: opacity .18s ease, transform .18s ease, background .18s ease;
    }

    [data-bexia-section-item] .fi-section,
    [data-bexia-section-item] .fi-wi-stats-overview-stat {
        box-shadow: none !important;
    }

    [data-bexia-section-item="rrhh"] {
        background: #faf5ff !important;
        border: 1px solid #e9d5ff !important;
    }

    [data-bexia-section-item="rrhh"] .fi-section,
    [data-bexia-section-item="rrhh"] .fi-wi-stats-overview-stat {
        background: #faf5ff !important;
        border-color: #e9d5ff !important;
    }

    [data-bexia-section-item="contabilidad"] {
        background: #eff6ff !important;
        border: 1px solid #bfdbfe !important;
    }

    [data-bexia-section-item="contabilidad"] .fi-section,
    [data-bexia-section-item="contabilidad"] .fi-wi-stats-overview-stat {
        background: #eff6ff !important;
        border-color: #bfdbfe !important;
    }

    [data-bexia-section-item="tesoreria"] {
        background: #f0fdf4 !important;
        border: 1px solid #bbf7d0 !important;
    }

    [data-bexia-section-item="tesoreria"] .fi-section,
    [data-bexia-section-item="tesoreria"] .fi-wi-stats-overview-stat {
        background: #f0fdf4 !important;
        border-color: #bbf7d0 !important;
    }

    @media print {
        .bexia-section-toolbar {
            display: none !important;
        }
    }
</style>
