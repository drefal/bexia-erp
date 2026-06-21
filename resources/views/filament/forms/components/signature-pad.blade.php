<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.entangle(@js($getStatePath())),
            statePath: @js($getStatePath()),
            cacheKey: null,
            drawing: false,
            canvas: null,
            ctx: null,
            lastPoint: null,

            initPad() {
                window.__bexiaSignatureCache = window.__bexiaSignatureCache || {};
                this.cacheKey = window.location.pathname + ':' + this.statePath;

                this.canvas = this.$refs.canvas;
                this.ctx = this.canvas.getContext('2d');

                this.prepareCanvas();

                const cached = window.__bexiaSignatureCache[this.cacheKey];

                if (! this.state && cached) {
                    this.state = cached;
                }

                this.$nextTick(() => {
                    this.prepareCanvas();

                    if (this.state) {
                        this.restoreSignature();
                    }
                });

                this.$watch('state', (value) => {
                    if (value) {
                        window.__bexiaSignatureCache[this.cacheKey] = value;
                        this.$nextTick(() => this.restoreSignature());
                    }
                });

                window.addEventListener('resize', () => {
                    const current = this.state || window.__bexiaSignatureCache[this.cacheKey] || null;
                    this.prepareCanvas();

                    if (current) {
                        this.state = current;
                        this.restoreSignature();
                    }
                });

                document.addEventListener('livewire:update', () => {
                    const current = this.state || window.__bexiaSignatureCache[this.cacheKey] || null;

                    if (current) {
                        this.state = current;
                        this.$nextTick(() => this.restoreSignature());
                    }
                });

                document.addEventListener('livewire:updated', () => {
                    const current = this.state || window.__bexiaSignatureCache[this.cacheKey] || null;

                    if (current) {
                        this.state = current;
                        this.$nextTick(() => this.restoreSignature());
                    }
                });
            },

            prepareCanvas() {
                const rect = this.canvas.getBoundingClientRect();
                const width = Math.max(Math.floor(rect.width), 320);
                const height = 180;

                this.canvas.width = width;
                this.canvas.height = height;
                this.canvas.style.height = height + 'px';

                this.ctx.setTransform(1, 0, 0, 1, 0, 0);
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.ctx.lineWidth = 2.5;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
                this.ctx.strokeStyle = '#111827';
            },

            getPoint(event) {
                const rect = this.canvas.getBoundingClientRect();

                let x = Number.isFinite(event.offsetX)
                    ? event.offsetX
                    : (event.clientX - rect.left);

                let y = Number.isFinite(event.offsetY)
                    ? event.offsetY
                    : (event.clientY - rect.top);

                const scaleX = rect.width > 0 ? (this.canvas.width / rect.width) : 1;
                const scaleY = rect.height > 0 ? (this.canvas.height / rect.height) : 1;

                x = x * scaleX;
                y = y * scaleY;

                return {
                    x: Math.max(0, Math.min(x, this.canvas.width)),
                    y: Math.max(0, Math.min(y, this.canvas.height)),
                };
            },

            start(event) {
                event.preventDefault();

                if (event.pointerId && this.canvas.setPointerCapture) {
                    this.canvas.setPointerCapture(event.pointerId);
                }

                this.drawing = true;
                this.lastPoint = this.getPoint(event);

                this.ctx.beginPath();
                this.ctx.moveTo(this.lastPoint.x, this.lastPoint.y);
            },

            move(event) {
                if (! this.drawing) {
                    return;
                }

                event.preventDefault();

                const point = this.getPoint(event);

                this.ctx.lineTo(point.x, point.y);
                this.ctx.stroke();

                this.lastPoint = point;
            },

            end(event) {
                if (! this.drawing) {
                    return;
                }

                event.preventDefault();

                this.drawing = false;
                this.lastPoint = null;
                this.state = this.canvas.toDataURL('image/png');
                window.__bexiaSignatureCache[this.cacheKey] = this.state;

                if (event.pointerId && this.canvas.releasePointerCapture) {
                    try {
                        this.canvas.releasePointerCapture(event.pointerId);
                    } catch (e) {}
                }
            },

            clearPad() {
                this.prepareCanvas();
                this.state = null;
                this.drawing = false;
                this.lastPoint = null;

                if (this.cacheKey && window.__bexiaSignatureCache) {
                    delete window.__bexiaSignatureCache[this.cacheKey];
                }
            },

            restoreSignature() {
                const current = this.state || window.__bexiaSignatureCache[this.cacheKey] || null;

                if (! current) {
                    return;
                }

                const image = new Image();

                image.onload = () => {
                    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                    this.ctx.drawImage(image, 0, 0, this.canvas.width, this.canvas.height);
                };

                image.src = current;
            },
        }"
        x-init="initPad()"
        class="space-y-2"
    >
        <div class="text-xs text-gray-600 dark:text-gray-300">
            Solicita al cliente o persona que recibe que firme dentro del recuadro. Esta firma se guardará como evidencia de entrega.
        </div>

        <div class="rounded-xl border border-gray-300 bg-white p-2 dark:border-gray-700 dark:bg-white">
            <canvas
                x-ref="canvas"
                class="block w-full cursor-crosshair touch-none rounded-lg bg-white"
                style="height: 180px;"
                @pointerdown="start($event)"
                @pointermove="move($event)"
                @pointerup="end($event)"
                @pointercancel="end($event)"
                @pointerleave="end($event)"
            ></canvas>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                @click="clearPad()"
            >
                Limpiar firma
            </button>

            <span class="text-xs text-gray-500" x-show="state">
                Firma capturada.
            </span>
        </div>
    </div>
</x-dynamic-component>
