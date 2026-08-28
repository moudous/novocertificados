<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('variaveis')) {
            Schema::create('variaveis', function (Blueprint $table): void {
                $table->increments('id');
                $table->enum('tipo', ['imagem', 'texto'])->nullable()->index();
                $table->string('imagem', 50)->nullable();
                $table->text('texto')->nullable();
                $table->smallInteger('ativo')->nullable()->index();
                $table->integer('pos_x')->nullable();
                $table->integer('pox_y')->nullable();
                $table->integer('altura')->nullable();
                $table->integer('largura')->nullable();
                $table->enum('alinhamento', ['esquerda', 'direita', 'centralizado', 'justificado'])->nullable();
                $table->string('cor', 15)->nullable();
                $table->integer('centro_x')->nullable();
                $table->integer('centro_y')->nullable();
                $table->dateTime('criado_em')->nullable()->useCurrent();
                $table->dateTime('alterado_em')->nullable()->useCurrent();
                $table->dateTime('apagado_em')->nullable()->index();
            });

            return;
        }

        foreach (['tipo', 'ativo', 'apagado_em'] as $column) {
            $indexExists = collect(Schema::getIndexes('variaveis'))
                ->contains(fn (array $index): bool => $index['columns'] === [$column]);

            if (! $indexExists) {
                Schema::table('variaveis', function (Blueprint $table) use ($column): void {
                    $table->index($column);
                });
            }
        }
    }

    public function down(): void
    {
        // A tabela pode ter sido criada fora do Laravel; a reversão não apaga seus dados.
    }
};
