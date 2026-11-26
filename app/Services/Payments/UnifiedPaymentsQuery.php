<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\UnifiedPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Generator;

/**
 * Arma la lista de "pagos ya procesados" (unificados + standalone) en un
 * formato uniforme, una fila por pago real. La usan tanto la tabla web
 * (CopagosController) como el export a Excel (PaymentsUnifiedExport),
 * para no duplicar la lógica de armado de filas en dos lugares.
 */
class UnifiedPaymentsQuery
{
    /**
     * Siempre se consulta un único informe (batchId): los datos de un
     * usuario nunca se mezclan con los de otro.
     *
     * @param  array{medico?: string, paciente?: string, cobertura?: string, periodo?: string}  $filtros
     */
    public function rows(string $batchId, array $filtros = [], ?int $limit = null): Collection
    {
        return collect($this->rowsGenerator($batchId, $filtros, $limit))->values();
    }

    public function generator(string $batchId, array $filtros = []): Generator
    {
        yield from $this->rowsGenerator($batchId, $filtros, null);
    }

    /**
     * Una página de filas del informe, en el mismo orden que el export.
     */
    public function paginate(string $batchId, array $filtros = [], int $perPage = 50, ?int $page = null): LengthAwarePaginator
    {
        $page ??= Paginator::resolveCurrentPage();

        $total = $this->unifiedQuery($batchId, $filtros)->count()
            + $this->standaloneQuery($batchId, $filtros)->count()
            + $this->revisionQuery($batchId, $filtros)->count();

        $items = collect($this->rowsGenerator($batchId, $filtros, $perPage, ($page - 1) * $perPage))->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]);
    }

    protected function rowsGenerator(string $batchId, array $filtros = [], ?int $limit = null, int $offset = 0): Generator
    {
        $unificados = $this->unifiedQuery($batchId, $filtros)->cursor();
        $standalone = $this->standaloneQuery($batchId, $filtros)->cursor();
        $revision = $this->revisionQuery($batchId, $filtros)->cursor();

        $uIterator = $unificados->getIterator();
        $sIterator = $standalone->getIterator();
        $rIterator = $revision->getIterator();

        $uIterator->rewind();
        $sIterator->rewind();
        $rIterator->rewind();

        $count = 0;

        while (($uIterator->valid() || $sIterator->valid() || $rIterator->valid()) && ($limit === null || $count < $limit)) {
            $u = $uIterator->valid() ? $uIterator->current() : null;
            $s = $sIterator->valid() ? $sIterator->current() : null;
            $r = $rIterator->valid() ? $rIterator->current() : null;

            // Determina cuál tiene la fecha más antigua
            $minItem = null;
            $minDate = null;

            foreach (['u' => $u, 's' => $s, 'r' => $r] as $key => $item) {
                if ($item !== null) {
                    $itemDate = $item->fecha_hora;
                    if ($minDate === null || $itemDate >= $minDate) {
                        $minDate = $itemDate;
                        $minItem = $key;
                    }
                }
            }

            // Las filas anteriores a la página pedida se saltean sin armarlas
            $saltear = $offset > 0;

            if ($minItem === 'u') {
                if (! $saltear) {
                    yield $this->desdeUnificado($u);
                }
                $uIterator->next();
            } elseif ($minItem === 's') {
                if (! $saltear) {
                    yield $this->desdeStandalone($s);
                }
                $sIterator->next();
            } elseif ($minItem === 'r') {
                if (! $saltear) {
                    yield $this->desdeRevision($r);
                }
                $rIterator->next();
            }

            if ($saltear) {
                $offset--;

                continue;
            }

            $count++;
        }
    }

    protected function unifiedQuery(string $batchId, array $filtros = []): Builder
    {
        return $this->aplicarFiltros(
            UnifiedPayment::query()->with(['paymentCobertura', 'paymentBonoPersona']),
            $batchId,
            $filtros,
        );
    }

    protected function standaloneQuery(string $batchId, array $filtros = []): Builder
    {
        return $this->aplicarFiltros(
            Payment::query()->where('dedup_status', 'standalone'),
            $batchId,
            $filtros,
        );
    }

    protected function revisionQuery(string $batchId, array $filtros = []): Builder
    {
        return $this->aplicarFiltros(
            Payment::query()->where('dedup_status', 'revision'),
            $batchId,
            $filtros,
        );
    }

    /**
     * Filtros comunes a las tres consultas. Se evita SQL propio de un motor
     * (antes se usaba strftime(), que solo existe en SQLite) para que funcione
     * igual en SQLite, PostgreSQL y MySQL.
     */
    protected function aplicarFiltros(Builder $query, string $batchId, array $filtros): Builder
    {
        return $query
            ->where('import_batch_id', $batchId)
            // whereLike sin distinguir mayúsculas (en PostgreSQL usa ILIKE)
            ->when($filtros['medico'] ?? null, fn ($q, $v) => $q->whereLike('medico', "%{$v}%"))
            ->when($filtros['paciente'] ?? null, fn ($q, $v) => $q->whereLike('paciente', "%{$v}%"))
            ->when($filtros['cobertura'] ?? null, fn ($q, $v) => $q->whereLike('cobertura', "%{$v}%"))
            // periodo viene de un <input type="month"> con formato "YYYY-MM"
            ->when($this->rangoDelPeriodo($filtros['periodo'] ?? null), fn ($q, $rango) => $q
                ->where('fecha_hora', '>=', $rango[0])
                ->where('fecha_hora', '<', $rango[1]))
            ->orderByDesc('fecha_hora');
    }

    /**
     * @return array{0: string, 1: string}|null  [inicio del mes, inicio del mes siguiente]
     */
    protected function rangoDelPeriodo(?string $periodo): ?array
    {
        if (! $periodo || ! preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            return null;
        }

        $inicio = \Carbon\Carbon::createFromFormat('!Y-m', $periodo);

        return [
            $inicio->format('Y-m-d H:i:s'),
            $inicio->copy()->addMonthNoOverflow()->format('Y-m-d H:i:s'),
        ];
    }

    protected function desdeUnificado(UnifiedPayment $u): array
    {
        $cobertura = $u->paymentCobertura;
        $bono = $u->paymentBonoPersona;

        return [
            'paciente' => $u->paciente,
            'dni' => $u->dni,
            'fecha' => $cobertura?->fecha,
            'hora' => $cobertura?->hora,
            'departamento' => $u->departamento,
            'especialidad' => $u->especialidad,
            'medico' => $u->medico,
            'estado_turno' => $cobertura?->estado_turno,
            'cobertura' => $u->cobertura,
            'numerobeneficiario' => $u->numerobeneficiario,
            'numero' => $u->numero_cobertura,
            'numero_autorizacion' => $cobertura?->numero_autorizacion,
            'prestacion' => $u->prestacion,
            'detalle' => $cobertura?->detalle,
            'codigo' => $u->codigo,
            'cantidad' => $u->cantidad,
            'precio' => $cobertura?->precio,
            'copago' => (float) ($cobertura?->copago ?? 0) + (float) ($bono?->copago ?? 0),
            'monto_os' => $u->monto_cobertura,
            'total' => $u->monto_total,
            'estado' => $u->estado_final,
            'tipo' => $cobertura?->tipo,
            'tipo_bono' => $bono ? 'Bono a Persona' : null,
            'modificado_por' => $cobertura?->modificado_por,
            'fecha_mod' => $cobertura?->fecha_mod,
            'fac_num' => $cobertura?->fac_num ?: $bono?->fac_num,
            'fac_num_manual' => $cobertura?->fac_num_manual ?: $bono?->fac_num_manual,
            'rec_nro' => $cobertura?->rec_nro ?: $bono?->rec_nro,
            'centro_costo' => $cobertura?->centro_costo ?: $bono?->centro_costo,
            'numero_bono_persona' => $u->numero_bono_persona,
            'monto_bono_persona' => $u->monto_bono_persona,
            'estado_unificacion' => 'Unificado',
            'orden' => $u->fecha_hora,
        ];
    }

    protected function desdeStandalone(Payment $p): array
    {
        return [
            'paciente' => $p->paciente,
            'dni' => $p->dni,
            'fecha' => $p->fecha,
            'hora' => $p->hora,
            'departamento' => $p->departamento,
            'especialidad' => $p->especialidad,
            'medico' => $p->medico,
            'estado_turno' => $p->estado_turno,
            'cobertura' => $p->cobertura,
            'numerobeneficiario' => $p->numerobeneficiario,
            'numero' => $p->numero,
            'numero_autorizacion' => $p->numero_autorizacion,
            'prestacion' => $p->prestacion,
            'detalle' => $p->detalle,
            'codigo' => $p->codigo,
            'cantidad' => $p->cantidad,
            'precio' => $p->precio,
            'copago' => $p->copago,
            'monto_os' => $p->tipo === 'Bono a Persona' ? 0 : $p->total,
            'total' => $p->total,
            'estado' => $p->estado,
            'tipo' => $p->tipo,
            'tipo_bono' => $p->tipo === 'Bono a Persona' ? 'Bono a Persona' : null,
            'modificado_por' => $p->modificado_por,
            'fecha_mod' => $p->fecha_mod,
            'fac_num' => $p->fac_num,
            'fac_num_manual' => $p->fac_num_manual,
            'rec_nro' => $p->rec_nro,
            'centro_costo' => $p->centro_costo,
            'numero_bono_persona' => null,
            'monto_bono_persona' => null,
            'estado_unificacion' => 'Individual (sin duplicado)',
            'orden' => $p->fecha_hora,
        ];
    }

    protected function desdeRevision(Payment $p): array
    {
        return [
            'paciente' => $p->paciente,
            'dni' => $p->dni,
            'fecha' => $p->fecha,
            'hora' => $p->hora,
            'departamento' => $p->departamento,
            'especialidad' => $p->especialidad,
            'medico' => $p->medico,
            'estado_turno' => $p->estado_turno,
            'cobertura' => $p->cobertura,
            'numerobeneficiario' => $p->numerobeneficiario,
            'numero' => $p->numero,
            'numero_autorizacion' => $p->numero_autorizacion,
            'prestacion' => $p->prestacion,
            'detalle' => $p->detalle,
            'codigo' => $p->codigo,
            'cantidad' => $p->cantidad,
            'precio' => $p->precio,
            'copago' => $p->copago,
            'monto_os' => $p->tipo === 'Bono a Persona' ? 0 : $p->total,
            'total' => $p->total,
            'estado' => $p->estado,
            'tipo' => $p->tipo,
            'tipo_bono' => $p->tipo === 'Bono a Persona' ? 'Bono a Persona' : null,
            'modificado_por' => $p->modificado_por,
            'fecha_mod' => $p->fecha_mod,
            'fac_num' => $p->fac_num,
            'fac_num_manual' => $p->fac_num_manual,
            'rec_nro' => $p->rec_nro,
            'centro_costo' => $p->centro_costo,
            'numero_bono_persona' => null,
            'monto_bono_persona' => null,
            'estado_unificacion' => 'A revisar',
            'orden' => $p->fecha_hora,
        ];
    }
}