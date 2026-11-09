<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Anos lectivos e os seus períodos (semestres ou trimestres), gerados a partir do ano (docs/08, padrão do OpenEduCat). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anos_lectivos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 20)->unique(); // 2027 ou 2027/2028
            $table->date('inicio');
            $table->date('fim');
            $table->string('estado', 20)->default('planeado'); // planeado, aberto, fechado
            $table->timestamps();
        });

        Schema::create('periodos_lectivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->cascadeOnDelete();
            $table->string('tipo', 20); // semestre, trimestre
            $table->unsignedTinyInteger('numero');
            $table->date('inicio');
            $table->date('fim');
            $table->date('inscricoes_inicio')->nullable();
            $table->date('inscricoes_fim')->nullable();
            $table->date('exames_inicio')->nullable();
            $table->date('exames_fim')->nullable();
            $table->timestamps();
            $table->unique(['ano_lectivo_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos_lectivos');
        Schema::dropIfExists('anos_lectivos');
    }
};
