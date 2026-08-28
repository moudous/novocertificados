<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lista_participantes')) {
            Schema::create('lista_participantes', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('participante_id')->nullable()->index();
                $table->integer('novo_certificado_id')->nullable()->index();
                $table->dateTime('criado_em')->nullable()->useCurrent();
                $table->dateTime('alterado_em')->nullable()->useCurrent();
                $table->unique(['novo_certificado_id', 'participante_id'], 'lista_participantes_certificado_participante_unique');
            });
            return;
        }

        foreach (['participante_id', 'novo_certificado_id'] as $column) {
            $exists = collect(Schema::getIndexes('lista_participantes'))->contains(fn (array $index): bool => $index['columns'] === [$column]);
            if (! $exists) Schema::table('lista_participantes', fn (Blueprint $table) => $table->index($column));
        }

        $uniqueExists = collect(Schema::getIndexes('lista_participantes'))->contains(
            fn (array $index): bool => $index['unique'] && $index['columns'] === ['novo_certificado_id', 'participante_id'],
        );
        if (! $uniqueExists) {
            Schema::table('lista_participantes', fn (Blueprint $table) => $table->unique(
                ['novo_certificado_id', 'participante_id'],
                'lista_participantes_certificado_participante_unique',
            ));
        }
    }

    public function down(): void
    {
        // A tabela pode ter sido criada fora do Laravel; a reversão não apaga seus dados.
    }
};
