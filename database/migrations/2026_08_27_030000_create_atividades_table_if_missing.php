<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('atividades')) {
            return;
        }

        Schema::create('atividades', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('eventoId')->nullable()->index();
            $table->string('nome', 200)->nullable();
            $table->text('descricao_old')->nullable();
            $table->string('periodos', 100)->nullable();
            $table->smallInteger('ativo')->nullable();
            $table->string('imagemFundo', 50)->nullable();
            $table->text('template_php')->nullable();
            $table->integer('template_id')->nullable();
            $table->dateTime('criado_em')->nullable()->useCurrent();
            $table->dateTime('atualizado_em')->nullable()->useCurrent();
            $table->dateTime('apagado_em')->nullable()->index();
        });
    }

    public function down(): void
    {
        // A tabela pode ser legada; a reversão não apaga seus dados.
    }
};
