<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <title>{{ config('app.name', 'Cell Ulagam') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        /* Custom scrollbar for dark theme */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(139, 92, 246, 0.5);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(139, 92, 246, 0.7);
        }

        /* Override default form styles for dark theme */
        input[type="email"],
        input[type="password"],
        input[type="text"] {
            background-color: rgba(255, 255, 255, 0.05) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
            color: white !important;
        }

        input[type="email"]::placeholder,
        input[type="password"]::placeholder,
        input[type="text"]::placeholder {
            color: rgba(148, 163, 184, 0.8) !important;
        }

        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="text"]:focus {
            border-color: rgb(139, 92, 246) !important;
            box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.3) !important;
        }

        input[type="checkbox"] {
            background-color: rgba(255, 255, 255, 0.1) !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
        }

        input[type="checkbox"]:checked {
            background-color: rgb(139, 92, 246) !important;
            border-color: rgb(139, 92, 246) !important;
        }

        label {
            color: rgb(203, 213, 225) !important;
        }
    </style>
</head>

<body class="font-sans antialiased">
    <div
        class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900">
        <!-- Logo & Branding -->
        <div class="mb-6 text-center">
            <a href="/" wire:navigate class="inline-block">
                <div
                    class="w-20 h-20 rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-500 flex items-center justify-center shadow-2xl shadow-violet-500/30 mx-auto mb-4 transform hover:scale-105 transition-transform">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white">Cell Ulagam</h1>
                <p class="text-sm text-slate-400">Sales Manager</p>
            </a>
        </div>

        <!-- Auth Card -->
        <div
            class="w-full sm:max-w-md px-6 py-8 bg-white/5 backdrop-blur-xl border border-white/10 shadow-2xl overflow-hidden rounded-3xl">
            {{ $slot }}
        </div>

        <!-- Footer -->
        <p class="mt-8 text-slate-500 text-sm">© {{ date('Y') }} Cell Ulagam. All rights reserved.</p>
    </div>
</body>

</html>