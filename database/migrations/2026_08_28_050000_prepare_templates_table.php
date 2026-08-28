<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('templates')) {
            Schema::create('templates', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('nome', 100)->nullable();
                $table->string('fundo', 100)->nullable();
                $table->smallInteger('ativo')->nullable()->index();
                $table->integer('certificado_a1')->nullable()->index();
                $table->integer('largura')->nullable();
                $table->integer('altura')->nullable();
                $table->enum('pagina', ['A4', 'Carta', 'Oficio', 'Personalizado'])->nullable();
                $table->enum('layout_pagina', ['Retrato', 'Paisagem'])->nullable();
                $table->dateTime('crido_em')->nullable()->useCurrent();
                $table->dateTime('alterado_em')->nullable()->useCurrent();
                $table->dateTime('apagado_em')->nullable()->index();
            });

            return;
        }

        foreach (['ativo', 'certificado_a1', 'apagado_em'] as $column) {
            $indexExists = collect(Schema::getIndexes('templates'))
                ->contains(fn (array $index): bool => $index['columns'] === [$column]);
            if (! $indexExists) {
                Schema::table('templates', fn (Blueprint $table) => $table->index($column));
            }
        }
    }

    public function down(): void
    {
        // A tabela pode ter sido criada fora do Laravel; a reversão não apaga seus dados.
    }
};
