@php
    $resumen = session('resumen');
    $pares = session('pares', []);
    $revision = session('revision', []);
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Unificación de Pagos Duplicados</title>
    <style>
        body { font-family: -apple-system, Segoe UI, Arial, sans-serif; background: #f5f8fa; color: #1b2a41; margin: 0; padding: 2rem; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #0b2545; }
        .card { background: #fff; border: 1px solid #e3e9ef; border-radius: 10px; padding: 1.5rem; margin-bottom: 1.5rem; }
        label { display: block; font-weight: 600; margin-bottom: 0.4rem; }
        input[type=file] { margin-bottom: 1rem; }
        .radio-group { margin-bottom: 1rem; }
        .radio-group label { display: inline-block; font-weight: 400; margin-right: 1.5rem; }
        button { background: #13a89e; color: #fff; border: none; padding: 0.7rem 1.4rem; border-radius: 6px; font-size: 1rem; cursor: pointer; }
        button:hover { background: #0f8a81; }
        .errors { color: #c00; margin-bottom: 1rem; }
        .stats { display: flex; gap: 1rem; margin-top: 1rem; }
        .stat { flex: 1; background: #f5f8fa; border-radius: 8px; padding: 1rem; text-align: center; }
        .stat .num { font-size: 2rem; font-weight: 700; color: #0b2545; }
        .stat .label { font-size: 0.85rem; color: #5b6b82; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.9rem; }
        th, td { text-align: left; padding: 0.5rem; border-bottom: 1px solid #e3e9ef; }
        th { color: #5b6b82; font-size: 0.8rem; text-transform: uppercase; }
        .badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .badge-alta { background: #d7f5e9; color: #1e7b34; }
        .badge-media { background: #fdf1d3; color: #8a6d00; }
    </style>
</head>
<body>
<div class="container">
    <h1>Unificación de Pagos Duplicados</h1>
    <p>Subí el Excel exportado por el sistema del hospital. Se detectan los pares cobertura + Bono a Persona y se muestra un resumen antes (o al momento) de guardar.</p>
    <p><a href="{{ route('payments.export') }}">⬇ Descargar Excel unificado con todo lo guardado hasta ahora (todos los lotes)</a></p>

    <div class="card">
        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('payments.upload.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <label for="archivo">Archivo Excel (.xlsx)</label>
            <input type="file" id="archivo" name="archivo" accept=".xlsx,.xls" required>

            <div class="radio-group">
                <label><input type="radio" name="modo" value="dry_run" checked> Dry-run (solo analizar, no guardar)</label>
                <label><input type="radio" name="modo" value="persistir"> Persistir en la base de datos</label>
            </div>

            <button type="submit">Procesar archivo</button>
        </form>
    </div>

    @if ($resumen)
        <div class="card">
            <h2>Resultado</h2>
            <p>Filas leídas: <strong>{{ $resumen['filas_leidas'] }}</strong> — Modo: <strong>{{ $resumen['modo'] === 'persistir' ? 'Persistido en base' : 'Dry-run (sin guardar)' }}</strong></p>
            @if (isset($resumen['batch_id']))
                <p>Batch ID: <code>{{ $resumen['batch_id'] }}</code></p>
                <p>
                    <a href="{{ route('payments.export', ['batch_id' => $resumen['batch_id']]) }}"
                       style="display:inline-block;background:#0b2545;color:#fff;padding:0.6rem 1.2rem;border-radius:6px;text-decoration:none;font-weight:600;">
                        ⬇ Descargar Excel unificado (este lote)
                    </a>
                </p>
            @endif

            <div class="stats">
                <div class="stat">
                    <div class="num">{{ $resumen['unificados'] }}</div>
                    <div class="label">Unificados</div>
                </div>
                <div class="stat">
                    <div class="num">{{ $resumen['standalone'] }}</div>
                    <div class="label">Sin duplicado</div>
                </div>
                <div class="stat">
                    <div class="num">{{ $resumen['revision'] }}</div>
                    <div class="label">Para revisión manual</div>
                </div>
            </div>
        </div>

        @if (count($pares) > 0)
            <div class="card">
                <h2>Pares unificados (primeros 20)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Prestación</th>
                            <th>Monto cobertura</th>
                            <th>Monto bono</th>
                            <th>Total</th>
                            <th>Confianza</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pares as $par)
                            <tr>
                                <td>{{ $par['cobertura']['paciente'] }}</td>
                                <td>{{ $par['cobertura']['prestacion'] }}</td>
                                <td>$ {{ number_format($par['monto_cobertura'], 2, ',', '.') }}</td>
                                <td>$ {{ number_format($par['monto_bono_persona'], 2, ',', '.') }}</td>
                                <td>$ {{ number_format($par['monto_total'], 2, ',', '.') }}</td>
                                <td><span class="badge badge-{{ $par['confianza'] }}">{{ $par['confianza'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if (count($revision) > 0)
            <div class="card">
                <h2>Casos para revisión manual</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Motivo</th>
                            <th>Filas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($revision as $grupo)
                            <tr>
                                <td>{{ $grupo['filas'][0]['paciente'] ?? '(sin paciente)' }}</td>
                                <td>{{ $grupo['motivo'] }}</td>
                                <td>{{ count($grupo['filas']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
</body>
</html>
