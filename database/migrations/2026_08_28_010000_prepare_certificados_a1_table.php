<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('certificados_a1')) {
            Schema::create('certificados_a1', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('nome', 50)->nullable();
                $table->dateTime('criado_em')->nullable()->useCurrent();
                $table->dateTime('alterado_em')->nullable()->useCurrent();
                $table->dateTime('apagado_em')->nullable()->index();
            });

            return;
        }

        $indexExists = collect(Schema::getIndexes('certificados_a1'))
            ->contains(fn (array $index): bool => $index['columns'] === ['apagado_em']);

        if (! $indexExists) {
            Schema::table('certificados_a1', function (Blueprint $table): void {
                $table->index('apagado_em');
            });
        }
    }

    public function down(): void
    {
        // A tabela pode ter sido criada fora do Laravel; a reversão não apaga seus dados.
    }
};
