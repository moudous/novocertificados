<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biblioteca_imagens', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nome', 120);
            $table->string('categoria', 30)->default('outro')->index();
            $table->string('arquivo', 100);
            $table->string('mime_type', 80)->nullable();
            $table->unsignedInteger('largura_px')->nullable();
            $table->unsignedInteger('altura_px')->nullable();
            $table->unsignedBigInteger('tamanho')->nullable();
            $table->boolean('ativo')->default(true)->index();
            $table->dateTime('criado_em')->nullable()->useCurrent();
            $table->dateTime('alterado_em')->nullable()->useCurrent();
            $table->dateTime('apagado_em')->nullable()->index();
        });

        Schema::table('templates', function (Blueprint $table): void {
            $table->unsignedInteger('biblioteca_imagem_id')->nullable()->index()->after('fundo');
        });
    }

    public function down(): void
    {
        Schema::table('templates', fn (Blueprint $table) => $table->dropColumn('biblioteca_imagem_id'));
        Schema::dropIfExists('biblioteca_imagens');
    }
};
