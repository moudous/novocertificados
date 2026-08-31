<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imagens_template', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('template_id')->index();
            $table->unsignedInteger('biblioteca_imagem_id')->nullable()->index();
            $table->string('element_uid', 80);
            $table->string('nome', 160);
            $table->longText('svg');
            $table->dateTime('criado_em')->nullable()->useCurrent();
            $table->dateTime('alterado_em')->nullable()->useCurrent();
            $table->unique(['template_id','element_uid']);
            $table->foreign('template_id')->references('id')->on('templates')->cascadeOnDelete();
            $table->foreign('biblioteca_imagem_id')->references('id')->on('biblioteca_imagens')->nullOnDelete();
        });
    }

    public function down(): void { Schema::dropIfExists('imagens_template'); }
};
