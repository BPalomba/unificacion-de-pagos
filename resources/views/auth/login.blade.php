@extends('layouts.app')

@section('title', 'Iniciar sesión · ' . config('app.name', 'Sistema de Copago'))

@section('content')
    <div class="auth-page">
        <main class="auth-card">
            <img src="{{ asset('img/hospital.png') }}" class="auth-logo" alt="Logo del hospital">
            <p class="auth-eyebrow">Hospital Universitario</p>
            <h1 class="auth-title">Sistema de Copago</h1>
            <p class="auth-subtitle">Ingresá con tu usuario para continuar.</p>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                        class="form-control @error('email') is-invalid @enderror" required autofocus
                        autocomplete="username">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input id="password" type="password" name="password"
                        class="form-control @error('password') is-invalid @enderror" required
                        autocomplete="current-password">
                </div>

                <div class="form-check mb-4">
                    <input id="remember" type="checkbox" name="remember" class="form-check-input"
                        @checked(old('remember'))>
                    <label for="remember" class="form-check-label">Recordarme en este equipo</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 auth-submit">
                    <i class="bi bi-box-arrow-in-right"></i> Ingresar
                </button>
            </form>

            <p class="auth-help">
                ¿Olvidaste tu contraseña o perdiste el acceso? Pedile a un administrador del sistema que la restablezca.
            </p>
        </main>
    </div>
@endsection
