@extends('layouts.app')

@section('title', 'Crear cuenta')

@section('content')
    <div class="relative mx-auto max-w-md">
        <div class="pointer-events-none absolute -inset-x-10 -top-16 -z-10 h-64">
            <div class="aurora-blob animate-aurora left-[-4%] top-0 h-56 w-56 bg-violet-400/40 dark:bg-violet-500/30"></div>
            <div class="aurora-blob animate-aurora right-[-4%] top-6 h-56 w-56 bg-sky-400/35 dark:bg-sky-500/25" style="animation-delay: -7s"></div>
        </div>

        <div class="glass-glow enter-up p-6">
            <h1 class="font-display text-xl font-semibold heading">Crear cuenta</h1>

            <form method="POST" action="{{ route('register') }}" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="timezone" id="timezone-input" value="{{ old('timezone', config('app.timezone')) }}">

                <div>
                    <label for="name" class="field-label">Nombre</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                        class="field-input">
                    @error('name')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="field-label">Correo</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required
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

                <div>
                    <label for="password_confirmation" class="field-label">Confirmar contraseña</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required
                        class="field-input">
                </div>

                <button type="submit" class="btn-primary w-full">
                    Crear cuenta
                </button>
            </form>

            <p class="mt-4 text-sm text-muted">
                ¿Ya tienes cuenta?
                <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">Inicia sesión</a>
            </p>
        </div>
    </div>

    <script>
        try {
            const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (timezone) {
                document.getElementById('timezone-input').value = timezone;
            }
        } catch (error) {
            // El navegador no expone la zona horaria; se conserva el valor por defecto.
        }
    </script>
@endsection
