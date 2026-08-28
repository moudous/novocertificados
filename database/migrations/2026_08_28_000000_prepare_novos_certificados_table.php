<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('novos_certificados')) {
            Schema::create('novos_certificados', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('certificado_antigo_id')->nullable()->index();
                $table->integer('lista_participantes_id')->nullable()->index();
                $table->integer('template_id')->nullable()->index();
                $table->smallInteger('ativo')->nullable()->index();
                $table->dateTime('criado_em')->nullable()->useCurrent();
                $table->dateTime('alterado_em')->nullable()->useCurrent();
                $table->dateTime('apagado_em')->nullable()->index();
            });

            return;
        }

        $this->createIndexIfMissing('certificado_antigo_id');
        $this->createIndexIfMissing('lista_participantes_id');
        $this->createIndexIfMissing('template_id');
        $this->createIndexIfMissing('ativo');
        $this->createIndexIfMissing('apagado_em');
    }

    public function down(): void
    {
        // A tabela pode ter sido criada fora do Laravel; a reversão não apaga seus dados.
    }

    private function createIndexIfMissing(string $column): void
    {
        $indexExists = collect(Schema::getIndexes('novos_certificados'))
            ->contains(fn (array $index): bool => $index['columns'] === [$column]);

        if ($indexExists) {
            return;
        }

        Schema::table('novos_certificados', function (Blueprint $table) use ($column): void {
            $table->index($column);
        });
    }
};
