@extends('layouts.app')

@section('title', 'Iniciar sesión')

@section('content')
    <div class="relative mx-auto max-w-md">
        <div class="pointer-events-none absolute -inset-x-10 -top-16 -z-10 h-64">
            <div class="aurora-blob animate-aurora left-[-4%] top-0 h-56 w-56 bg-indigo-400/40 dark:bg-indigo-500/30"></div>
            <div class="aurora-blob animate-aurora right-[-4%] top-6 h-56 w-56 bg-sky-400/35 dark:bg-sky-500/25" style="animation-delay: -7s"></div>
        </div>

        <div class="glass-glow enter-up p-6">
            <h1 class="font-display text-xl font-semibold heading">Iniciar sesión</h1>

            <form method="POST" action="{{ route('login') }}" class="mt-4 space-y-4">
                @csrf

                <div>
                    <label for="email" class="field-label">Correo</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                        class="field-input">
                    @error('email')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="field-label">Contraseña</label>
                    <input id="password" type="password" name="password" required
                        class="field-input">
                    @error('password')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-white/20 dark:bg-white/10">
                    Recordarme
                </label>

                <button type="submit" class="btn-primary w-full">
                    Entrar
                </button>
            </form>

            <p class="mt-4 text-sm text-muted">
                ¿No tienes cuenta?
                <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">Regístrate</a>
            </p>
        </div>
    </div>
@endsection
