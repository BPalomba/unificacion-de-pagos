<?php

namespace App\Http\Controllers;

use App\Exports\PaymentsUnifiedExport;
use App\Services\Payments\DataSources\ExcelPaymentSource;
use App\Services\Payments\PaymentDeduplicationService;
use App\Services\Payments\PaymentNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class PaymentUnificationController extends Controller
{
    public function create(): View
    {
        return view('payments.upload');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls'],
            'modo' => ['required', 'in:dry_run,persistir'],
        ], [], [
            'archivo' => 'archivo Excel',
        ]);

        $path = $request->file('archivo')->store('payments-uploads');

        // Storage::path() resuelve la ruta real según el disco configurado
        // (en Laravel 11 el disco "local" guarda en storage/app/private/...,
        // no en storage/app/... como en versiones anteriores).
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
            ->route('payments.upload')
            ->with('resumen', $resumen)
            ->with('pares', array_slice($analysis['pairs'], 0, 20))
            ->with('revision', $analysis['review']);
    }

    /**
     * Descarga el Excel con el resultado ya unificado. Si se pasa
     * ?batch_id=..., exporta solo ese lote; si no, exporta todo lo que
     * haya en la base (pares unificados + pagos sin duplicado).
     */
    public function export(Request $request)
    {
        $batchId = $request->query('batch_id');

        $nombreArchivo = $batchId
            ? "pagos_unificados_{$batchId}.xlsx"
            : 'pagos_unificados.xlsx';

        return Excel::download(new PaymentsUnifiedExport($batchId), $nombreArchivo);
    }
}
