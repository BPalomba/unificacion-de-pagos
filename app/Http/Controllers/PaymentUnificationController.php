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
        // Se valida que el archivo exista y sea de extensión xlsx o xls
        // Se valida que el modo sea alguno de los dos: dry_run o persistir
        $validated = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls'],
            'modo' => ['required', 'in:dry_run,persistir'],
        ], [], [
            'archivo' => 'archivo Excel',
        ]);

        // Se guarda el archivo físicamente en su ruta por defecto, desde el directorio raíz: storage/app/private/payments-uploads
        // Esto es ruta relativa
        $path = $request->file('archivo')->store('payments-uploads');


        try {
            // Se obtiene la ruta absoluta
            $fullPath = Storage::path($path);

            // Se lee el archivo
            $rawRows = (new ExcelPaymentSource($fullPath))->fetch();

            // Se configura al normalizer
            $normalizer = new PaymentNormalizer(config('payments.business_key_fields'));

            // Se configura el servicio de desduplicado
            $service = new PaymentDeduplicationService(
                normalizer: $normalizer,
                tipoAtencion: config('payments.tipo_atencion'),
                toleranceSeconds: config('payments.numero_time_tolerance_seconds'),
                estadoPriority: config('payments.estado_priority'),
            );

            // El servicio de desduplicado hace su magia
            $analysis = $service->analyze($rawRows);

            // Los datos resumidos
            $resumen = [
                'filas_leidas' => $rawRows->count(),
                'unificados' => count($analysis['pairs']),
                'standalone' => count($analysis['standalone']),
                'revision' => count($analysis['review']),
                'modo' => $validated['modo'],
            ];

            // Si el modo era persistir, que el service lo persista y también devuelva el batchId
            if ($validated['modo'] === 'persistir') {
                $resultado = $service->persist($analysis, 'excel');
                $resumen['batch_id'] = $resultado['batch_id'];
            }

            // Se vuelve a la pantalla con el resumen, los primeros 20 pares analizados y los pares que necesitan review
            return redirect()
                ->route('payments.upload')
                ->with('resumen', $resumen)
                ->with('pares', array_slice($analysis['pairs'], 0, 20))
                ->with('revision', $analysis['review']);
        } finally {
            // Se elimina el archivo temporal una vez que ya no hace falta
            Storage::delete($path);
        }
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
