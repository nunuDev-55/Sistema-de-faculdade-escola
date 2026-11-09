<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estudantes, encarregados, matrículas e inscrições (docs/04-base-de-dados.md, secções 3 e 5).
 *
 * A inscrição liga-se para já à disciplina do plano; a turma (leccionação) acrescenta-se quando houver oferta
 * lectiva (turmas e docentes), sem mudar o resto. O estudante repete-se na inscrição para as perguntas do
 * dia-a-dia ("já fez Programação I?") não precisarem de passar pelas matrículas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudantes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique(); // 2027/00123: gerado uma vez, nunca reutilizado (RN-08)
            $table->string('nome');
            $table->string('sexo', 1)->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('documento_tipo', 20)->nullable(); // bi, passaporte, dire, cedula, certidao
            $table->string('documento_numero', 30)->nullable();
            $table->string('nuit', 9)->nullable();
            $table->string('nacionalidade', 60)->nullable();
            $table->string('naturalidade', 100)->nullable();
            $table->string('provincia', 40)->nullable();
            $table->string('morada')->nullable();
            $table->string('telemovel', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('necessidades_especiais')->nullable();
            $table->string('categoria', 20)->default('regular'); // regular, bolseiro, funcionario, estrangeiro, transferido
            $table->string('estado', 20)->default('activo'); // activo, suspenso, desistente, transferido, graduado
            $table->text('nota_privada')->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('nome');
            $table->unique(['documento_tipo', 'documento_numero']);
        });

        Schema::create('encarregados', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('documento_numero', 30)->nullable();
            $table->string('profissao', 100)->nullable();
            $table->string('telemovel', 20)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('morada')->nullable();
            $table->timestamps();
        });

        Schema::create('estudante_encarregado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudante_id')->constrained('estudantes')->cascadeOnDelete();
            $table->foreignId('encarregado_id')->constrained('encarregados')->cascadeOnDelete();
            $table->string('parentesco', 20); // pai, mae, irmao, tio, avo, conjuge, outro
            $table->boolean('principal')->default(false);
            $table->boolean('ve_financas')->default(true);
            $table->timestamps();
            $table->unique(['estudante_id', 'encarregado_id']);
        });

        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('estudante_id')->constrained('estudantes')->restrictOnDelete();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->foreignId('plano_id')->constrained('planos_curriculares')->restrictOnDelete();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->restrictOnDelete();
            $table->unsignedTinyInteger('ano_curricular'); // ano do curso, ou classe no ensino geral
            $table->string('regime', 20)->nullable(); // laboral, pos_laboral, manha, tarde, noite
            $table->string('estado', 20)->default('activa'); // activa, suspensa, anulada, transferida, concluida
            $table->string('resultado', 20)->nullable(); // transita, nao_transita, graduado
            $table->date('data');
            $table->text('motivo')->nullable(); // da suspensão ou da anulação
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['ano_lectivo_id', 'curso_id', 'estado']);
        });

        Schema::create('inscricoes_periodo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique(); // número do comprovativo
            $table->foreignId('matricula_id')->constrained('matriculas')->cascadeOnDelete();
            $table->foreignId('periodo_id')->nullable()->constrained('periodos_lectivos')->restrictOnDelete(); // null = o ano todo (ensino geral)
            $table->string('estado', 20)->default('confirmada'); // confirmada, anulada
            $table->string('estado_facturacao', 20)->default('por_gerar'); // por_gerar, geradas, falhou
            $table->dateTime('confirmada_em')->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['matricula_id', 'periodo_id']);
        });

        Schema::create('inscricoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscricao_periodo_id')->constrained('inscricoes_periodo')->cascadeOnDelete();
            $table->foreignId('estudante_id')->constrained('estudantes')->restrictOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->decimal('creditos', 4, 1)->default(0); // os do plano no dia da inscrição
            $table->boolean('em_atraso')->default(false); // disciplina de um ano anterior
            $table->string('estado', 20)->default('inscrito'); // inscrito, anulado, aprovado, reprovado, excluido
            $table->decimal('nota_final', 5, 2)->nullable();
            $table->dateTime('anulada_em')->nullable();
            $table->timestamps();
            $table->unique(['inscricao_periodo_id', 'disciplina_id']);
            $table->index(['estudante_id', 'disciplina_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscricoes');
        Schema::dropIfExists('inscricoes_periodo');
        Schema::dropIfExists('matriculas');
        Schema::dropIfExists('estudante_encarregado');
        Schema::dropIfExists('encarregados');
        Schema::dropIfExists('estudantes');
    }
};
