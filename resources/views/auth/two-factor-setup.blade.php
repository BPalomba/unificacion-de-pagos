@extends('layouts.app')

@section('title', 'Configurar verificación en dos pasos · ' . config('app.name', 'Sistema de Copago'))

@section('content')
    <div class="auth-page">
        <main class="auth-card auth-card-wide">
            <img src="{{ asset('img/hospital.png') }}" class="auth-logo" alt="Logo del hospital">
            <h1 class="auth-title">Configurá la verificación en dos pasos</h1>
            <p class="auth-subtitle">
                Para proteger los datos del sistema, además de tu contraseña vas a necesitar un código de tu celular
                cada vez que ingreses. Es obligatorio y se configura una sola vez.
            </p>

            <ol class="auth-steps">
                <li>
                    Instalá una app autenticadora en tu celular:
                    <strong>Google Authenticator</strong> o <strong>Microsoft Authenticator</strong>.
                </li>
                <li>
                    En la app, tocá <em>Agregar cuenta</em> y escaneá este código QR:
                    <div class="auth-qr">{!! $qrCodeSvg !!}</div>
                    <details class="auth-manual-key">
                        <summary>¿No podés escanearlo? Ingresá la clave a mano</summary>
                        <code>{{ trim(chunk_split($secretKey, 4, ' ')) }}</code>
                    </details>
                </li>
                <li>
                    Escribí el código de 6 dígitos que te muestra la app:
                    <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-2">
                        @csrf

                        @error('code', 'confirmTwoFactorAuthentication')
                            <div class="alert alert-danger" role="alert">
                                El código no es válido. Revisá que la hora del celular esté bien y probá con el código nuevo.
                            </div>
                        @enderror

                        <input id="code" type="text" name="code" class="form-control auth-code-input mb-3"
                            inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code"
                            aria-label="Código de 6 dígitos" required autofocus>

                        <button type="submit" class="btn btn-primary w-100 auth-submit">Confirmar y activar</button>
                    </form>
                </li>
            </ol>

            <form method="POST" action="{{ route('logout') }}" class="text-center">
                @csrf
                <button type="submit" class="btn btn-link auth-link">Cerrar sesión</button>
            </form>
        </main>
    </div>
@endsection
