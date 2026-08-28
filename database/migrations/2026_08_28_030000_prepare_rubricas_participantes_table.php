<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rubricas_participantes')) {
            Schema::create('rubricas_participantes', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('rubrica', 50)->nullable();
                $table->integer('participante_id')->nullable()->index();
                $table->smallInteger('ativo')->nullable()->index();
                $table->dateTime('criado_em')->nullable()->useCurrent();
                $table->dateTime('alterado_em')->nullable()->useCurrent();
                $table->dateTime('apagado_em')->nullable()->index();
            });

            return;
        }

        foreach (['participante_id', 'ativo', 'apagado_em'] as $column) {
            $indexExists = collect(Schema::getIndexes('rubricas_participantes'))
                ->contains(fn (array $index): bool => $index['columns'] === [$column]);

            if (! $indexExists) {
                Schema::table('rubricas_participantes', function (Blueprint $table) use ($column): void {
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
