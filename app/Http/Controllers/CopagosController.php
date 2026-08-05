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

        $rows = (new UnifiedPaymentsQuery())->rows(null, $filtros, 50);

        return view('welcome', [
            'rows' => $rows,
            'filtros' => $filtros,
        ]);
    }

    /**
     * Recibe el Excel desde el modal "Nuevo registro".
     */
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