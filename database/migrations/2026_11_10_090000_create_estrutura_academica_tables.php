<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estrutura académica (docs/04-base-de-dados.md, secção 2): unidades orgânicas, cursos, disciplinas e planos
 * curriculares com versões. As precedências pertencem a uma versão do plano (o mesmo par de disciplinas pode
 * ter regras diferentes em planos diferentes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_organicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_pai_id')->nullable()->constrained('unidades_organicas')->nullOnDelete();
            $table->string('tipo', 20); // faculdade, escola, departamento
            $table->string('codigo', 20)->unique();
            $table->string('nome');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades_organicas')->nullOnDelete();
            $table->string('codigo', 20)->unique();
            $table->string('nome');
            $table->string('grau', 30); // licenciatura, mestrado, doutoramento, tecnico, ensino_primario, ensino_secundario
            $table->unsignedTinyInteger('duracao_anos');
            $table->foreignId('director_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('disciplinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades_organicas')->nullOnDelete();
            $table->string('codigo', 20)->unique();
            $table->string('nome');
            $table->decimal('creditos', 4, 1)->default(0);
            $table->unsignedSmallInteger('horas_teoricas')->default(0);
            $table->unsignedSmallInteger('horas_praticas')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('planos_curriculares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->string('versao', 20);
            $table->string('estado', 20)->default('rascunho'); // rascunho, aprovado, substituido
            $table->string('metodo_avaliacao', 20)->default('media_simples'); // media_simples, media_ponderada
            $table->date('aprovado_em')->nullable();
            $table->foreignId('aprovado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['curso_id', 'versao']);
        });

        Schema::create('plano_disciplinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plano_id')->constrained('planos_curriculares')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->unsignedTinyInteger('ano'); // ano curricular (ou classe, no ensino geral)
            $table->unsignedTinyInteger('periodo'); // semestre ou trimestre; 0 = anual
            $table->boolean('obrigatoria')->default(true);
            $table->boolean('nuclear')->default(false); // conta para a passagem de classe (ensino geral)
            $table->timestamps();
            $table->unique(['plano_id', 'disciplina_id']);
        });

        Schema::create('precedencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plano_id')->constrained('planos_curriculares')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->cascadeOnDelete();
            $table->foreignId('precedente_id')->constrained('disciplinas')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['plano_id', 'disciplina_id', 'precedente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precedencias');
        Schema::dropIfExists('plano_disciplinas');
        Schema::dropIfExists('planos_curriculares');
        Schema::dropIfExists('disciplinas');
        Schema::dropIfExists('cursos');
        Schema::dropIfExists('unidades_organicas');
    }
};
