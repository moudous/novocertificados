<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('participantes_de_teste')) {
            Schema::create('participantes_de_teste', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('participante_id')->nullable()->unique();
                $table->dateTime('criado_em')->nullable()->useCurrent();
                $table->dateTime('alterado_em')->nullable()->useCurrent();
                $table->dateTime('apagado_em')->nullable()->index();
            });

            return;
        }

        Schema::table('participantes_de_teste', function (Blueprint $table): void {
            if (! Schema::hasColumn('participantes_de_teste', 'criado_em')) {
                $table->dateTime('criado_em')->nullable()->useCurrent();
            }
            if (! Schema::hasColumn('participantes_de_teste', 'alterado_em')) {
                $table->dateTime('alterado_em')->nullable()->useCurrent();
            }
            if (! Schema::hasColumn('participantes_de_teste', 'apagado_em')) {
                $table->dateTime('apagado_em')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        // A tabela pode ter sido criada fora do Laravel; a reversão não apaga seus dados.
    }
};
