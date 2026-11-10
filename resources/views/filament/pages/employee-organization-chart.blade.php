<x-filament-panels::page>
    @php
        $chart = $this->chartPayload();
        $byId = [];
        $children = [];
        $roots = [];

        foreach ($chart['nodes'] as $row) {
            $byId[(int) $row['id']] = $row;
        }

        foreach ($chart['nodes'] as $row) {
            $id = (int) $row['id'];
            $ancestors = $row['ancestors'] ?? [];

            if (empty($ancestors)) {
                $roots[] = $id;
                continue;
            }

            $parentId = (int) end($ancestors);
            $children[$parentId][] = $id;
        }

        $managerIds = array_keys($children);
        $initialCollapsed = [];

        foreach ($managerIds as $id) {
            if (($byId[$id]['depth'] ?? 0) >= 1) {
                $initialCollapsed[] = (int) $id;
            }
        }
    @endphp

    <style>
        [x-cloak] { display: none !important; }

        .orgx-page {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .orgx-hero {
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
            padding: 22px 24px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
        }

        .orgx-hero-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
        }

        .orgx-title {
            font-size: 1.8rem;
            line-height: 1.2;
            font-weight: 800;
            color: #0f172a;
        }

        .orgx-subtitle {
            margin-top: 6px;
            color: #64748b;
            font-size: .92rem;
        }

        .orgx-total {
            min-width: 180px;
            text-align: right;
        }

        .orgx-total-number {
            font-size: 2.2rem;
            line-height: 1;
            font-weight: 800;
            color: #1d4ed8;
        }

        .orgx-total-label {
            margin-top: 4px;
            color: #64748b;
            font-size: .88rem;
        }

        .orgx-areas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
        }

        .orgx-area {
            border: 1px solid #dbe4f0;
            border-radius: 16px;
            background: #fff;
            padding: 14px 16px;
            text-align: left;
            box-shadow: 0 3px 10px rgba(15, 23, 42, .03);
            transition: .15s ease;
        }

        .orgx-area:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(15, 23, 42, .06);
        }

        .orgx-area-selected {
            border-color: #2563eb !important;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, .12);
            background: #f8fbff;
        }

        .orgx-area-name {
            font-size: .86rem;
            font-weight: 700;
            color: #334155;
        }

        .orgx-area-count {
            margin-top: 6px;
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
        }

        .orgx-toolbar {
            border: 1px solid #dbe4f0;
            border-radius: 16px;
            background: #fff;
            padding: 16px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, .04);
        }

        .orgx-toolbar-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .orgx-search {
            flex: 1 1 320px;
            min-width: 0;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 11px 14px;
            font-size: .9rem;
            background: #fff;
            color: #0f172a;
        }

        .orgx-btn {
            border: 1px solid #cbd5e1;
            background: #fff;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: .84rem;
            font-weight: 700;
            color: #334155;
            transition: .15s ease;
        }

        .orgx-btn:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        .orgx-btn-primary {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }

        .orgx-btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }

        .orgx-hint {
            margin-top: 10px;
            color: #64748b;
            font-size: .82rem;
        }

        .orgx-canvas {
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            background:
                radial-gradient(#e7eef7 0.7px, transparent 0.7px),
                #fbfdff;
            background-size: 18px 18px;
            padding: 22px;
            overflow-x: auto;
        }

        .org-roots,
        .org-children {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .org-roots {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .org-node {
            position: relative;
        }

        .org-node-root > .org-card {
            margin-left: 0;
        }

        .org-children {
            position: relative;
            margin-top: 10px;
            margin-left: 32px;
            padding-left: 26px;
            border-left: 2px solid #c7d6ea;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .org-children > .org-node::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 36px;
            width: 26px;
            border-top: 2px solid #c7d6ea;
        }

        .org-card {
            width: min(100%, 860px);
            border: 1px solid #d7e3f2;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 6px 20px rgba(15, 23, 42, .05);
            padding: 16px 18px;
        }

        .org-card-root {
            background: linear-gradient(135deg, #eef5ff 0%, #f8fbff 100%);
            border-color: #8cb4f7;
        }

        .org-card-match {
            box-shadow: 0 0 0 2px rgba(37, 99, 235, .18);
            border-color: #2563eb;
        }

        .org-card-main {
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }

        .org-avatar {
            width: 52px;
            height: 52px;
            border-radius: 999px;
            background: linear-gradient(135deg, #2563eb 0%, #60a5fa 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .95rem;
            font-weight: 800;
            flex: 0 0 52px;
            box-shadow: 0 6px 16px rgba(37, 99, 235, .25);
        }

        .org-card-body {
            min-width: 0;
            flex: 1 1 auto;
        }

        .org-card-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .org-person-name {
            font-size: 1rem;
            font-weight: 800;
            line-height: 1.3;
            color: #0f172a;
            overflow-wrap: anywhere;
        }

        .org-person-position {
            margin-top: 4px;
            color: #475569;
            font-size: .88rem;
        }

        .org-toggle {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 1.1rem;
            font-weight: 800;
            flex: 0 0 34px;
        }

        .org-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 11px;
        }

        .org-badge-area,
        .org-badge-muted,
        .org-badge-count {
            border-radius: 999px;
            padding: 5px 10px;
            font-size: .72rem;
            line-height: 1.1;
        }

        .org-badge-area {
            background: #eaf2ff;
            color: #1e40af;
            font-weight: 700;
        }

        .org-badge-muted {
            background: #f1f5f9;
            color: #475569;
        }

        .org-badge-count {
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 700;
        }

        .orgx-footer {
            color: #64748b;
            font-size: .8rem;
            text-align: center;
        }

        @media print {
            .orgx-toolbar,
            .orgx-areas,
            .fi-topbar,
            .fi-sidebar,
            .fi-header {
                display: none !important;
            }

            .orgx-canvas,
            .org-card,
            .orgx-hero {
                box-shadow: none !important;
            }

            .orgx-page {
                gap: .6rem;
            }

            .org-card {
                break-inside: avoid;
            }
        }

        .dark .orgx-hero,
        .dark .orgx-area,
        .dark .orgx-toolbar,
        .dark .org-card {
            background: #111827;
            border-color: #374151;
        }

        .dark .orgx-title,
        .dark .orgx-area-count,
        .dark .org-person-name {
            color: #f9fafb;
        }

        .dark .orgx-subtitle,
        .dark .orgx-total-label,
        .dark .orgx-hint,
        .dark .orgx-area-name,
        .dark .org-person-position,
        .dark .orgx-footer {
            color: #cbd5e1;
        }

        .dark .orgx-canvas {
            background:
                radial-gradient(#243041 0.7px, transparent 0.7px),
                #0b1220;
            border-color: #374151;
        }

        .dark .org-badge-area {
            background: #1e3a8a;
            color: #dbeafe;
        }

        .dark .org-badge-muted {
            background: #374151;
            color: #e5e7eb;
        }

        .dark .org-badge-count {
            background: #172554;
            color: #bfdbfe;
        }

        .dark .orgx-search,
        .dark .orgx-btn {
            background: #111827;
            color: #f9fafb;
            border-color: #4b5563;
        }

        @media (max-width: 640px) {
            .orgx-total {
                text-align: left;
            }

            .orgx-title {
                font-size: 1.4rem;
            }

            .org-card {
                padding: 14px;
            }

            .org-children {
                margin-left: 14px;
                padding-left: 14px;
            }

            .org-children > .org-node::before {
                left: -14px;
                width: 14px;
            }
        }

        /* ORG02 FIX4 - Arbol ejecutivo vertical */
        .orgx-page { gap: 14px; }

        .orgx-hero {
            border-radius: 16px;
            background: linear-gradient(120deg,#eff6ff,#ffffff);
            border-top: 4px solid #1d4ed8;
            padding: 16px 20px;
        }

        .orgx-title {
            font-size: 1.5rem;
            letter-spacing: -.025em;
        }

        .orgx-areas {
            grid-template-columns: repeat(auto-fit,minmax(135px,1fr));
            gap: 8px;
        }

        .orgx-area {
            padding: 10px 12px;
            border-radius: 10px;
        }

        .orgx-area-count {
            margin-top: 2px;
            font-size: 1.3rem;
        }

        .orgx-toolbar {
            padding: 12px;
            border-radius: 12px;
        }

        .orgx-canvas {
            display: block;
            padding: 20px 12px;
            overflow-x: auto;
        }

        .org-roots,.org-children {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            margin: 0;
            padding: 0;
            list-style: none;
            border-left: 0;
        }

        .org-roots { width: 100%; }

        .org-node,
        .org-node-root {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            margin: 0;
        }

        .org-children {
            position: relative;
            width: min(100%, 650px);
            margin: 0;
            padding: 22px 12px 12px;
            border: 1px solid #dbeafe;
            border-radius: 14px;
            background: rgba(239,246,255,.35);
        }

        .org-children::before {
            content: '';
            position: absolute;
            top: -1px;
            left: 50%;
            height: 22px;
            border-left: 2px solid #93b4dd;
        }

        .org-children > .org-node::before {
            content: '';
            position: absolute;
            top: -12px;
            left: 50%;
            width: 0;
            height: 12px;
            border-top: 0;
            border-left: 2px solid #93b4dd;
        }

        .org-children > .org-node:first-child::before {
            display: none;
        }

        .org-card {
            width: min(100%, 510px);
            padding: 10px 13px;
            border-radius: 12px;
            border-left: 3px solid #bfd6f8;
            box-shadow: 0 2px 7px rgba(15,23,42,.06);
        }

        .org-card-root {
            border-left: 4px solid #1d4ed8;
            background: #eef5ff;
        }

        .org-card-main { gap: 10px; align-items: center; }

        .org-avatar {
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            overflow: hidden;
            border: 2px solid #dbeafe;
            box-shadow: none;
        }

        .org-person-name {
            font-size: .91rem;
            line-height: 1.25;
        }

        .org-person-position {
            margin-top: 2px;
            font-size: .78rem;
        }

        .org-meta { margin-top: 5px; gap: 5px; }

        .org-badge-area,.org-badge-count {
            padding: 3px 7px;
            font-size: .66rem;
        }

        .org-toggle {
            width: 28px;
            height: 28px;
            flex: 0 0 28px;
            border-radius: 7px;
        }

        @media(max-width:640px) {
            .org-children { padding: 20px 6px 8px; }
            .org-card { width: 100%; }
        }


        /* ORG02 FIX5 - Tres columnas */
        .orgx-canvas {
            overflow-x: auto;
            padding: 20px 18px;
        }

        .org-roots {
            width: 100%;
            align-items: stretch;
        }

        .org-node-root {
            width: 100%;
            align-items: center;
        }

        .org-node-root > .org-card {
            width: min(100%, 510px);
            margin-inline: auto;
        }

        /* Los reportes directos de la direccion
           se acomodan en tres columnas. */
        .org-node-root > .org-children {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            align-items: start;
            gap: 16px 18px;
            width: 100%;
            max-width: none;
            margin-top: 0;
            padding: 24px 12px 12px;
            background: rgba(239, 246, 255, .28);
            border: 1px solid #dbeafe;
            border-radius: 14px;
        }

        /* Conexion vertical desde la direccion. */
        .org-node-root > .org-children::before {
            left: 50%;
            height: 24px;
            top: -1px;
        }

        .org-node-root > .org-children > .org-node {
            width: 100%;
            min-width: 0;
            align-items: stretch;
        }

        .org-node-root > .org-children > .org-node::before {
            display: none;
        }

        .org-node-root > .org-children > .org-node > .org-card {
            width: 100%;
            min-width: 0;
            margin: 0;
        }

        /* Cada responsable conserva a su equipo
           debajo de su propia tarjeta. */
        .org-node-root > .org-children
        > .org-node > .org-children {
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: none;
            gap: 9px;
            padding: 20px 7px 8px;
            margin-top: 4px;
        }

        .org-node-root > .org-children
        > .org-node > .org-children .org-card {
            width: 100%;
            min-width: 0;
        }

        /* Segundo nivel: mantener conexiones
           entre cada responsable y su equipo. */
        .org-node-root > .org-children
        > .org-node > .org-children::before {
            display: block;
            height: 20px;
        }

        @media (max-width: 1100px) {
            .org-node-root > .org-children {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .orgx-canvas {
                padding: 12px 8px;
            }

            .org-node-root > .org-children {
                grid-template-columns: minmax(0, 1fr);
                padding: 20px 8px 8px;
                gap: 12px;
            }
        }


        /* ORG02 FIX6 - Equipos */
        .org-children {
            position: relative;
        }

        .org-team-label {
            list-style: none;
            width: 100%;
            min-width: 0;
            padding: 7px 10px;
            margin: 0 0 5px;
            border-radius: 8px;
            border: 1px solid #bfd6f8;
            background: #eaf3ff;
            color: #1e40af;
            font-size: .73rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 5px;
        }

        .org-team-label-name {
            font-weight: 800;
        }

        .org-team-label-count {
            font-size: .68rem;
            color: #475569;
        }

        /* El encabezado de direccion ocupa las tres
           columnas sin desplazar a los colaboradores. */
        .org-node-root > .org-children > .org-team-label {
            grid-column: 1 / -1;
            text-align: center;
            justify-content: center;
            gap: 10px;
        }

        /* Cada responsable tiene su propio bloque
           de subordinados, separado de sus hermanos. */
        .org-node:not(.org-node-root) > .org-children {
            border: 1px solid #bed4f1;
            border-left: 3px solid #3b82f6;
            background: #f0f6ff;
            border-radius: 12px;
            padding: 21px 9px 10px;
            margin-top: 10px;
        }

        .org-node:not(.org-node-root)
        > .org-children > .org-team-label {
            margin-bottom: 5px;
            background: #dbeafe;
            border-color: #bfdbfe;
        }

        /* En una rama expandida, las tarjetas hermanas
           mantienen separacion visual. */
        .org-node:not(.org-node-root)
        > .org-children > .org-node {
            width: 100%;
            margin: 0;
        }

        .org-node:not(.org-node-root)
        > .org-children > .org-node > .org-card {
            background: #fff;
            width: 100%;
        }

        .dark .org-team-label {
            background: #172554;
            border-color: #1e40af;
            color: #dbeafe;
        }

        .dark .org-team-label-count {
            color: #bfdbfe;
        }

        .dark .org-node:not(.org-node-root) > .org-children {
            background: #111e35;
            border-color: #1e40af;
        }


        /* ORG02 FIX7 - Vista enfocada de tres columnas */
        .orgx-focus {
            border: 1px solid #dbe4f0;
            border-radius: 16px;
            background: #f8fbff;
            padding: 18px;
        }

        .orgx-focus-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 18px;
        }

        .orgx-focus-heading {
            margin: 0 auto;
            max-width: 520px;
            padding: 16px;
            border: 2px solid #2563eb;
            border-radius: 14px;
            background: #eff6ff;
            text-align: center;
        }

        .orgx-focus-eyebrow {
            color: #1d4ed8;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .orgx-focus-name {
            color: #0f172a;
            font-size: 1.18rem;
            font-weight: 800;
            margin-top: 4px;
        }

        .orgx-focus-position {
            color: #475569;
            font-size: .82rem;
            margin-top: 4px;
        }

        .orgx-focus-count {
            color: #1d4ed8;
            font-size: .8rem;
            font-weight: 700;
            margin-top: 8px;
        }

        .orgx-focus-line {
            border-left: 2px solid #93b4dd;
            height: 23px;
            width: 0;
            margin: 0 auto;
        }

        .orgx-focus-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            align-items: start;
        }

        .orgx-focus-card {
            min-width: 0;
            border: 1px solid #bfdbfe;
            border-top: 3px solid #2563eb;
            border-radius: 12px;
            background: #fff;
            padding: 12px;
            box-shadow: 0 3px 10px rgba(15,23,42,.06);
        }

        .orgx-focus-person {
            display: flex;
            gap: 10px;
            align-items: center;
            min-width: 0;
        }

        .orgx-focus-avatar {
            position: relative;
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            border-radius: 50%;
            background: #2563eb;
            overflow: hidden;
        }

        .orgx-focus-avatar img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .orgx-focus-initials {
            display: flex;
            width: 100%;
            height: 100%;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: .8rem;
        }

        .orgx-focus-person-info {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .orgx-focus-person-info strong {
            display: block;
            color: #0f172a;
            font-size: .83rem;
        }

        .orgx-focus-person-info div,
        .orgx-focus-person-info small {
            display: block;
            color: #64748b;
            font-size: .74rem;
            margin-top: 2px;
        }

        .orgx-focus-direct {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 11px;
            padding-top: 9px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: .72rem;
        }

        .orgx-focus-direct strong {
            color: #1d4ed8;
        }

        .orgx-focus-subteam {
            margin-top: 12px;
            padding: 10px;
            border: 1px solid #bfdbfe;
            border-left: 3px solid #2563eb;
            border-radius: 10px;
            background: #eff6ff;
        }

        .orgx-focus-subhead {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 5px;
            color: #1e40af;
            font-size: .74rem;
        }

        .orgx-focus-subhead span {
            color: #475569;
        }

        .orgx-focus-sublist {
            display: flex;
            flex-direction: column;
            gap: 7px;
            margin-top: 10px;
        }

        .orgx-focus-subperson {
            display: flex;
            align-items: center;
            gap: 7px;
            background: #fff;
            border: 1px solid #dbeafe;
            border-radius: 7px;
            padding: 7px 8px;
            color: #334155;
            font-size: .72rem;
            overflow-wrap: anywhere;
        }

        .orgx-focus-subdot {
            width: 6px;
            height: 6px;
            flex: 0 0 6px;
            border-radius: 50%;
            background: #2563eb;
        }

        .orgx-focus-open,
        .orgx-focus-deeper {
            color: #1d4ed8;
            font-size: .72rem;
            font-weight: 700;
            cursor: pointer;
        }

        .orgx-focus-open {
            margin-top: 10px;
        }

        .orgx-focus-note {
            color: #64748b;
            text-align: center;
            font-size: .75rem;
            margin-top: 15px;
        }

        @media (max-width: 1100px) {
            .orgx-focus-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .orgx-focus-grid {
                grid-template-columns: minmax(0, 1fr);
            }
            .orgx-focus {
                padding: 10px;
            }
        }

        .dark .orgx-focus {
            background: #0b1220;
            border-color: #374151;
        }

        .dark .orgx-focus-heading,
        .dark .orgx-focus-subteam {
            background: #172554;
            border-color: #2563eb;
        }

        .dark .orgx-focus-card,
        .dark .orgx-focus-subperson {
            background: #111827;
            border-color: #374151;
        }

        .dark .orgx-focus-name,
        .dark .orgx-focus-person-info strong {
            color: #f8fafc;
        }

        .dark .orgx-focus-subperson {
            color: #e2e8f0;
        }

    </style>

    <div
        x-data="{
            nodes: @js($chart['nodes']),
            area: '',
            query: '',
            collapsed: @js($initialCollapsed),

            // ORG02 FIX7 - Vista enfocada
            focusId: null,

            focusTeam(id) {
                this.focusId = Number(id);
                this.$nextTick(() => {
                    this.$refs.orgFocus?.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                });
            },

            focusRow() {
                return this.nodes.find(
                    row => Number(row.id) === this.focusId
                ) || null;
            },

            focusChildren(id) {
                return this.nodes.filter(
                    row => Number(row.manager_id) === Number(id)
                );
            },

            focusBack() {
                const row = this.focusRow();
                const parentId = row?.manager_id;

                if (parentId && this.nodes.some(
                    other => Number(other.id) === Number(parentId)
                )) {
                    this.focusId = Number(parentId);
                } else {
                    this.focusId = null;
                }
            },

            focusHome() {
                this.focusId = null;
            },

            focusInitials(name) {
                return String(name || '')
                    .trim()
                    .split(/\s+/)
                    .slice(0, 2)
                    .map(part => part.charAt(0).toUpperCase())
                    .join('') || 'NA';
            },

            normalize(value) {
                return String(value || '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase();
            },

            filtering() {
                return Boolean(this.area || this.query.trim());
            },

            matches(row) {
                if (this.area && row.department !== this.area) {
                    return false;
                }

                const q = this.normalize(this.query.trim());

                if (!q) return true;

                const text = this.normalize(
                    row.name + ' ' + row.position + ' ' +
                    row.department + ' ' + (row.manager_name || '')
                );

                return text.includes(q);
            },

            matchesById(id) {
                const row = this.nodes.find(n => n.id === id);
                return row ? this.matches(row) : false;
            },

            visible(id) {
                if (!this.filtering()) return true;

                const row = this.nodes.find(n => n.id === id);

                if (!row) return false;
                if (this.matches(row)) return true;

                return this.nodes.some(other =>
                    this.matches(other) &&
                    other.ancestors.includes(id)
                );
            },

            toggle(id) {
                if (this.collapsed.includes(id)) {
                    this.collapsed = this.collapsed.filter(x => x !== id);
                } else {
                    this.collapsed = [...this.collapsed, id];
                }
            },

            expandAll() {
                this.collapsed = [];
            },

            collapseAll() {
                this.collapsed = this.nodes
                    .filter(row => row.has_children)
                    .map(row => row.id);
            },

            resetFilters() {
                this.area = '';
                this.query = '';
                this.collapsed = @js($initialCollapsed);
            },

            matchedCount() {
                return this.nodes.filter(row => this.matches(row)).length;
            },

            printPdf() {
                window.print();
            }
        }"
        class="orgx-page"
    >
        <section class="orgx-hero">
            <div class="orgx-hero-grid">
                <div>
                    <div class="orgx-title">Organigrama corporativo</div>
                    <div class="orgx-subtitle">
                        Vista ejecutiva de la estructura organizacional de Grupo Línea 7.
                    </div>
                </div>

                <div class="orgx-total">
                    <div class="orgx-total-number">{{ $chart['total'] }}</div>
                    <div class="orgx-total-label">Empleados activos</div>
                </div>
            </div>
        </section>

        <section class="orgx-areas">
            @foreach ($chart['areas'] as $areaItem)
                <button
                    type="button"
                    class="orgx-area"
                    x-on:click="area = area === @js($areaItem['name']) ? '' : @js($areaItem['name'])"
                    x-bind:class="area === @js($areaItem['name']) ? 'orgx-area-selected' : ''"
                    x-bind:aria-pressed="area === @js($areaItem['name'])"
                >
                    <div class="orgx-area-name">{{ $areaItem['name'] }}</div>
                    <div class="orgx-area-count">{{ $areaItem['count'] }}</div>
                    <div class="orgx-subtitle">colaboradores</div>
                </button>
            @endforeach
        </section>

        <section class="orgx-toolbar">
            <div class="orgx-toolbar-row">
                <input
                    type="search"
                    x-model.debounce.200ms="query"
                    placeholder="Buscar empleado, puesto, departamento o jefe..."
                    aria-label="Buscar en organigrama"
                    class="orgx-search"
                />

                <button type="button" class="orgx-btn orgx-btn-primary" wire:click="downloadPdf" wire:loading.attr="disabled" wire:target="downloadPdf">
                    Descargar PDF
                </button>

                <button type="button" class="orgx-btn" x-on:click="expandAll()">
                    Expandir todo
                </button>

                <button type="button" class="orgx-btn" x-on:click="collapseAll()">
                    Contraer todo
                </button>

                <button type="button" class="orgx-btn" x-on:click="resetFilters()">
                    Restablecer
                </button>
            </div>

            <div class="orgx-hint">
                <span x-text="matchedCount()"></span>
                empleados coinciden con el filtro. La búsqueda conserva visibles sus superiores para no perder el contexto.
            </div>
        </section>


        <!-- ORG02 FIX7: equipo a todo el ancho -->
        <section
            class="orgx-focus"
            x-ref="orgFocus"
            x-show="focusId !== null"
            x-cloak
        >
            <div class="orgx-focus-actions">
                <button type="button" class="orgx-btn"
                    x-on:click="focusBack()">
                    Subir un nivel
                </button>
                <button type="button" class="orgx-btn"
                    x-on:click="focusHome()">
                    Volver al organigrama general
                </button>
            </div>

            <template x-if="focusRow()">
                <div>
                    <div class="orgx-focus-heading">
                        <div class="orgx-focus-eyebrow">
                            Vista jerárquica del equipo
                        </div>
                        <div class="orgx-focus-name"
                            x-text="focusRow().name"></div>
                        <div class="orgx-focus-position"
                            x-text="(focusRow().position || '') + ' · ' +
                                (focusRow().department || '')"></div>
                        <div class="orgx-focus-count"
                            x-text="focusChildren(focusId).length +
                                ' colaboradores directos'"></div>
                    </div>

                    <div class="orgx-focus-line"></div>

                    <div class="orgx-focus-grid">
                        <template
                            x-for="person in focusChildren(focusId)"
                            x-bind:key="person.id"
                        >
                            <article class="orgx-focus-card">
                                <div class="orgx-focus-person">
                                    <div class="orgx-focus-avatar">
                                        <template x-if="person.avatar_url">
                                            <img
                                                x-bind:src="person.avatar_url"
                                                alt=""
                                                loading="lazy"
                                                x-on:error="$el.style.display='none'"
                                            />
                                        </template>
                                        <span x-text="focusInitials(person.name)"
                                            class="orgx-focus-initials"></span>
                                    </div>
                                    <div class="orgx-focus-person-info">
                                        <strong x-text="person.name"></strong>
                                        <div x-text="person.position"></div>
                                        <small x-text="person.department"></small>
                                    </div>
                                </div>

                                <div class="orgx-focus-direct">
                                    <span>Reporta directamente a</span>
                                    <strong x-text="focusRow().name"></strong>
                                </div>

                                <template
                                    x-if="focusChildren(person.id).length > 0"
                                >
                                    <div class="orgx-focus-subteam">
                                        <div class="orgx-focus-subhead">
                                            <strong
                                                x-text="'Equipo de ' + person.name"
                                            ></strong>
                                            <span
                                                x-text="focusChildren(person.id).length +
                                                    ' colaboradores'"
                                            ></span>
                                        </div>
                                        <div class="orgx-focus-sublist">
                                            <template
                                                x-for="sub in focusChildren(person.id)"
                                                x-bind:key="sub.id"
                                            >
                                                <div class="orgx-focus-subperson">
                                                    <span class="orgx-focus-subdot"></span>
                                                    <span x-text="sub.name"></span>
                                                    <button
                                                        type="button"
                                                        x-show="focusChildren(sub.id).length > 0"
                                                        x-on:click="focusTeam(sub.id)"
                                                        class="orgx-focus-deeper"
                                                    >
                                                        Ver equipo
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                        <button type="button"
                                            class="orgx-focus-open"
                                            x-on:click="focusTeam(person.id)">
                                            Ver equipo en tres columnas
                                        </button>
                                    </div>
                                </template>
                            </article>
                        </template>
                    </div>

                    <p class="orgx-focus-note">
                        Cada columna representa un colaborador directo.
                        Los equipos internos permanecen dentro de la
                        tarjeta de su responsable.
                    </p>
                </div>
            </template>
        </section>

        <section class="orgx-canvas" x-show="focusId === null">

            @if (count($roots))
                <ul class="org-roots">
                    @foreach ($roots as $rootId)
                        @include('filament.pages.partials.employee-org-node', [
                            'node' => $byId[$rootId],
                            'children' => $children,
                            'byId' => $byId,
                        ])
                    @endforeach
                </ul>
            @else
                <div class="orgx-footer">
                    No hay empleados activos para mostrar.
                </div>
            @endif
        </section>

        <div class="orgx-footer">
            Vista de consulta. Los filtros y controles no modifican empleados, departamentos ni jefes directos.
        </div>
    </div>
</x-filament-panels::page>
