<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name') }}</title>

    <link rel="icon" href="{{ $favicon }}" type="image/x-icon">
    <link rel="shortcut icon" href="{{ $favicon }}" type="image/x-icon">

    @stack('meta')

    {{-- Preconnects --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>

    {{-- Material Symbols - load only once --}}
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet">

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>

<body>
    {{-- Toast Notifications --}}
    <livewire:shared.font-toast-notification />

    {{-- Full Website Background Video --}}
    <div class="fixed inset-0 -z-20 bg-slate-950">
    <video
        data-bg-video
        muted
        loop
        playsinline
        preload="metadata"
        poster="{{ asset('assets/images/matrix.webp') }}"
        class="h-full w-full object-cover"
    >
        <source src="{{ asset('assets/videos/matrix1c.mp4') }}" type="video/mp4">
    </video>
</div>

    {{-- Global Overlay --}}
    <div
        class="fixed inset-0 -z-10 bg-[radial-gradient(circle_at_top,rgba(59,130,246,0.30),rgba(15,23,42,0.88)_55%,rgba(2,6,23,0.95)_100%)]">
    </div>

    <div class="fixed inset-0 -z-10 bg-slate-950/30"></div>

    {{-- Navbar --}}
    <div class="mx-auto max-w-350 px-4 py-4 sm:px-6 lg:px-8">
        <livewire:shared.navbar />
    </div>

    {{-- Auth success toast --}}
    <div
        x-data="{ show: false, message: '' }"
        x-on:auth-success.window="
            show = true;
            message = $event.detail.message;
            setTimeout(() => show = false, 3000);
        "
        x-show="show"
        x-transition
        class="fixed right-5 top-5 z-9999 rounded-2xl bg-emerald-500 px-5 py-3 text-white shadow-xl"
        style="display: none;"
    >
        <span x-text="message"></span>
    </div>

    {{-- Main content --}}
    <main>
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <livewire:shared.footer />

    {{-- Auth Modal --}}
    <livewire:auth.auth-modal wire:key="global-auth-modal" />

    @stack('scripts')

    @livewireScripts

    <script>
    (() => {
        const openAuthFromUrl = () => {
            const params = new URLSearchParams(window.location.search);

            if (params.get('openAuth') === 'login') {
                window.dispatchEvent(new CustomEvent('open-auth', {
                    detail: {
                        mode: 'login'
                    }
                }));
            }
        };

        const startBackgroundVideo = () => {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            const video = document.querySelector('[data-bg-video]');

            if (!video) {
                return;
            }

            const playPromise = video.play();

            if (playPromise !== undefined) {
                playPromise.catch(() => {});
            }
        };

        document.addEventListener('DOMContentLoaded', () => {
            openAuthFromUrl();

            setTimeout(startBackgroundVideo, 300);
        });
    })();
</script>
</body>

</html>