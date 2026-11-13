<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finanças do estudante, parte 1 (docs/04-base-de-dados.md, secção 6): a tabela de preços do ano lectivo e as
 * cobranças (o papel da factura do nsERP). Dinheiro sempre em decimal(15,2), contas com bcmath (RN-11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ano_lectivo_id')->constrained('anos_lectivos')->cascadeOnDelete();
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->cascadeOnDelete(); // vazio = todos os cursos
            $table->string('tipo', 20); // inscricao (por período), propina (mensal)
            $table->decimal('valor', 15, 2);
            $table->unsignedTinyInteger('prestacoes')->default(1); // propinas: quantos meses por período
            $table->unsignedTinyInteger('dia_vencimento')->default(10);
            $table->timestamps();
            $table->unique(['ano_lectivo_id', 'curso_id', 'tipo']);
        });

        Schema::create('cobrancas', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('estudante_id')->constrained('estudantes')->restrictOnDelete();
            $table->foreignId('matricula_id')->nullable()->constrained('matriculas')->nullOnDelete();
            $table->foreignId('inscricao_periodo_id')->nullable()->constrained('inscricoes_periodo')->nullOnDelete();
            $table->string('tipo', 20); // inscricao, propina
            $table->string('descricao');
            $table->decimal('valor', 15, 2);
            $table->decimal('pago', 15, 2)->default(0);
            $table->date('vencimento');
            $table->string('estado', 20)->default('aberta'); // aberta, parcial, paga, anulada
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['estudante_id', 'estado']);
            $table->index(['vencimento', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas');
        Schema::dropIfExists('precos');
    }
};
