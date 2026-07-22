<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentReviewGroup;
use App\Models\UnifiedPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentDeduplicationService
{
    public function __construct(
        protected PaymentNormalizer $normalizer,
        protected string $tipoPaciente,
        protected int $toleranceSeconds,
        protected array $estadoPriority,
    ) {
    }

    /**
     * Analiza una colección de filas crudas (arrays asociativos, ya sea
     * desde Excel o desde el endpoint) SIN escribir nada en la base.
     * Pensado para poder correr en modo "dry run" antes de persistir.
     *
     * @param  Collection<int, array>  $rawRows
     * @return array{pairs: array, standalone: array, review: array}
     */
    public function analyze(Collection $rawRows): array
    {
        $normalized = $rawRows
            ->map(fn (array $raw) => $this->normalizer->normalizeRow($raw))
            // Descarta filas completamente vacías (por ejemplo, líneas en
            // blanco que a veces trae el Excel entre el header y los datos)
            ->filter(fn (array $row) => filled($row['paciente']))
            ->values();

        $groups = $normalized->groupBy('business_key');

        $pairs = [];
        $standalone = [];
        $review = [];

        foreach ($groups as $businessKey => $group) {
            if ($group->count() === 1) {
                $standalone[] = $group->first();
                continue;
            }

            $bono = $group->filter(fn ($r) => $r['tipo'] === $this->tipoPaciente)->values();
            $cobertura = $group->filter(fn ($r) => $r['tipo'] !== $this->tipoPaciente)->values();

            if ($group->count() === 2 && $bono->count() === 1 && $cobertura->count() === 1) {
                $pairs[] = $this->buildPair($cobertura->first(), $bono->first(), (string) $businessKey);
                continue;
            }

            $review[] = [
                'business_key' => (string) $businessKey,
                'filas' => $group->all(),
                'motivo' => $this->motivoRevision($group, $bono, $cobertura),
            ];
        }

        return compact('pairs', 'standalone', 'review');
    }

    /**
     * Arma el registro unificado (todavía en memoria) a partir del par
     * cobertura + bono a persona, y calcula la confianza del match.
     */
    protected function buildPair(array $cobertura, array $bono, string $businessKey): array
    {
        $confianza = $this->confirmarPorNumero($cobertura['numero'], $bono['numero'])
            ? 'alta'
            : 'media';

        return [
            'business_key' => $businessKey,
            'cobertura' => $cobertura,
            'bono' => $bono,
            'confianza' => $confianza,
            'monto_cobertura' => $cobertura['total'],
            'monto_bono_persona' => $bono['total'],
            'monto_total' => $cobertura['total'] + $bono['total'],
            'estado_final' => $this->resolverEstado($cobertura['estado'], $bono['estado']),
        ];
    }

    /**
     * Confirma si dos NUMERO corresponden al mismo prefijo, con la
     * marca de tiempo dentro de la tolerancia configurada. Sirve como
     * señal de confianza adicional a la clave de negocio, no como
     * criterio de agrupamiento principal.
     */
    protected function confirmarPorNumero(?string $numeroCobertura, ?string $numeroBono): bool
    {
        $a = $this->normalizer->parseNumero($numeroCobertura);
        $b = $this->normalizer->parseNumero($numeroBono);

        if (! $a['base'] || ! $b['base'] || $a['base'] !== $b['base']) {
            return false;
        }

        // Nota: se comparan los timestamps como enteros (no como segundos
        // reales), igual que en la validación inicial. Funciona bien salvo
        // en el borde de un cambio de minuto/hora exacto, caso poco
        // frecuente dado que ambas filas se graban casi en simultáneo.
        return abs((int) $a['timestamp'] - (int) $b['timestamp']) <= $this->toleranceSeconds;
    }

    /**
     * Resuelve qué ESTADO final le corresponde al registro unificado,
     * usando la prioridad configurada. Si alguno de los dos estados no
     * está en la lista de prioridades, se lo trata como el de menor
     * prioridad posible (para no descartarlo silenciosamente).
     */
    protected function resolverEstado(?string $estadoCobertura, ?string $estadoBono): ?string
    {
        $prioridad = fn (?string $estado) => $estado && in_array($estado, $this->estadoPriority, true)
            ? array_search($estado, $this->estadoPriority, true)
            : count($this->estadoPriority);

        return $prioridad($estadoCobertura) <= $prioridad($estadoBono)
            ? $estadoCobertura
            : $estadoBono;
    }

    protected function motivoRevision(Collection $group, Collection $bono, Collection $cobertura): string
    {
        if ($group->count() > 2) {
            return "Grupo con {$group->count()} registros (se esperaban 2 como máximo)";
        }

        if ($bono->count() > 1) {
            return 'Más de un registro "Bono a Persona" para el mismo turno';
        }

        if ($bono->count() === 0) {
            return 'Dos registros de cobertura sin ningún "Bono a Persona" asociado';
        }

        return 'Combinación de TIPO inesperada dentro del grupo';
    }

    /**
     * Persiste el resultado de analyze() en la base de datos, dentro de
     * una transacción. Pensado para correrse solo después de validar el
     * resultado de analyze() en modo dry-run.
     *
     * @return array{batch_id: string, unificados: int, standalone: int, revision: int}
     */
    public function persist(array $analysis, string $source, ?string $importBatchId = null): array
    {
        $importBatchId ??= (string) Str::uuid();

        DB::transaction(function () use ($analysis, $source, $importBatchId) {
            foreach ($analysis['standalone'] as $row) {
                $this->createPayment($row, $source, $importBatchId, [
                    'dedup_status' => 'standalone',
                ]);
            }

            foreach ($analysis['pairs'] as $pair) {
                $paymentCobertura = $this->createPayment($pair['cobertura'], $source, $importBatchId);
                $paymentBono = $this->createPayment($pair['bono'], $source, $importBatchId);

                $unified = UnifiedPayment::create([
                    'import_batch_id' => $importBatchId,
                    'paciente' => $pair['cobertura']['paciente'],
                    'dni' => $pair['cobertura']['dni'],
                    'fecha_hora' => $pair['cobertura']['fecha_hora'],
                    'departamento' => $pair['cobertura']['departamento'],
                    'especialidad' => $pair['cobertura']['especialidad'],
                    'medico' => $pair['cobertura']['medico'],
                    'cobertura' => $pair['cobertura']['cobertura'],
                    'numerobeneficiario' => $pair['cobertura']['numerobeneficiario'],
                    'prestacion' => $pair['cobertura']['prestacion'],
                    'codigo' => $pair['cobertura']['codigo'],
                    'cantidad' => $pair['cobertura']['cantidad'],
                    'monto_cobertura' => $pair['monto_cobertura'],
                    'monto_bono_persona' => $pair['monto_bono_persona'],
                    'monto_total' => $pair['monto_total'],
                    'numero_cobertura' => $pair['cobertura']['numero'],
                    'numero_bono_persona' => $pair['bono']['numero'],
                    'payment_cobertura_id' => $paymentCobertura->id,
                    'payment_bono_persona_id' => $paymentBono->id,
                    'estado_final' => $pair['estado_final'],
                    'confianza' => $pair['confianza'],
                ]);

                $paymentCobertura->update(['dedup_status' => 'unificado', 'unified_payment_id' => $unified->id]);
                $paymentBono->update(['dedup_status' => 'unificado', 'unified_payment_id' => $unified->id]);
            }

            foreach ($analysis['review'] as $grupo) {
                $reviewGroup = PaymentReviewGroup::create([
                    'import_batch_id' => $importBatchId,
                    'business_key' => $grupo['business_key'],
                    'motivo' => $grupo['motivo'],
                    'cantidad_filas' => count($grupo['filas']),
                ]);

                foreach ($grupo['filas'] as $row) {
                    $this->createPayment($row, $source, $importBatchId, [
                        'dedup_status' => 'revision',
                        'payment_review_group_id' => $reviewGroup->id,
                    ]);
                }
            }
        });

        return [
            'batch_id' => $importBatchId,
            'unificados' => count($analysis['pairs']),
            'standalone' => count($analysis['standalone']),
            'revision' => count($analysis['review']),
        ];
    }

    protected function createPayment(array $row, string $source, string $importBatchId, array $extra = []): Payment
    {
        return Payment::create(array_merge($row, $extra, [
            'source' => $source,
            'import_batch_id' => $importBatchId,
        ]));
    }
}
