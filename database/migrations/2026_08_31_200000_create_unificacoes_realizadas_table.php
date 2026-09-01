<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('unificacoes_realizadas')) return;

        Schema::create('unificacoes_realizadas', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('participante_novo_id')->nullable()->index();
            $table->string('participante_novo_nome', 150)->nullable();
            $table->json('participantes_excluidos');
            $table->unsignedInteger('usuario_id')->nullable()->index();
            $table->string('usuario_nome', 150)->nullable();
            $table->longText('dados_antes');
            $table->longText('dados_depois')->nullable();
            $table->string('status', 20)->default('realizada')->index();
            $table->unsignedInteger('desfeito_por')->nullable()->index();
            $table->dateTime('desfeito_em')->nullable();
            $table->dateTime('criado_em')->nullable()->useCurrent();
            $table->dateTime('alterado_em')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unificacoes_realizadas');
    }
};
