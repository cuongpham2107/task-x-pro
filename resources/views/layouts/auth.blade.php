<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title ?? 'ProManager' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-display bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 antialiased">
    <div class="flex min-h-screen">
        <!-- Left Side: Background Image & Slogan -->
        <div class="hidden lg:flex lg:w-2/3 flex-col justify-end items-center relative overflow-hidden">
            <!-- Background Image (only show if file is present in public/images) -->
            @if (file_exists(public_path('images/background-login.jpg')))
                <img src="{{ asset('images/background-login.jpg' ?? '') }}" alt="Professional workspace"
                    class="absolute inset-0 w-full h-full object-cover" />
            @endif

            <!-- Gradient Overlay -->
            <div class="absolute inset-0 bg-linear-to-t from-black/80 via-black/40 to-primary/30"></div>

            <!-- Decorative Glow -->
            <div class="absolute top-0 left-0 w-full h-full pointer-events-none">
                <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-primary/30 rounded-full blur-3xl"></div>
                <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-primary/20 rounded-full blur-3xl">
                </div>
            </div>

            <!-- Text Content -->
            <div class="relative z-10 max-w-xl text-center px-12 pb-16">
                <h1 class="text-white text-5xl font-black leading-tight tracking-tight mb-6 drop-shadow-lg">
                    Quản lý hiệu quả, bứt phá thành công
                </h1>
                <p class="text-white/85 text-lg font-medium drop-shadow-md">
                    Hệ thống quản lý công việc chuyên nghiệp cho doanh nghiệp hiện đại. Tối ưu hóa quy trình, nâng cao
                    năng suất đội ngũ.
                </p>
            </div>
        </div>

        <!-- Right Side: Content -->
        <div
            class="w-full lg:w-1/3 flex items-center justify-center p-6 sm:p-8 lg:p-10 bg-white dark:bg-background-dark relative overflow-hidden">
            {{-- Subtle decorative background --}}
            <div
                class="absolute top-0 right-0 w-72 h-72 bg-primary/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 pointer-events-none">
            </div>
            <div
                class="absolute bottom-0 left-0 w-56 h-56 bg-primary/5 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2 pointer-events-none">
            </div>

            <div class="w-full max-w-110 flex flex-col gap-7 relative z-10">
                <!-- Logo & Heading -->
                <div class="flex flex-col gap-5">
                    <div class="flex items-center gap-3">
                        <div
                            class="size-11 rounded-xl bg-primary/10 ring-1 ring-primary/20 flex items-center justify-center p-1.5 shadow-sm">
                            <img src="{{ asset('images/logo.png') }}" alt="ASGL-logo" class="size-full object-contain">
                        </div>
                        <div class="flex flex-col">
                            <span
                                class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100 leading-none">{{ config('app.name') }}</span>
                            <span
                                class="text-[11px] font-medium text-slate-400 dark:text-slate-500 tracking-widest uppercase mt-4">Project
                                Manager</span>
                        </div>
                    </div>

                    <div
                        class="w-full h-px bg-linear-to-r from-transparent via-slate-200 dark:via-slate-700 to-transparent">
                    </div>

                    {{ $header }}
                </div>

                <!-- Main Content -->
                {{ $slot }}

                <!-- Footer branding -->
                <p class="text-center text-xs text-slate-400 dark:text-slate-600 mt-2">
                    &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                </p>
            </div>
        </div>
    </div>

    {{-- Global flash toast --}}
    {{-- <x-ui.alert /> --}}
</body>

</html>
