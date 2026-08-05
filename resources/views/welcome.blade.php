@extends('layouts.app')

@section('title', config('app.name', 'Sistema de Copago'))

@section('content')
    <div class="app-wrapper">
        <main class="app-container">
            <header class="hero-card">
                <div class="row align-items-center gy-4">
                    <div class="col-lg-2 col-md-3 text-center text-md-start">
                        <img src="{{ asset('img/hospital.png') }}" class="logo-hospital img-fluid" alt="Logo del hospital">
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

            {{-- Aviso con el resultado de la última carga (aparece después de subir un Excel) --}}
            @if (session('resumen'))
                @php $resumen = session('resumen'); @endphp
                <div class="alert alert-{{ $resumen['modo'] === 'persistir' ? 'success' : 'info' }} mt-3" role="alert">
                    <strong>{{ $resumen['modo'] === 'persistir' ? 'Guardado.' : 'Vista previa (dry-run, no se guardó nada).' }}</strong>
                    Filas leídas: {{ $resumen['filas_leidas'] }} ·
                    Unificados: {{ $resumen['unificados'] }} ·
                    Sin duplicado: {{ $resumen['standalone'] }} ·
                    Para revisión manual: {{ $resumen['revision'] }}

                    @if (isset($resumen['batch_id']))
                        <a href="{{ route('copagos.export', ['batch_id' => $resumen['batch_id']]) }}"
                            class="btn btn-sm btn-outline-dark ms-2">
                            <i class="bi bi-download"></i> Descargar este lote
                        </a>
                    @endif
                </div>

                @if (count(session('revision', [])) > 0)
                    <div class="alert alert-warning mt-2">
                        <strong>{{ count(session('revision')) }} grupo(s) para revisión manual:</strong>
                        <ul class="mb-0">
                            @foreach (session('revision') as $grupo)
                                <li>{{ $grupo['filas'][0]['paciente'] ?? '(sin paciente)' }} — {{ $grupo['motivo'] }}
                                    ({{ count($grupo['filas']) }} filas)</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif

            <section class="controls-section">
                <form method="GET" action="{{ route('copagos.index') }}" class="filters">
                    <div class="filter-item">
                        <i class="bi bi-person-badge"></i>
                        <input class="filter-input" type="text" name="medico" value="{{ $filtros['medico'] ?? '' }}"
                            placeholder="Médico" aria-label="Filtro médico">
                    </div>
                    <div class="filter-item">
                        <i class="bi bi-calendar-event"></i>
                        <input class="filter-input" type="month" name="periodo" value="{{ $filtros['periodo'] ?? '' }}"
                            aria-label="Filtro mes y año">
                    </div>
                    <div class="filter-item">
                        <i class="bi bi-person-lines-fill"></i>
                        <input class="filter-input" type="text" name="paciente" value="{{ $filtros['paciente'] ?? '' }}"
                            placeholder="Paciente" aria-label="Filtro paciente">
                    </div>
                    <div class="filter-item">
                        <i class="bi bi-building"></i>
                        <input class="filter-input" type="text" name="cobertura" value="{{ $filtros['cobertura'] ?? '' }}"
                            placeholder="Obra social" aria-label="Filtro obra social">
                    </div>

                    <div class="action-buttons">
                        <button type="button" class="action-button" title="Nuevo registro" data-bs-toggle="modal"
                            data-bs-target="#modalNuevoRegistro">
                            <i class="bi bi-file-earmark-plus"></i>
                            <p>Nuevo Registro</p>
                        </button>
                        @if (isset($resumen['batch_id']))
                            <a href="{{ route('copagos.export', ['batch_id' => $resumen['batch_id']]) }}"
                                class="action-button" title="Descargar reporte">
                                <i class="bi bi-download"></i>
                                <p>Descargar Reporte</p>
                            </a>
                        @endif
                        <button type="submit" class="search-button" title="Buscar">
                            <i class="bi bi-search"></i>
                        </button>
                        <a href="{{ route('copagos.index') }}" class="clear-filters-button" title="Limpiar filtros">Limpiar
                            filtros</a>
                    </div>
                </form>
            </section>

            <section class="content-area">
                <div class="table-card">
                    <div class="table-card-header">
                        <div>
                            <p class="eyebrow">Vista general</p>
                            <h2>Últimos copagos registrados</h2>
                        </div>
                    </div>

                    @if ($rows->isEmpty())
                        <div class="table-placeholder">
                            <p>Todavía no hay copagos cargados. Usá "Nuevo registro" para subir el primer Excel.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Médico</th>
                                        <th>Paciente</th>
                                        <th>Obra social</th>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Prestación</th>
                                        <th>Código</th>
                                        <th>Tipo</th>
                                        <th class="text-end">Precio</th>
                                        <th class="text-end">Copago</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr>
                                            <td>{{ $row['medico'] }}</td>
                                            <td>{{ $row['paciente'] }}</td>
                                            <td>{{ $row['cobertura'] }}</td>
                                            <td>{{ $row['fecha'] }}</td>
                                            <td>{{ $row['hora'] }}</td>
                                            <td>{{ $row['prestacion'] }}</td>
                                            <td>{{ $row['codigo'] }}</td>
                                            <td>{{ $row['tipo'] ?? '—' }}</td>
                                            <td class="text-end">$ {{ number_format((float) $row['precio'], 2, ',', '.') }}</td>
                                            <td class="text-end">$ {{ number_format((float) $row['copago'], 2, ',', '.') }}</td>
                                            <td class="text-end">$ {{ number_format((float) $row['total'], 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>
        </main>
    </div>

    {{-- Modal: Nuevo registro (subir Excel) --}}
    <div class="modal fade" id="modalNuevoRegistro" tabindex="-1" aria-labelledby="modalNuevoRegistroLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('copagos.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalNuevoRegistroLabel">Nuevo registro — subir Excel</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="archivo" class="form-label">Archivo Excel (.xlsx)</label>
                            <input type="file" class="form-control" id="archivo" name="archivo" accept=".xlsx,.xls"
                                required>
                        </div>

                        <input type="hidden" name="modo" value="persistir">
                        
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Procesar archivo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
