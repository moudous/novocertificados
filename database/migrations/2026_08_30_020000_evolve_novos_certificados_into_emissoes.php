<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('novos_certificados', function (Blueprint $table): void {
            $table->string('nome', 150)->nullable()->after('id');
            $table->integer('evento_id')->nullable()->index()->after('template_id');
            $table->integer('atividade_id')->nullable()->index()->after('evento_id');
            $table->unsignedInteger('responsavel_id')->nullable()->index()->after('atividade_id');
            $table->unsignedInteger('rubrica_id')->nullable()->index()->after('responsavel_id');
            $table->date('data_emissao')->nullable()->after('rubrica_id');
            $table->json('campos_personalizados')->nullable()->after('data_emissao');
        });
        Schema::table('lista_participantes', function (Blueprint $table): void {
            $table->string('codigo', 64)->nullable()->unique()->after('novo_certificado_id');
            $table->string('arquivo_pdf', 150)->nullable()->after('codigo');
            $table->json('dados_personalizados')->nullable()->after('arquivo_pdf');
            $table->json('snapshot_dados')->nullable()->after('dados_personalizados');
            $table->json('snapshot_template')->nullable()->after('snapshot_dados');
            $table->dateTime('gerado_em')->nullable()->after('snapshot_template');
            $table->text('erro_geracao')->nullable()->after('gerado_em');
        });
    }

    public function down(): void
    {
        Schema::table('lista_participantes', fn (Blueprint $table) => $table->dropColumn(['codigo','arquivo_pdf','dados_personalizados','snapshot_dados','snapshot_template','gerado_em','erro_geracao']));
        Schema::table('novos_certificados', fn (Blueprint $table) => $table->dropColumn(['nome','evento_id','atividade_id','responsavel_id','rubrica_id','data_emissao','campos_personalizados']));
    }
};
