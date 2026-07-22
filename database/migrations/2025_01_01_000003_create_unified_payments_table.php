<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unified_payments', function (Blueprint $table) {
            $table->id();
            $table->string('import_batch_id')->nullable()->index();

            // Datos del turno/prestación (comunes a ambas filas originales)
            $table->string('paciente')->nullable();
            $table->string('dni')->nullable();
            $table->dateTime('fecha_hora')->nullable();
            $table->string('departamento')->nullable();
            $table->string('especialidad')->nullable();
            $table->string('medico')->nullable();
            $table->string('cobertura')->nullable();
            $table->string('numerobeneficiario')->nullable();
            $table->string('prestacion')->nullable();
            $table->string('codigo')->nullable();
            $table->decimal('cantidad', 10, 2)->nullable();

            // Montos consolidados
            $table->decimal('monto_cobertura', 14, 2)->default(0);
            $table->decimal('monto_bono_persona', 14, 2)->default(0);
            $table->decimal('monto_total', 14, 2)->default(0);

            // Trazabilidad de los registros originales (nunca se pierden)
            $table->string('numero_cobertura')->nullable();
            $table->string('numero_bono_persona')->nullable();
            $table->foreignId('payment_cobertura_id')->nullable()
                ->constrained('payments')->nullOnDelete();
            $table->foreignId('payment_bono_persona_id')->nullable()
                ->constrained('payments')->nullOnDelete();

            $table->string('estado_final')->nullable();

            // Confianza del match: "alta" (confirmado también por el
            // patrón de NUMERO con tolerancia) o "media" (solo por clave
            // de negocio, sin patrón de NUMERO disponible para confirmar)
            $table->string('confianza')->default('media');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unified_payments');
    }
};
