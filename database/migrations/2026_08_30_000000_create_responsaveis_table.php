<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responsaveis', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('participante_id')->unique();
            $table->string('cargo', 100)->nullable();
            $table->string('titulacao', 100)->nullable();
            $table->boolean('ativo')->default(true)->index();
            $table->dateTime('criado_em')->nullable()->useCurrent();
            $table->dateTime('alterado_em')->nullable()->useCurrent();
            $table->dateTime('apagado_em')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responsaveis');
    }
};
