{{-- BEXIA_ATC_CUSTOMER_SIGNATURE_PAD_V5_83_4C3B --}}

@php
    $statePath = $getStatePath();
@endphp

<div
    x-data="{
        value: $wire.entangle(@js($statePath)),
        drawing: false,
        ctx: null,

        setup() {
            const canvas = this.$refs.canvas;

            this.ctx = canvas.getContext('2d');

            this.ctx.lineWidth = 3;
            this.ctx.lineCap = 'round';
            this.ctx.lineJoin = 'round';
            this.ctx.strokeStyle = '#111827';

            this.resetCanvas(false);

            if (
                this.value
                && this.value.startsWith('data:image/png;base64,')
            ) {
                const image = new Image();

                image.onload = () => {
                    this.resetCanvas(false);

                    this.ctx.drawImage(
                        image,
                        0,
                        0,
                        canvas.width,
                        canvas.height
                    );
                };

                image.src = this.value;
            }
        },

        resetCanvas(clearState = true) {
            const canvas = this.$refs.canvas;

            this.ctx.clearRect(
                0,
                0,
                canvas.width,
                canvas.height
            );

            this.ctx.fillStyle = '#ffffff';

            this.ctx.fillRect(
                0,
                0,
                canvas.width,
                canvas.height
            );

            this.ctx.strokeStyle = '#111827';

            if (clearState) {
                this.value = null;
            }
        },

        coordinates(event) {
            const canvas = this.$refs.canvas;
            const rect = canvas.getBoundingClientRect();

            return {
                x:
                    (event.clientX - rect.left)
                    * (canvas.width / rect.width),

                y:
                    (event.clientY - rect.top)
                    * (canvas.height / rect.height),
            };
        },

        start(event) {
            event.preventDefault();

            const point = this.coordinates(event);

            this.drawing = true;

            this.ctx.beginPath();

            this.ctx.moveTo(
                point.x,
                point.y
            );

            if (
                event.currentTarget
                    .setPointerCapture
            ) {
                event.currentTarget
                    .setPointerCapture(
                        event.pointerId
                    );
            }
        },

        move(event) {
            if (! this.drawing) {
                return;
            }

            event.preventDefault();

            const point = this.coordinates(event);

            this.ctx.lineTo(
                point.x,
                point.y
            );

            this.ctx.stroke();
        },

        finish(event) {
            if (! this.drawing) {
                return;
            }

            event.preventDefault();

            this.drawing = false;

            this.ctx.closePath();

            this.value =
                this.$refs.canvas
                    .toDataURL('image/png');
        },
    }"
    x-init="$nextTick(() => setup())"
    class="space-y-2"
>
    <div class="text-sm text-gray-600 dark:text-gray-400">
        Solicita al cliente firmar dentro del recuadro.
        La firma quedará asociada a la recepción física del equipo.
    </div>

    <canvas
        x-ref="canvas"
        width="900"
        height="260"
        class="block w-full touch-none cursor-crosshair rounded-xl border border-gray-300 bg-white shadow-sm dark:border-gray-700"
        x-on:pointerdown="start($event)"
        x-on:pointermove="move($event)"
        x-on:pointerup="finish($event)"
        x-on:pointercancel="finish($event)"
        x-on:pointerleave="finish($event)"
    ></canvas>

    <div class="flex items-center justify-between gap-3">
        <div
            class="text-xs text-gray-500 dark:text-gray-400"
            x-text="
                value
                    ? 'Firma capturada'
                    : 'Pendiente de firma'
            "
        ></div>

        <x-filament::button
            type="button"
            color="gray"
            size="sm"
            x-on:click="resetCanvas(true)"
        >
            Limpiar firma
        </x-filament::button>
    </div>
</div>
