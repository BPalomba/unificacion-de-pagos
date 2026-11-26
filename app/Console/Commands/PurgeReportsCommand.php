<?php

namespace App\Console\Commands;

use App\Services\Payments\PaymentReportService;
use Illuminate\Console\Command;

class PurgeReportsCommand extends Command
{
    protected $signature = 'informes:purgar';

    protected $description = 'Borra los informes vencidos y todo pago que no pertenezca a un informe vigente';

    public function handle(PaymentReportService $reports): int
    {
        $resultado = $reports->purgarVencidos();

        $this->info("Informes vencidos borrados: {$resultado['informes']}. Pagos borrados: {$resultado['pagos']}.");

        return self::SUCCESS;
    }
}
