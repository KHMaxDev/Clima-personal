@php
    $items = [
        ['label' => 'Inicio', 'route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'home'],
        ['label' => 'Calendario', 'route' => 'calendar', 'match' => 'calendar', 'icon' => 'calendar'],
        ['label' => 'Mis actividades', 'route' => 'activities.index', 'match' => 'activities.*', 'icon' => 'activities'],
        ['label' => 'Pronóstico', 'route' => 'forecast', 'match' => 'forecast', 'icon' => 'forecast'],
        ['label' => 'Historial', 'route' => 'history', 'match' => 'history', 'icon' => 'history'],
        ['label' => 'Asistente IA', 'route' => 'assistant', 'match' => 'assistant', 'icon' => 'assistant'],
    ];

    $navItems = array_map(fn ($item) => [
        'label' => $item['label'],
        'href' => route($item['route']),
        'active' => request()->routeIs($item['match']),
        'icon' => $item['icon'],
    ], $items);

    $navProps = [
        'items' => $navItems,
        'comingSoon' => [
            ['label' => 'Configuración', 'icon' => 'settings'],
        ],
    ];
@endphp

<aside class="hidden w-64 shrink-0 border-r border-slate-200/70 bg-white/60 backdrop-blur-xl md:flex md:flex-col dark:border-white/10 dark:bg-slate-950/50">
    <div class="flex h-16 items-center gap-2 border-b border-slate-200/70 px-5 dark:border-white/10">
        <span class="text-lg font-semibold tracking-tight text-indigo-600 dark:text-indigo-400">
            {{ config('app.name', 'ClimaQuest') }}
        </span>
    </div>

    <div data-react-component="SidebarNav" data-react-props="{{ json_encode($navProps) }}" class="flex-1 flex flex-col"></div>

    @auth
        <div class="border-t border-slate-200/70 p-4 dark:border-white/10">
            <p class="truncate text-sm font-medium heading">
                {{ auth()->user()->name }}
            </p>
            <p class="truncate text-xs text-muted">
                {{ auth()->user()->email }}
            </p>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="btn-secondary w-full">
                    Cerrar sesión
                </button>
            </form>
        </div>
    @endauth
</aside>
