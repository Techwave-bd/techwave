<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    @stack('head')
</head>

<body>
    {{ $slot }}

    @stack('scripts')
    @livewireScripts
</body>

</html>
