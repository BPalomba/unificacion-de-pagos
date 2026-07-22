<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Origen del registro (para trazabilidad de dónde vino)
            $table->string('source')->default('excel'); // excel | endpoint
            $table->string('import_batch_id')->nullable()->index();

            // Campos tal cual vienen del sistema del hospital
            $table->string('paciente')->nullable();
            $table->string('dni')->nullable();
            $table->string('fecha')->nullable();
            $table->string('hora')->nullable();
            $table->dateTime('fecha_hora')->nullable()->index();
            $table->string('departamento')->nullable();
            $table->string('especialidad')->nullable();
            $table->string('medico')->nullable();
            $table->string('estado_turno')->nullable();
            $table->string('cobertura')->nullable();
            $table->string('numerobeneficiario')->nullable();
            $table->string('numero')->nullable()->index();
            $table->string('numero_autorizacion')->nullable();
            $table->string('prestacion')->nullable();
            $table->string('detalle')->nullable();
            $table->string('codigo')->nullable();
            $table->decimal('cantidad', 10, 2)->nullable();
            $table->decimal('precio', 14, 2)->nullable();
            $table->decimal('copago', 14, 2)->nullable();
            $table->decimal('total', 14, 2)->nullable();
            $table->string('estado')->nullable();
            $table->string('tipo')->nullable();
            $table->string('modificado_por')->nullable();
            $table->string('fecha_mod')->nullable();
            $table->string('fac_num')->nullable();
            $table->string('fac_num_manual')->nullable();
            $table->string('rec_nro')->nullable();
            $table->string('centro_costo')->nullable();

            // Clave de negocio calculada (para agrupar candidatos a par)
            $table->string('business_key', 500)->nullable()->index();

            // Estado del procesamiento de deduplicación:
            // pendiente | unificado | standalone | revision
            $table->string('dedup_status')->default('pendiente')->index();

            // Referencias a unified_payments / payment_review_groups.
            // Sin ->constrained() acá para evitar dependencia circular
            // (esas tablas todavía no existen en este punto). La FK real
            // se agrega en la migración 2025_01_01_000004.
            $table->unsignedBigInteger('unified_payment_id')->nullable()->index();
            $table->unsignedBigInteger('payment_review_group_id')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
