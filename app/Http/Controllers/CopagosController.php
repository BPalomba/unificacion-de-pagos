<?php

namespace App\Http\Controllers;

use App\Exports\PaymentsUnifiedExport;
use App\Models\PaymentReport;
use App\Models\PaymentReviewGroup;
use App\Services\Payments\DataSources\ExcelPaymentSource;
use App\Services\Payments\PaymentReportService;
use App\Services\Payments\UnifiedPaymentsQuery;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Informes temporales: cada Excel subido se procesa y queda disponible
 * solo para quien lo subió, durante payments.report_ttl_minutes minutos.
 */
class CopagosController extends Controller
{
    public function __construct(protected PaymentReportService $reports)
    {
    }

    /**
     * Pantalla principal: los informes vigentes del usuario.
     */
    public function index(Request $request): View
    {
        $this->reports->purgarVencidos();

        $informes = $request->user()->paymentReports()->vigentes()->latest()->get();

        return view('welcome', ['informes' => $informes]);
    }

    /**
     * Recibe el Excel desde el modal "Nuevo informe", lo procesa y lleva
     * directo a la página del informe.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls'],
        ], [], [
            'archivo' => 'archivo Excel',
        ]);

        $this->reports->purgarVencidos();

        $archivo = $request->file('archivo');

        // Se guarda temporalmente en storage/app/private/payments-uploads
        $path = $archivo->store('payments-uploads');

        try {
            try {
                $rawRows = (new ExcelPaymentSource(Storage::path($path)))->fetch();
            } catch (\Throwable $e) {
                Log::warning('No se pudo leer el Excel subido', ['error' => $e->getMessage()]);

                return redirect()->route('copagos.index')->withErrors([
                    'archivo' => 'No se pudo leer el Excel. Verificá que sea el reporte del sistema del hospital.',
                ]);
            }

            $informe = $this->reports->crear($request->user(), $rawRows, $archivo->getClientOriginalName());

            return redirect()->route('informes.show', $informe);
        } finally {
            // El Excel original no se conserva: solo el resultado procesado
            Storage::delete($path);
        }
    }

    /**
     * Página del informe: resumen, grupos a revisar y la grilla paginada.
     */
    public function show(Request $request, string $informe): View
    {
        $informe = $this->informeDelUsuario($request, $informe);

        $filtros = $request->only(['medico', 'paciente', 'cobertura', 'periodo']);

        $rows = (new UnifiedPaymentsQuery())->paginate($informe->id, $filtros, 50);

        $revision = PaymentReviewGroup::where('import_batch_id', $informe->id)
            ->with('payments')
            ->orderBy('id')
            ->get();

        return view('informes.show', [
            'informe' => $informe,
            'rows' => $rows,
            'filtros' => $filtros,
            'revision' => $revision,
        ]);
    }

    /**
     * Descarga el Excel procesado del informe.
     */
    public function export(Request $request, string $informe): BinaryFileResponse
    {
        $informe = $this->informeDelUsuario($request, $informe);

        return Excel::download(new PaymentsUnifiedExport($informe->id), $informe->nombre_descarga);
    }

    /**
     * Elimina el informe en el momento, sin esperar a que venza.
     */
    public function destroy(Request $request, string $informe): RedirectResponse
    {
        $informe = $this->informeDelUsuario($request, $informe);

        $this->reports->eliminar($informe);

        return redirect()->route('copagos.index')
            ->with('status', "Se eliminó el informe \"{$informe->nombre_archivo}\".");
    }

    /**
     * Limpieza disparada por Vercel Cron (Authorization: Bearer CRON_SECRET).
     * Sin secreto configurado, o con uno incorrecto, la ruta "no existe".
     */
    public function purgar(Request $request): JsonResponse
    {
        $secreto = (string) config('payments.cron_secret');

        abort_if($secreto === '' || ! hash_equals($secreto, (string) $request->bearerToken()), 404);

        return response()->json($this->reports->purgarVencidos());
    }

    /**
     * Busca un informe vigente del usuario logueado. Si no existe, es de
     * otro usuario o ya venció, se responde igual en los tres casos para
     * no revelar si existe.
     */
    protected function informeDelUsuario(Request $request, string $id): PaymentReport
    {
        $informe = $request->user()->paymentReports()->vigentes()->find($id);

        if (! $informe) {
            throw new HttpResponseException(
                redirect()->route('copagos.index')->with('warning', 'El informe no existe o ya venció.')
            );
        }

        return $informe;
    }
}
