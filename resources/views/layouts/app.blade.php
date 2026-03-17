<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? config('app.name') }}</title>

    {{-- Favicon --}}
    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%231337ec'/><text x='50%25' y='54%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-size='18' font-weight='700' fill='white'>A</text></svg>">
    <link rel="apple-touch-icon"
        href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%231337ec'/><text x='50%25' y='54%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-size='18' font-weight='700' fill='white'>A</text></svg>">
    <!-- Alpine Plugins -->
    <!-- <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/mask@3.x.x/dist/cdn.min.js"></script> -->

    <!-- Alpine Core -->
    <!-- <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script> -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    @filamentStyles
</head>

<body class="bg-background-light dark:bg-background-dark font-display min-h-screen text-slate-900 dark:text-slate-100">
    <div x-data="{ sidebarOpen: false }"
        class="group/design-root relative flex h-auto min-h-screen w-full flex-col overflow-x-hidden">
        <div class="layout-container flex h-full grow flex-col">
            @include('layouts.header')
            <main class="flex flex-1 flex-col px-4 py-6 lg:px-10">
                <div class="max-w-400 mx-auto flex w-full flex-1 flex-col">
                    {{ $slot }}
                </div>
            </main>
            @include('layouts.footer')

            @include('layouts.sidebar')
        </div>
    </div>

    {{-- Global flash toast --}}
    <x-ui.alert />

    {{-- Event-driven toast stack: $dispatch('toast', { message: '...', type: 'success' }) --}}
    <x-ui.toast-stack />

    @livewireScripts
    @filamentScripts
    @livewire('notifications')

    {{-- Notification drawer — must be outside any overflow:hidden container --}}
    <livewire:notification.modal />


    <script src="https://cdn.jsdelivr.net/npm/flowbite@4.0.1/dist/flowbite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.js"></script>
</body>

</html>
