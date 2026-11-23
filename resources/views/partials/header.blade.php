<header class="hero-card">
    <div class="row align-items-center gy-4">
        <div class="col-lg-2 col-md-3 text-center text-md-start">
            <a href="{{ route('copagos.index') }}">
                <img src="{{ asset('img/hospital.png') }}" class="logo-hospital img-fluid" alt="Logo del hospital">
            </a>
        </div>
        <div class="col-lg-7 col-md-5 text-center text-md-center">
            <p class="eyebrow">Hospital Universitario</p>
            <h1 class="sistema">Sistema de Copago</h1>
        </div>
        <div class="col-lg-3 col-md-4 text-center text-md-end">
            @auth
            <div class="usuario">
                <img src="{{ asset('img/aaaaaaa.png') }}" class="foto-usuario" alt="Foto de usuario">
                <div class="user-details">
                    <div class="datos">
                        <strong>{{ Auth::user()->name }}</strong>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="logout-form">
                        @csrf
                        <button type="submit" class="logout-icon logout-button" title="Cerrar sesión">
                            <i class="bi bi-box-arrow-right"></i>
                            <span class="logout-text">Cerrar sesión</span>
                        </button>
                    </form>
                </div>
            </div>
            @endauth
        </div>
    </div>
</header>

{{-- Mensajes de una sola vez (después de eliminar, de un informe vencido, etc.) --}}
@if (session('status'))
    <div class="alert alert-success mt-3" role="alert">{{ session('status') }}</div>
@endif
@if (session('warning'))
    <div class="alert alert-warning mt-3" role="alert">{{ session('warning') }}</div>
@endif
