@extends('layouts.app')

@section('title', $informe->nombre_archivo . ' · ' . config('app.name', 'Sistema de Copago'))

@section('content')
    <div class="app-wrapper">
        <main class="app-container">
            @include('partials.header')

            <section class="controls-section">
                <div>
                    <a href="{{ route('copagos.index') }}" class="text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Volver a mis informes
                    </a>
                    <h2 class="mt-2 mb-1">
                        <i class="bi bi-file-earmark-spreadsheet"></i> {{ $informe->nombre_archivo }}
                    </h2>
                    <p class="text-muted mb-0">
                        Subido el {{ \App\Models\PaymentReport::horaLocal($informe->created_at) }} ·
                        se elimina a las {{ \App\Models\PaymentReport::horaLocal($informe->expires_at, 'H:i') }}
                        (en {{ $informe->tiempo_restante }})
                    </p>
                </div>

                <div class="action-buttons">
                    <a href="{{ route('informes.export', $informe) }}" class="action-button" title="Descargar Excel">
                        <i class="bi bi-download"></i>
                        <p>Descargar Excel</p>
                    </a>
                    <form action="{{ route('informes.destroy', $informe) }}" method="POST"
                        onsubmit="return confirm('¿Eliminar este informe? No se puede deshacer.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger" title="Eliminar informe">
                            <i class="bi bi-trash"></i> Eliminar informe
                        </button>
                    </form>
                </div>
            </section>

            <div class="alert alert-info" role="note">
                <i class="bi bi-clock-history"></i>
                Este informe es temporal. Descargá el Excel procesado si necesitás conservarlo.
            </div>

            {{-- Resumen del procesamiento --}}
            <section class="row g-3 mb-4">
                @foreach ([
                    'Filas leídas' => $informe->filas_leidas,
                    'Unificados' => $informe->unificados,
                    'Sin duplicado' => $informe->standalone,
                    'Grupos a revisar' => $informe->revision,
                ] as $etiqueta => $valor)
                    <div class="col-6 col-lg-3">
                        <div class="summary-card h-100">
                            <p class="card-label">{{ $etiqueta }}</p>
                            <h2>{{ $valor }}</h2>
                        </div>
                    </div>
                @endforeach
            </section>

            @if ($revision->isNotEmpty())
                <div class="alert alert-warning">
                    <strong>{{ $revision->count() }} grupo(s) para revisión manual:</strong>
                    <ul class="mb-0">
                        @foreach ($revision as $grupo)
                            <li>{{ $grupo->payments->first()?->paciente ?? '(sin paciente)' }} — {{ $grupo->motivo }}
                                ({{ $grupo->cantidad_filas }} filas)</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="controls-section">
                <form method="GET" action="{{ route('informes.show', $informe) }}" class="filters">
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
                        <button type="submit" class="search-button" title="Buscar">
                            <i class="bi bi-search"></i>
                        </button>
                        <a href="{{ route('informes.show', $informe) }}" class="clear-filters-button"
                            title="Limpiar filtros">Limpiar filtros</a>
                    </div>
                </form>
            </section>

            <section class="content-area">
                <div class="table-card">
                    <div class="table-card-header">
                        <div>
                            <p class="eyebrow">Vista previa</p>
                            <h2>Copagos del informe</h2>
                        </div>
                        <span class="text-muted">{{ $rows->total() }} fila(s)</span>
                    </div>

                    @if ($rows->isEmpty())
                        <div class="table-placeholder">
                            <p>No hay filas que coincidan con los filtros.</p>
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
                                        <th>Unificación</th>
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
                                            <td>{{ $row['estado_unificacion'] }}</td>
                                            <td class="text-end">$ {{ number_format((float) $row['precio'], 2, ',', '.') }}</td>
                                            <td class="text-end">$ {{ number_format((float) $row['copago'], 2, ',', '.') }}</td>
                                            <td class="text-end">$ {{ number_format((float) $row['total'], 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            {{ $rows->withQueryString()->links() }}
                        </div>
                    @endif
                </div>
            </section>
        </main>
    </div>
@endsection
