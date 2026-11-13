<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finanças do estudante, parte 2: pagamentos (cada um com o seu recibo numerado) e o que cada pagamento abate a
 * cada cobrança. O que sobra de um pagamento é adiantamento e abate as cobranças seguintes (RN-13, RN-17).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique(); // número do recibo
            $table->foreignId('estudante_id')->constrained('estudantes')->restrictOnDelete();
            $table->date('data');
            $table->decimal('valor', 15, 2);
            $table->string('metodo', 20); // numerario, banco, mpesa, emola, pos
            $table->string('referencia', 60)->nullable(); // talão do banco, transacção M-Pesa
            $table->string('estado', 20)->default('valido'); // valido, anulado
            $table->text('motivo_anulacao')->nullable();
            $table->foreignId('registado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('anulado_em')->nullable();
            $table->timestamps();
            $table->index(['estudante_id', 'estado']);
        });

        Schema::create('cobranca_pagamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cobranca_id')->constrained('cobrancas')->cascadeOnDelete();
            $table->foreignId('pagamento_id')->constrained('pagamentos')->cascadeOnDelete();
            $table->decimal('valor', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranca_pagamento');
        Schema::dropIfExists('pagamentos');
    }
};
