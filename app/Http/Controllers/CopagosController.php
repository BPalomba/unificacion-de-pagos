<?php

namespace App\Http\Controllers;

use App\Exports\PaymentsUnifiedExport;
use App\Services\Payments\DataSources\ExcelPaymentSource;
use App\Services\Payments\PaymentDeduplicationService;
use App\Services\Payments\PaymentNormalizer;
use App\Services\Payments\UnifiedPaymentsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class CopagosController extends Controller
{
    /**
     * Pantalla principal: la tabla de "Últimos copagos registrados",
     * con los filtros del header (médico, período, paciente, cobertura).
     */
    public function index(Request $request): View
    {
        $filtros = $request->only(['medico', 'paciente', 'cobertura', 'periodo']);

        $rows = (new UnifiedPaymentsQuery())->rows(null, $filtros);

        return view('welcome', [
            'rows' => $rows->take(50), // "últimos copagos": se limita a los 50 más recientes
            'filtros' => $filtros,
        ]);
    }

    /**
     * Recibe el Excel desde el modal "Nuevo registro".
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls'],
            'modo' => ['required', 'in:dry_run,persistir'],
        ], [], [
            'archivo' => 'archivo Excel',
        ]);

        $path = $request->file('archivo')->store('payments-uploads');
        $fullPath = Storage::path($path);

        $rawRows = (new ExcelPaymentSource($fullPath))->fetch();

        $normalizer = new PaymentNormalizer(config('payments.business_key_fields'));
        $service = new PaymentDeduplicationService(
            normalizer: $normalizer,
            tipoPaciente: config('payments.tipo_paciente'),
            toleranceSeconds: config('payments.numero_time_tolerance_seconds'),
            estadoPriority: config('payments.estado_priority'),
        );

        $analysis = $service->analyze($rawRows);

        $resumen = [
            'filas_leidas' => $rawRows->count(),
            'unificados' => count($analysis['pairs']),
            'standalone' => count($analysis['standalone']),
            'revision' => count($analysis['review']),
            'modo' => $validated['modo'],
        ];

        if ($validated['modo'] === 'persistir') {
            $resultado = $service->persist($analysis, 'excel');
            $resumen['batch_id'] = $resultado['batch_id'];
        }

        return redirect()
            ->route('copagos.index')
            ->with('resumen', $resumen)
            ->with('revision', $analysis['review']);
    }

    /**
     * Botón "Descargar reporte": exporta lo que haya en la base
     * (respetando los filtros activos, si los hay).
     */
    public function export(Request $request)
    {
        $batchId = $request->query('batch_id');

        return Excel::download(
            new PaymentsUnifiedExport($batchId),
            $batchId ? "copagos_{$batchId}.xlsx" : 'copagos.xlsx'
        );
    }
}   