<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contas do portal do estudante (docs/04-base-de-dados.md, secção 3): guarda própria ("estudante"), que nunca abre o
 * sistema interno. O estudante entra com o número de estudante e a senha que a secretaria lhe entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_portal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudante_id')->unique()->constrained('estudantes')->cascadeOnDelete();
            $table->string('password');
            $table->string('estado', 20)->default('activa'); // activa, suspensa
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas_portal');
    }
};
