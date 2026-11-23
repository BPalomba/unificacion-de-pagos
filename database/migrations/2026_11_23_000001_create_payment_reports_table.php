<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un "informe" es un Excel subido por un usuario. Es temporal: vence
        // en expires_at y después se borra junto con sus pagos (ver
        // PaymentReportService::purgarVencidos). Su id es el import_batch_id
        // de payments, unified_payments y payment_review_groups.
        Schema::create('payment_reports', function (Blueprint $table) {
            // string y no uuid: en PostgreSQL uuid es un tipo propio y no se
            // puede comparar contra import_batch_id (varchar) sin castear.
            $table->string('id', 36)->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nombre_archivo');

            // Resumen del procesamiento, para listar sin contar filas
            $table->unsignedInteger('filas_leidas')->default(0);
            $table->unsignedInteger('unificados')->default(0);
            $table->unsignedInteger('standalone')->default(0);
            $table->unsignedInteger('revision')->default(0);

            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reports');
    }
};
