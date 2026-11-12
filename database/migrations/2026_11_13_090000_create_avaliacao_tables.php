<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avaliação (docs/04-base-de-dados.md, secção 5): o plano de avaliação de cada disciplina na turma (componentes com
 * pesos que somam 100 %), as notas de cada componente e as pautas de cada época (frequência, exame normal,
 * recorrência), com as linhas por estudante. Uma pauta publicada não se altera (RN-09).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('componentes_avaliacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leccionacao_id')->constrained('leccionacoes')->cascadeOnDelete();
            $table->string('nome', 60); // 1.º teste, Trabalho de grupo
            $table->decimal('peso', 5, 2); // em %, a soma dá 100
            $table->date('data')->nullable();
            $table->unsignedTinyInteger('ordem')->default(1);
            $table->timestamps();
        });

        Schema::create('notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('componente_id')->constrained('componentes_avaliacao')->cascadeOnDelete();
            $table->foreignId('inscricao_id')->constrained('inscricoes')->cascadeOnDelete();
            $table->decimal('valor', 5, 2)->nullable();
            $table->boolean('faltou')->default(false); // conta como zero
            $table->foreignId('lancada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['componente_id', 'inscricao_id']);
        });

        Schema::create('pautas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->nullable()->unique(); // número atribuído ao publicar
            $table->foreignId('leccionacao_id')->constrained('leccionacoes')->cascadeOnDelete();
            $table->string('epoca', 20); // frequencia, normal, recorrencia
            $table->string('estado', 20)->default('rascunho'); // rascunho, submetida, validada, publicada
            $table->text('observacao')->nullable(); // porque foi devolvida
            $table->dateTime('submetida_em')->nullable();
            $table->foreignId('submetida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('validada_em')->nullable();
            $table->foreignId('validada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('publicada_em')->nullable();
            $table->foreignId('publicada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['leccionacao_id', 'epoca']);
        });

        Schema::create('pauta_linhas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pauta_id')->constrained('pautas')->cascadeOnDelete();
            $table->foreignId('inscricao_id')->constrained('inscricoes')->cascadeOnDelete();
            $table->decimal('nota', 5, 2)->nullable(); // média de frequência ou nota do exame
            $table->boolean('faltou')->default(false);
            $table->decimal('nota_final', 5, 2)->nullable();
            $table->string('situacao', 20)->nullable(); // excluido, admitido, dispensado, aprovado, reprovado
            $table->timestamps();
            $table->unique(['pauta_id', 'inscricao_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pauta_linhas');
        Schema::dropIfExists('pautas');
        Schema::dropIfExists('notas');
        Schema::dropIfExists('componentes_avaliacao');
    }
};
