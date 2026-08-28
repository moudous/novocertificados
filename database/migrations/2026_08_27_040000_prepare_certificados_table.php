<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('certificados')) {
            Schema::create('certificados', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('participanteId')->nullable();
                $table->string('nome', 600)->nullable();
                $table->string('arquivo', 50)->nullable();
                $table->integer('atividadeId')->nullable();
                $table->integer('categoriaId')->nullable();
                $table->string('titulo', 256)->nullable();
                $table->string('titulo2', 256)->nullable();
                $table->string('titulo3', 256)->nullable();
                $table->string('titulo4', 100)->nullable();
                $table->integer('cargaHoraria')->nullable();
                $table->string('outrosParticipantes', 700)->nullable();
                $table->string('tipo', 50)->nullable();
                $table->smallInteger('ativo')->nullable();
                $table->string('arquivo_old', 50)->nullable();
                $table->dateTime('criado_em')->nullable()->useCurrent();
                $table->dateTime('atualizado_em')->nullable()->useCurrent();
                $table->dateTime('apagado_em')->nullable()->index();
            });

            return;
        }

        if (! Schema::hasColumn('certificados', 'apagado_em')) {
            Schema::table('certificados', function (Blueprint $table): void {
                $table->dateTime('apagado_em')->nullable()->after('atualizado_em')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('certificados') && Schema::hasColumn('certificados', 'apagado_em')) {
            Schema::table('certificados', function (Blueprint $table): void {
                $table->dropIndex(['apagado_em']);
                $table->dropColumn('apagado_em');
            });
        }
    }
};
