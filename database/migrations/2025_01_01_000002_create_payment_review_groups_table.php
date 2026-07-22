<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_review_groups', function (Blueprint $table) {
            $table->id();
            $table->string('import_batch_id')->nullable()->index();
            $table->string('business_key', 500)->nullable();
            // Motivo por el que no se pudo unificar automáticamente
            $table->string('motivo');
            $table->integer('cantidad_filas')->default(0);
            $table->boolean('resuelto')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_review_groups');
    }
};
