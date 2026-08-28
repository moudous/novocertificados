<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('eventos')) {
            return;
        }

        Schema::create('eventos', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nome', 200)->nullable();
            $table->string('periodos', 100)->nullable();
            $table->smallInteger('ativo')->nullable();
            $table->text('descricao')->nullable();
            $table->dateTime('criado_em')->nullable()->useCurrent();
            $table->dateTime('atualizado_em')->nullable()->useCurrent();
            $table->dateTime('apagado_em')->nullable()->index();
        });
    }

    public function down(): void
    {
        // A tabela é legada e pode conter dados anteriores ao Laravel.
    }
};
