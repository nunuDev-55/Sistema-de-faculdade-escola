<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oferta lectiva (docs/04-base-de-dados.md, secção 4): salas, docentes, turmas e leccionações.
 *
 * Uma leccionação é uma disciplina dada a uma turma por um docente: é aí que se penduram o horário, as aulas,
 * o plano de avaliação e a pauta. A inscrição do estudante passa a apontar para a leccionação quando ele é
 * distribuído por uma turma. A turma é do semestre (superior) ou do ano todo (ensino geral, período vazio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nome', 100)->nullable();
            $table->string('tipo', 20)->default('aula'); // aula, laboratorio, anfiteatro, informatica
            $table->unsignedSmallInteger('capacidade')->default(40);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('docentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('nome');
            $table->string('numero', 20)->nullable()->unique(); // número de funcionário
            $table->string('grau_academico', 20)->nullable(); // licenciado, mestre, doutor
            $table->string('categoria', 40)->nullable(); // assistente, auxiliar, associado, catedrático
            $table->string('regime', 20)->default('integral'); // integral, parcial
            $table->foreignId('unidade_id')->nullable()->constrained('unidades_organicas')->nullOnDelete();
            $table->string('telemovel', 20)->nullable();
            $table->string('email')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index('nome');
        });

        Schema::create('turmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->restrictOnDelete();
            $table->foreignId('periodo_id')->nullable()->constrained('periodos_lectivos')->restrictOnDelete(); // null = o ano todo
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->unsignedTinyInteger('ano_curricular'); // ou classe
            $table->string('nome', 20); // A, B, 10.ª A
            $table->string('regime', 20)->nullable();
            $table->unsignedSmallInteger('capacidade')->default(40);
            $table->foreignId('director_id')->nullable()->constrained('docentes')->nullOnDelete(); // director de turma
            $table->timestamps();
            $table->unique(['ano_lectivo_id', 'periodo_id', 'curso_id', 'ano_curricular', 'nome'], 'turmas_unica');
        });

        Schema::create('leccionacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->foreignId('docente_id')->nullable()->constrained('docentes')->nullOnDelete(); // por atribuir
            $table->timestamps();
            $table->unique(['turma_id', 'disciplina_id']);
        });

        Schema::table('inscricoes', function (Blueprint $table) {
            $table->foreignId('leccionacao_id')->nullable()->after('disciplina_id')->constrained('leccionacoes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inscricoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leccionacao_id');
        });
        Schema::dropIfExists('leccionacoes');
        Schema::dropIfExists('turmas');
        Schema::dropIfExists('docentes');
        Schema::dropIfExists('salas');
    }
};
