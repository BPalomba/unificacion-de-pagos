@extends('layouts.app')

@section('title', 'Verificación en dos pasos · ' . config('app.name', 'Sistema de Copago'))

@section('content')
    @php $usarRecuperacion = $errors->has('recovery_code') || old('recovery_code'); @endphp

    <div class="auth-page">
        <main class="auth-card">
            <img src="{{ asset('img/hospital.png') }}" class="auth-logo" alt="Logo del hospital">
            <h1 class="auth-title">Verificación en dos pasos</h1>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Código de la app autenticadora --}}
            <form method="POST" action="{{ route('two-factor.login.store') }}" id="form-code"
                @class(['d-none' => $usarRecuperacion])>
                @csrf
                <p class="auth-subtitle">
                    Abrí tu app autenticadora (Google o Microsoft Authenticator) e ingresá el código de 6 dígitos.
                </p>

                <div class="mb-4">
                    <label for="code" class="form-label">Código</label>
                    <input id="code" type="text" name="code" class="form-control auth-code-input"
                        inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code"
                        @if (! $usarRecuperacion) autofocus @endif>
                </div>

                <button type="submit" class="btn btn-primary w-100 auth-submit">Verificar</button>

                <p class="auth-help">
                    ¿No tenés el celular?
                    <a href="#" class="auth-link" data-toggle-2fa>Usar un código de recuperación</a>
                </p>
            </form>

            {{-- Código de recuperación --}}
            <form method="POST" action="{{ route('two-factor.login.store') }}" id="form-recovery"
                @class(['d-none' => ! $usarRecuperacion])>
                @csrf
                <p class="auth-subtitle">
                    Ingresá uno de los códigos de recuperación que guardaste al configurar el acceso.
                    Cada código se puede usar una sola vez.
                </p>

                <div class="mb-4">
                    <label for="recovery_code" class="form-label">Código de recuperación</label>
                    <input id="recovery_code" type="text" name="recovery_code" class="form-control"
                        autocomplete="off" @if ($usarRecuperacion) autofocus @endif>
                </div>

                <button type="submit" class="btn btn-primary w-100 auth-submit">Verificar</button>

                <p class="auth-help">
                    <a href="#" class="auth-link" data-toggle-2fa>Usar el código de la app</a>
                </p>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="text-center">
                @csrf
                <button type="submit" class="btn btn-link auth-link">Cancelar y volver al login</button>
            </form>
        </main>
    </div>

    <script>
        document.querySelectorAll('[data-toggle-2fa]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                var code = document.getElementById('form-code');
                var recovery = document.getElementById('form-recovery');
                code.classList.toggle('d-none');
                recovery.classList.toggle('d-none');
                var visible = code.classList.contains('d-none') ? recovery : code;
                visible.querySelector('input:not([type=hidden])').focus();
            });
        });
    </script>
@endsection
