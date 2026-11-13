<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos académicos emitidos (RF-90, RF-91): declarações, histórico e certificado. O conteúdo fica congelado no
 * momento da emissão, com um código de verificação (o do QR) e uma assinatura que denuncia qualquer alteração.
 * Um documento emitido não se apaga nem se altera: anula-se, com o motivo (RNF-06).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_emitidos', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique(); // DC2027/0001
            $table->string('tipo', 30); // declaracao_matricula, declaracao_frequencia, historico, certificado
            $table->foreignId('estudante_id')->constrained('estudantes')->restrictOnDelete();
            $table->foreignId('matricula_id')->nullable()->constrained('matriculas')->nullOnDelete();
            $table->string('finalidade', 200)->nullable(); // "para efeitos de bolsa de estudo"
            $table->longText('conteudo'); // JSON congelado na emissão, tal como foi assinado
            $table->string('codigo_verificacao', 12)->unique();
            $table->string('assinatura', 64);
            $table->string('estado', 20)->default('valido'); // valido, anulado
            $table->text('motivo_anulacao')->nullable();
            $table->foreignId('emitido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('emitido_em');
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('anulado_em')->nullable();
            $table->timestamps();
            $table->index(['estudante_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_emitidos');
    }
};
