@extends('layouts.app')

@section('title', config('app.name', 'Sistema de Copago'))

@section('content')
    <div class="app-wrapper">
        <main class="app-container">
            @include('partials.header')

            {{-- Errores al subir (validación o Excel ilegible) --}}
            @if ($errors->any())
                <div class="alert alert-danger mt-3" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="controls-section">
                <div class="alert alert-info mb-0 flex-grow-1" role="note">
                    <i class="bi bi-clock-history"></i>
                    Los informes se guardan durante <strong>{{ \App\Models\PaymentReport::duracionLegible() }}</strong>
                    desde que se suben y solo los ves vos. Después se eliminan: descargá el Excel procesado para conservarlo.
                </div>

                <div class="action-buttons">
                    <button type="button" class="action-button" title="Nuevo informe" data-bs-toggle="modal"
                        data-bs-target="#modalNuevoInforme">
                        <i class="bi bi-file-earmark-plus"></i>
                        <p>Nuevo informe</p>
                    </button>
                </div>
            </section>

            <section class="content-area">
                <div class="table-card">
                    <div class="table-card-header">
                        <div>
                            <p class="eyebrow">Mis informes</p>
                            <h2>Informes subidos</h2>
                        </div>
                    </div>

                    @if ($informes->isEmpty())
                        <div class="table-placeholder">
                            <p>No tenés informes vigentes. Usá "Nuevo informe" para subir un Excel.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Archivo</th>
                                        <th>Subido</th>
                                        <th>Vence</th>
                                        <th class="text-end">Filas leídas</th>
                                        <th class="text-end">Unificados</th>
                                        <th class="text-end">A revisar</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($informes as $informe)
                                        <tr>
                                            <td>
                                                <i class="bi bi-file-earmark-spreadsheet"></i>
                                                {{ $informe->nombre_archivo }}
                                            </td>
                                            <td>{{ \App\Models\PaymentReport::horaLocal($informe->created_at) }}</td>
                                            <td>
                                                {{ \App\Models\PaymentReport::horaLocal($informe->expires_at, 'H:i') }}
                                                <small class="text-muted">(en {{ $informe->tiempo_restante }})</small>
                                            </td>
                                            <td class="text-end">{{ $informe->filas_leidas }}</td>
                                            <td class="text-end">{{ $informe->unificados }}</td>
                                            <td class="text-end">
                                                @if ($informe->revision > 0)
                                                    <span class="badge text-bg-warning">{{ $informe->revision }}</span>
                                                @else
                                                    0
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('informes.show', $informe) }}"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> Ver
                                                </a>
                                            </td>
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

    {{-- Modal: Nuevo informe (subir Excel) --}}
    <div class="modal fade" id="modalNuevoInforme" tabindex="-1" aria-labelledby="modalNuevoInformeLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('informes.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalNuevoInformeLabel">Nuevo informe — subir Excel</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="archivo" class="form-label">Archivo Excel (.xlsx)</label>
                            <input type="file" class="form-control" id="archivo" name="archivo" accept=".xlsx,.xls"
                                required>
                        </div>
                        <p class="text-muted small mb-0">
                            Al procesarlo vas directo al informe, donde podés revisarlo y descargar el Excel unificado.
                        </p>
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
