@if (session('status'))
    <div class="glass-soft mb-4 border-emerald-300/60 bg-emerald-50/70 px-4 py-2 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
        {{ session('status') }}
    </div>
@endif

@if (session('warning'))
    <div class="glass-soft mb-4 border-amber-300/60 bg-amber-50/70 px-4 py-2 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
        {{ session('warning') }}
    </div>
@endif
