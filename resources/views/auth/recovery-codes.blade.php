@extends('layouts.app')

@section('title', 'Códigos de recuperación · ' . config('app.name', 'Sistema de Copago'))

@section('content')
    <div class="auth-page">
        <main class="auth-card auth-card-wide">
            <img src="{{ asset('img/hospital.png') }}" class="auth-logo" alt="Logo del hospital">
            <h1 class="auth-title"><i class="bi bi-shield-check"></i> Verificación activada</h1>

            <div class="alert alert-warning" role="alert">
                <strong>Guardá estos códigos de recuperación en un lugar seguro.</strong>
                Si perdés el celular, cada código te permite entrar una vez. No se van a volver a mostrar.
            </div>

            <ul class="auth-recovery-codes" id="recovery-codes">
                @foreach ($recoveryCodes as $code)
                    <li><code>{{ $code }}</code></li>
                @endforeach
            </ul>

            <div class="d-flex gap-2 mb-4">
                <button type="button" class="btn btn-outline-dark flex-fill" id="copy-codes">
                    <i class="bi bi-clipboard"></i> Copiar
                </button>
                <button type="button" class="btn btn-outline-dark flex-fill" onclick="window.print()">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
            </div>

            <a href="{{ route('copagos.index') }}" class="btn btn-primary w-100 auth-submit">
                Ya los guardé, continuar
            </a>
        </main>
    </div>

    <script>
        document.getElementById('copy-codes').addEventListener('click', function () {
            var codes = Array.from(document.querySelectorAll('#recovery-codes code'))
                .map(function (el) { return el.textContent; })
                .join('\n');
            var button = this;
            navigator.clipboard.writeText(codes).then(function () {
                button.innerHTML = '<i class="bi bi-check2"></i> Copiados';
            });
        });
    </script>
@endsection
