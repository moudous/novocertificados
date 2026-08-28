<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('templates', 'tipo_fundo')) $table->string('tipo_fundo', 20)->default('imagem')->after('fundo_colorido_ativo');
            if (! Schema::hasColumn('templates', 'fundo_degrade')) $table->string('fundo_degrade', 100)->nullable()->after('tipo_fundo');
            if (! Schema::hasColumn('templates', 'cor_degrade_inicio')) $table->string('cor_degrade_inicio', 7)->nullable()->after('fundo_degrade');
            if (! Schema::hasColumn('templates', 'cor_degrade_fim')) $table->string('cor_degrade_fim', 7)->nullable()->after('cor_degrade_inicio');
        });
        DB::table('templates')->where('fundo_colorido_ativo', true)->update(['tipo_fundo' => 'colorido']);
    }
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            foreach (['cor_degrade_fim','cor_degrade_inicio','fundo_degrade','tipo_fundo'] as $column) if (Schema::hasColumn('templates', $column)) $table->dropColumn($column);
        });
    }
};
