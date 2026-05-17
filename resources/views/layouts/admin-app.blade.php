<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        use App\Models\SiteSetting;

        $siteSetting = SiteSetting::current();

        $favicon = $siteSetting->favicon
            ? asset('storage/' . $siteSetting->favicon)
            : asset('assets/images/logo/logo.png');
    @endphp

    <link rel="icon" href="{{ $favicon }}" type="image/x-icon">
    <link rel="shortcut icon" href="{{ $favicon }}" type="image/x-icon">

    <title>{{ $title ?? config('app.name') }}</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />

    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">

    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        .font-manrope {
            font-family: 'Manrope', sans-serif;
        }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body x-data="{ sidebarOpen: false, sidebarCollapsed: false }" class="bg-background text-on-background min-h-screen">

    <!-- Mobile Overlay -->
    <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
        class="fixed inset-0 bg-black/40 z-40 lg:hidden"></div>

    <!-- Main Wrapper -->
    <div :class="sidebarCollapsed ? 'lg:ml-20' : 'lg:ml-64'"
        class="flex flex-col min-h-screen transition-all duration-300">

        <!-- Topbar -->
        <livewire:admin.shared.header />

        {{-- Sidebar --}}
        <livewire:admin.shared.sidebar />

        <!-- Content -->
        <main class="p-4 sm:p-6 lg:p-stack-lg max-w-425 mx-auto w-full">
            {{ $slot }}
        </main>

        {{-- Toast Notifications --}}
        <livewire:common.toast-notification />


        <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
        @stack('scripts')
        @livewireScripts
    </div>

</body>

</html>
