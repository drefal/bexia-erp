<script>
(function () {
    if (window.BEXIA_INTERNAL_REFERENCE_DUPLICATE_MODAL_READY) {
        return;
    }

    window.BEXIA_INTERNAL_REFERENCE_DUPLICATE_MODAL_READY = true;

    function ensureModal() {
        let modal = document.getElementById('bexia-internal-reference-duplicate-modal');

        if (modal) {
            return modal;
        }

        modal = document.createElement('div');
        modal.id = 'bexia-internal-reference-duplicate-modal';
        modal.style.display = 'none';
        modal.innerHTML = `
            <div data-bexia-modal-backdrop
                style="
                    position: fixed;
                    inset: 0;
                    z-index: 999999;
                    background: rgba(15, 23, 42, 0.68);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                "
            >
                <div role="dialog" aria-modal="true" aria-labelledby="bexia-internal-reference-duplicate-title"
                    style="
                        width: min(520px, 100%);
                        background: white;
                        border-radius: 18px;
                        box-shadow: 0 24px 80px rgba(15, 23, 42, 0.35);
                        overflow: hidden;
                        font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    "
                >
                    <div style="padding: 22px 24px 12px 24px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="
                                width: 42px;
                                height: 42px;
                                border-radius: 999px;
                                background: #fee2e2;
                                color: #b91c1c;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 24px;
                                font-weight: 700;
                            ">!</div>

                            <div>
                                <h2 id="bexia-internal-reference-duplicate-title"
                                    style="margin: 0; font-size: 18px; line-height: 1.3; font-weight: 700; color: #111827;"
                                >
                                    Referencia interna duplicada
                                </h2>
                                <p style="margin: 4px 0 0 0; font-size: 13px; color: #6b7280;">
                                    Revisa la referencia antes de continuar.
                                </p>
                            </div>
                        </div>

                        <p data-bexia-modal-message
                            style="margin: 18px 0 0 0; color: #374151; font-size: 15px; line-height: 1.55;"
                        ></p>
                    </div>

                    <div style="
                        padding: 16px 24px 22px 24px;
                        display: flex;
                        justify-content: flex-end;
                        gap: 10px;
                        background: #f9fafb;
                        border-top: 1px solid #e5e7eb;
                    ">
                        <button type="button" data-bexia-modal-close
                            style="
                                appearance: none;
                                border: 0;
                                border-radius: 10px;
                                background: #dc2626;
                                color: white;
                                font-weight: 700;
                                padding: 10px 16px;
                                cursor: pointer;
                            "
                        >
                            Entendido
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        modal.querySelector('[data-bexia-modal-close]').addEventListener('click', function () {
            modal.style.display = 'none';
        });

        modal.querySelector('[data-bexia-modal-backdrop]').addEventListener('click', function (event) {
            if (event.target === event.currentTarget) {
                modal.style.display = 'none';
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.style.display !== 'none') {
                modal.style.display = 'none';
            }
        });

        return modal;
    }

    function showModal(detail) {
        const modal = ensureModal();

        const title = detail && detail.title
            ? String(detail.title)
            : 'Referencia interna duplicada';

        const message = detail && (detail.message || detail.body)
            ? String(detail.message || detail.body)
            : 'La referencia interna ya existe en otro producto de esta empresa.';

        const titleEl = modal.querySelector('#bexia-internal-reference-duplicate-title');
        const messageEl = modal.querySelector('[data-bexia-modal-message]');

        if (titleEl) {
            titleEl.textContent = title;
        }

        if (messageEl) {
            messageEl.textContent = message;
        }

        modal.style.display = 'block';

        setTimeout(function () {
            const button = modal.querySelector('[data-bexia-modal-close]');
            if (button) {
                button.focus();
            }
        }, 50);
    }

    window.addEventListener('bexia-internal-reference-duplicate-modal', function (event) {
        showModal(event.detail || {});
    });

    document.addEventListener('bexia-internal-reference-duplicate-modal', function (event) {
        showModal(event.detail || {});
    });
})();
</script>
