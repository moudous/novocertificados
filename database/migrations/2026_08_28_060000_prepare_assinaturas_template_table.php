<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assinaturas_template')) {
            Schema::create('assinaturas_template', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('participante_id')->nullable()->index();
                $table->integer('template_id')->nullable()->index();
                $table->string('titulacao', 20)->nullable();
                $table->integer('rubrica_id')->nullable()->index();
                $table->integer('ativo')->nullable()->index();
                $table->dateTime('criado_em')->nullable()->useCurrent();
                $table->dateTime('alterado_em')->nullable()->useCurrent();
                $table->dateTime('apgado_em')->nullable()->index();
            });
            return;
        }

        foreach (['participante_id', 'template_id', 'rubrica_id', 'ativo', 'apgado_em'] as $column) {
            $exists = collect(Schema::getIndexes('assinaturas_template'))->contains(fn (array $index): bool => $index['columns'] === [$column]);
            if (! $exists) Schema::table('assinaturas_template', fn (Blueprint $table) => $table->index($column));
        }
    }

    public function down(): void
    {
        // A tabela pode ter sido criada fora do Laravel; a reversão não apaga seus dados.
    }
};
