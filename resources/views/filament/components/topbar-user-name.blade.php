@php
    $user = filament()->auth()->user();
    $name = $user?->name;
@endphp

@if ($user && filled($name))
    <span
        id="bexia-user-menu-name-source"
        class="bexia-user-menu-name-source"
        data-user-name="{{ e($name) }}"
        aria-hidden="true"
    ></span>

    @once
        <style>
            .bexia-user-menu-name-source {
                display: none !important;
            }

            .bexia-user-menu-trigger-name {
                display: inline-flex;
                align-items: center;
                max-width: 12rem;
                margin-inline-end: .15rem;
                color: rgb(51, 65, 85);
                font-size: .82rem;
                font-weight: 700;
                line-height: 1rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .fi-user-menu button,
            .fi-user-menu .fi-dropdown-trigger button {
                display: inline-flex !important;
                align-items: center !important;
                gap: .45rem !important;
                min-height: 2.25rem;
                border-radius: 9999px !important;
                padding-inline: .35rem .45rem !important;
                transition: background-color .15s ease, box-shadow .15s ease;
            }

            .fi-user-menu button:hover,
            .fi-user-menu .fi-dropdown-trigger button:hover {
                background: rgb(248, 250, 252) !important;
            }

            @media (max-width: 900px) {
                .bexia-user-menu-trigger-name {
                    max-width: 8rem;
                }
            }

            @media (max-width: 760px) {
                .bexia-user-menu-trigger-name {
                    display: none !important;
                }
            }
        </style>

        <script>
            (function () {
                function installBexiaUserMenuName() {
                    var source = document.getElementById('bexia-user-menu-name-source');

                    if (!source) {
                        return;
                    }

                    var userName = source.getAttribute('data-user-name') || '';

                    if (!userName.trim()) {
                        return;
                    }

                    var userMenu =
                        document.querySelector('.fi-user-menu') ||
                        source.previousElementSibling;

                    if (!userMenu) {
                        return;
                    }

                    var button =
                        userMenu.querySelector('button') ||
                        userMenu.closest('.fi-dropdown')?.querySelector('button');

                    if (!button) {
                        return;
                    }

                    if (button.querySelector('.bexia-user-menu-trigger-name')) {
                        return;
                    }

                    var label = document.createElement('span');
                    label.className = 'bexia-user-menu-trigger-name';
                    label.textContent = userName;
                    label.title = userName;

                    var avatar =
                        button.querySelector('img') ||
                        button.querySelector('svg') ||
                        button.firstElementChild;

                    if (avatar && avatar.parentNode === button) {
                        button.insertBefore(label, avatar);
                    } else {
                        button.prepend(label);
                    }

                    source.remove();
                }

                document.addEventListener('DOMContentLoaded', installBexiaUserMenuName);
                document.addEventListener('livewire:navigated', installBexiaUserMenuName);

                setTimeout(installBexiaUserMenuName, 100);
                setTimeout(installBexiaUserMenuName, 500);
            })();
        </script>
    @endonce
@endif
