<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', 'ClimaQuest'))</title>

        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('theme');
                    var dark = stored
                        ? stored === 'dark'
                        : window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (dark) {
                        document.documentElement.classList.add('dark');
                    }
                } catch (error) {
                    // Sin almacenamiento: se usa el tema del sistema.
                }
            })();
        </script>

        @viteReactRefresh
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body class="min-h-screen">
        <div class="app-bg" aria-hidden="true"></div>

        @auth
            @php
                $weatherProps = [
                    'location' => \App\Support\LocationResolver::forUser(auth()->user()),
                    'weatherUrl' => route('weather.summary'),
                    'placeUrl' => route('weather.place'),
                    'weatherLabels' => trans('weather'),
                ];
            @endphp

            <div data-react-component="WeatherAmbience" data-react-props="{{ json_encode($weatherProps) }}"></div>

            <div class="flex min-h-screen">
                @include('layouts.partials.sidebar')

                <div class="flex min-w-0 flex-1 flex-col">
                    <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-slate-200/70 bg-white/60 px-4 backdrop-blur-xl md:px-6 dark:border-white/10 dark:bg-slate-950/50">
                        <div class="flex items-center gap-2">
                            <span class="text-base font-semibold text-indigo-600 md:hidden dark:text-indigo-400">
                                {{ config('app.name', 'ClimaQuest') }}
                            </span>
                            <span class="hidden text-sm font-medium text-slate-500 md:inline dark:text-slate-400">
                                @yield('title', 'Panel')
                            </span>
                        </div>

                        <nav class="flex items-center gap-2">
                            <div class="hidden sm:block" data-react-component="WeatherStatus" data-react-props="{{ json_encode(['weatherLabels' => trans('weather')]) }}"></div>
                            <div data-react-component="ThemeToggle"></div>
                            <a href="{{ route('activities.create') }}" class="btn-primary">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                <span class="hidden sm:inline">Nueva actividad</span>
                            </a>
                        </nav>
                    </header>

                    <main class="flex-1 px-4 py-6 md:px-6">
                        @include('layouts.partials.flash')
                        @yield('content')
                    </main>
                </div>
            </div>
        @else
            <header class="border-b border-slate-200/70 bg-white/60 backdrop-blur-xl dark:border-white/10 dark:bg-slate-950/50">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                    <a href="{{ route('home') }}" class="text-lg font-semibold text-indigo-600 dark:text-indigo-400">
                        {{ config('app.name', 'ClimaQuest') }}
                    </a>
                    <nav class="flex items-center gap-2 text-sm">
                        <div data-react-component="ThemeToggle"></div>
                        <a href="{{ route('login') }}" class="btn-secondary">Entrar</a>
                        <a href="{{ route('register') }}" class="btn-primary">Registro</a>
                    </nav>
                </div>
            </header>

            <main class="mx-auto max-w-6xl px-4 py-6">
                @include('layouts.partials.flash')
                @yield('content')
            </main>
        @endauth
    </body>
</html>
