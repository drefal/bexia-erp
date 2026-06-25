@php
    $statePath = $getStatePath();
    $uid = 'geofence-map-' . md5($statePath . '-' . spl_object_id($this));
@endphp

<style>
    .bexia-geofence-map {
        width: 100% !important;
        max-width: none !important;
    }

    .bexia-geofence-map .leaflet-container {
        width: 100% !important;
        height: 520px !important;
        min-height: 520px !important;
        overflow: hidden !important;
        position: relative !important;
        outline-style: none !important;
        background: #ddd !important;
        font: 12px/1.5 "Helvetica Neue", Arial, Helvetica, sans-serif !important;
        z-index: 1 !important;
    }

    .bexia-geofence-map .leaflet-pane,
    .bexia-geofence-map .leaflet-map-pane,
    .bexia-geofence-map .leaflet-tile-pane,
    .bexia-geofence-map .leaflet-overlay-pane,
    .bexia-geofence-map .leaflet-shadow-pane,
    .bexia-geofence-map .leaflet-marker-pane,
    .bexia-geofence-map .leaflet-tooltip-pane,
    .bexia-geofence-map .leaflet-popup-pane {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
    }

    .bexia-geofence-map .leaflet-map-pane {
        z-index: 400 !important;
    }

    .bexia-geofence-map .leaflet-tile-pane {
        z-index: 200 !important;
    }

    .bexia-geofence-map .leaflet-overlay-pane {
        z-index: 400 !important;
    }

    .bexia-geofence-map .leaflet-marker-pane {
        z-index: 600 !important;
    }

    .bexia-geofence-map .leaflet-control-container {
        position: relative !important;
        z-index: 800 !important;
    }

    .bexia-geofence-map .leaflet-tile,
    .bexia-geofence-map .leaflet-marker-icon,
    .bexia-geofence-map .leaflet-marker-shadow,
    .bexia-geofence-map .leaflet-image-layer {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        max-width: none !important;
        max-height: none !important;
        width: 256px;
        height: 256px;
        user-select: none;
        -webkit-user-select: none;
    }

    .bexia-geofence-map .leaflet-tile-container,
    .bexia-geofence-map .leaflet-layer {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
    }

    .bexia-geofence-map .leaflet-tile-container img {
        max-width: none !important;
        max-height: none !important;
    }

    .bexia-geofence-map .leaflet-control {
        position: relative !important;
        z-index: 800 !important;
        pointer-events: auto !important;
        float: left !important;
        clear: both !important;
    }

    .bexia-geofence-map .leaflet-top,
    .bexia-geofence-map .leaflet-bottom {
        position: absolute !important;
        z-index: 1000 !important;
        pointer-events: none !important;
    }

    .bexia-geofence-map .leaflet-top {
        top: 0 !important;
    }

    .bexia-geofence-map .leaflet-left {
        left: 0 !important;
    }

    .bexia-geofence-map .leaflet-right {
        right: 0 !important;
    }

    .bexia-geofence-map .leaflet-bottom {
        bottom: 0 !important;
    }

    .bexia-geofence-map .leaflet-control-zoom {
        border: 2px solid rgba(0, 0, 0, 0.2) !important;
        background-clip: padding-box !important;
        margin-left: 10px !important;
        margin-top: 10px !important;
        border-radius: 4px !important;
        overflow: hidden !important;
    }

    .bexia-geofence-map .leaflet-control-zoom a {
        display: block !important;
        width: 30px !important;
        height: 30px !important;
        line-height: 30px !important;
        text-align: center !important;
        text-decoration: none !important;
        color: black !important;
        background-color: white !important;
        border-bottom: 1px solid #ccc !important;
        font-size: 18px !important;
        font-weight: bold !important;
    }

    .bexia-geofence-map .leaflet-control-zoom a:last-child {
        border-bottom: none !important;
    }

    .bexia-geofence-map .leaflet-control-attribution {
        background: rgba(255, 255, 255, 0.8) !important;
        margin: 0 !important;
        padding: 0 5px !important;
        color: #333 !important;
        font-size: 11px !important;
    }

    .bexia-geofence-map .leaflet-bottom.leaflet-right {
        right: 0 !important;
        bottom: 0 !important;
    }

    .bexia-geofence-map svg {
        max-width: none !important;
    }

    .bexia-geofence-map .leaflet-interactive {
        cursor: pointer !important;
    }
</style>


<div
    wire:ignore
    x-data="{
        map: null,
        marker: null,
        circle: null,
        polygon: null,
        points: [],
        resizeObserver: null,
        uid: @js($uid),

        loadLeaflet() {
            return new Promise((resolve, reject) => {
                if (window.L) {
                    resolve();
                    return;
                }

                if (! document.querySelector('link[data-bexia-leaflet]')) {
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                    link.integrity = 'sha256-p4NxAoJBhIINfQ2ATbXtSMxhjcL8GlLd2N2D8daT8Vg=';
                    link.crossOrigin = '';
                    link.setAttribute('data-bexia-leaflet', '1');
                    document.head.appendChild(link);
                }

                const existing = document.querySelector('script[data-bexia-leaflet]');
                if (existing) {
                    existing.addEventListener('load', resolve, { once: true });
                    existing.addEventListener('error', reject, { once: true });
                    return;
                }

                const script = document.createElement('script');
                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                script.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
                script.crossOrigin = '';
                script.setAttribute('data-bexia-leaflet', '1');
                script.addEventListener('load', resolve, { once: true });
                script.addEventListener('error', reject, { once: true });
                document.body.appendChild(script);
            });
        },

        field(name) {
            const candidates = [
                `[name='${name}']`,
                `[name='data[${name}]']`,
                `[name$='[${name}]']`,
                `input[id$='-${name}']`,
                `textarea[id$='-${name}']`,
                `select[id$='-${name}']`,
                `input[id$='.${name}']`,
                `textarea[id$='.${name}']`,
                `select[id$='.${name}']`,
            ];

            for (const selector of candidates) {
                const element = document.querySelector(selector);
                if (element) {
                    return element;
                }
            }

            return null;
        },

        inputValue(name) {
            const el = this.field(name);
            return el ? el.value : null;
        },

        setInput(name, value) {
            const el = this.field(name);
            if (! el) return;

            el.value = value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        },

        numberValue(name, fallback = null) {
            const value = this.inputValue(name);
            const parsed = parseFloat(value);
            return Number.isFinite(parsed) ? parsed : fallback;
        },

        type() {
            return this.inputValue('geofence_type') || 'circle';
        },

        radius() {
            return this.numberValue('radius_meters', 100) || 100;
        },

        readPolygon() {
            const raw = this.inputValue('polygon_coordinates');

            if (! raw) {
                return [];
            }

            try {
                const decoded = JSON.parse(raw);
                if (! Array.isArray(decoded)) {
                    return [];
                }

                return decoded
                    .filter((point) => Array.isArray(point) && point.length >= 2)
                    .map((point) => [parseFloat(point[0]), parseFloat(point[1])])
                    .filter((point) => Number.isFinite(point[0]) && Number.isFinite(point[1]));
            } catch (e) {
                return [];
            }
        },

        writePolygon() {
            this.setInput('polygon_coordinates', JSON.stringify(this.points));
        },

        currentCenter() {
            const lat = this.numberValue('latitude', 19.3581188);
            const lng = this.numberValue('longitude', -99.1072386);

            return [lat, lng];
        },

        forceSize() {
            const el = document.getElementById(this.uid);
            if (! el) return;

            el.style.display = 'block';
            el.style.width = '100%';
            el.style.minWidth = '100%';
            el.style.height = '520px';
            el.style.minHeight = '520px';

            const parent = el.parentElement;
            if (parent) {
                parent.style.width = '100%';
                parent.style.minWidth = '100%';
            }
        },

        resizeMap() {
            if (! this.map) return;

            this.forceSize();

            [50, 150, 300, 600, 1000, 1500].forEach((delay) => {
                setTimeout(() => {
                    this.forceSize();
                    this.map.invalidateSize(true);
                }, delay);
            });
        },

        redraw(fit = false) {
            if (! this.map || ! window.L) return;

            this.forceSize();

            const center = this.currentCenter();
            const type = this.type();

            if (! this.marker) {
                this.marker = L.circleMarker(center, {
                    radius: 7,
                    weight: 3,
                    fillOpacity: 0.85,
                }).addTo(this.map);
            } else {
                this.marker.setLatLng(center);
            }

            if (this.circle) {
                this.map.removeLayer(this.circle);
                this.circle = null;
            }

            if (this.polygon) {
                this.map.removeLayer(this.polygon);
                this.polygon = null;
            }

            this.points = this.readPolygon();

            if (type === 'polygon') {
                if (this.points.length > 0) {
                    this.polygon = L.polygon(this.points, {
                        weight: 3,
                    }).addTo(this.map);

                    if (fit) {
                        try {
                            this.map.fitBounds(this.polygon.getBounds(), { padding: [40, 40], maxZoom: 20 });
                        } catch (e) {
                            this.map.setView(center, 18);
                        }
                    }
                } else {
                    this.map.setView(center, 18);
                }
            } else {
                this.circle = L.circle(center, {
                    radius: this.radius(),
                    weight: 2,
                }).addTo(this.map);

                if (fit) {
                    try {
                        this.map.fitBounds(this.circle.getBounds(), { padding: [40, 40], maxZoom: 20 });
                    } catch (e) {
                        this.map.setView(center, 18);
                    }
                }
            }

            this.resizeMap();
        },

        clearPolygon() {
            this.points = [];
            this.writePolygon();
            this.redraw(false);
        },

        useCurrentCenterAsFirstPoint() {
            const center = this.currentCenter();
            this.points = this.readPolygon();
            this.points.push([parseFloat(center[0].toFixed(7)), parseFloat(center[1].toFixed(7))]);
            this.writePolygon();
            this.redraw(true);
        },

        bindFieldListeners() {
            ['geofence_type', 'latitude', 'longitude', 'radius_meters', 'polygon_coordinates'].forEach((name) => {
                const input = this.field(name);
                if (input && ! input.dataset.bexiaMapBound) {
                    input.dataset.bexiaMapBound = '1';
                    input.addEventListener('input', () => this.redraw(true));
                    input.addEventListener('change', () => this.redraw(true));
                }
            });
        },

        bindResizeObserver() {
            const el = document.getElementById(this.uid);
            if (! el || ! window.ResizeObserver) return;

            this.resizeObserver = new ResizeObserver(() => {
                this.resizeMap();
            });

            this.resizeObserver.observe(el);
            if (el.parentElement) {
                this.resizeObserver.observe(el.parentElement);
            }
        },

        initMap() {
            this.loadLeaflet().then(() => {
                this.$nextTick(() => {
                    const el = document.getElementById(this.uid);
                    if (! el || this.map) return;

                    this.forceSize();

                    this.map = L.map(el, {
                        zoomControl: true,
                        scrollWheelZoom: true,
                    }).setView(this.currentCenter(), 18);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 22,
                        attribution: '&copy; OpenStreetMap contributors',
                    }).addTo(this.map);

                    this.map.on('click', (event) => {
                        const type = this.type();

                        if (type === 'polygon') {
                            this.points = this.readPolygon();
                            this.points.push([
                                parseFloat(event.latlng.lat.toFixed(7)),
                                parseFloat(event.latlng.lng.toFixed(7)),
                            ]);
                            this.writePolygon();
                        } else {
                            this.setInput('latitude', event.latlng.lat.toFixed(7));
                            this.setInput('longitude', event.latlng.lng.toFixed(7));
                        }

                        this.redraw(false);
                    });

                    this.bindFieldListeners();
                    this.bindResizeObserver();
                    this.redraw(true);
                    this.resizeMap();

                    setTimeout(() => {
                        this.bindFieldListeners();
                        this.redraw(true);
                        this.resizeMap();
                    }, 1000);
                });
            });
        },
    }"
    x-init="initMap()"
    class="bexia-geofence-map w-full max-w-none space-y-3"
    style="width: 100%; max-width: none;"
>
    <div class="w-full max-w-none rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900" style="width: 100%; max-width: none;">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    Mapa de geocerca
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Círculo: da clic para mover el centro. Polígono: da clic para agregar puntos al perímetro.
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    x-on:click="useCurrentCenterAsFirstPoint()"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                >
                    Agregar centro como punto
                </button>

                <button
                    type="button"
                    x-on:click="clearPolygon()"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                >
                    Limpiar polígono
                </button>
            </div>
        </div>

        <div
            id="{{ $uid }}"
            class="w-full max-w-none"
            style="display: block; width: 100%; min-width: 100%; height: 520px; min-height: 520px; border-radius: 0.75rem; overflow: hidden;"
        ></div>

        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Los mapas se muestran con OpenStreetMap/Leaflet. Al guardar, Bexia conserva latitud/longitud y, si aplica, el JSON del polígono.
        </div>
    </div>
</div>
