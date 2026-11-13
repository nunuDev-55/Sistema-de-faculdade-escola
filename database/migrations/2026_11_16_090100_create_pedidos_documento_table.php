<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedidos de documentos (RF-92): o estudante pede no portal (ou a secretaria regista ao balcão), o pedido gera o
 * emolumento, a secretaria emite quando está pago e marca a entrega. O estado do pagamento lê-se da cobrança.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_documento', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique(); // PD2027/0001
            $table->foreignId('estudante_id')->constrained('estudantes')->restrictOnDelete();
            $table->foreignId('matricula_id')->nullable()->constrained('matriculas')->nullOnDelete();
            $table->string('tipo', 30);
            $table->string('finalidade', 200)->nullable();
            $table->string('estado', 20)->default('pedido'); // pedido, emitido, entregue, recusado
            $table->string('origem', 20)->default('portal'); // portal, secretaria
            $table->foreignId('cobranca_id')->nullable()->constrained('cobrancas')->nullOnDelete();
            $table->foreignId('documento_id')->nullable()->constrained('documentos_emitidos')->nullOnDelete();
            $table->text('motivo_recusa')->nullable();
            $table->foreignId('tratado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('entregue_em')->nullable();
            $table->timestamps();
            $table->index(['estado', 'created_at']);
            $table->index(['estudante_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_documento');
    }
};
