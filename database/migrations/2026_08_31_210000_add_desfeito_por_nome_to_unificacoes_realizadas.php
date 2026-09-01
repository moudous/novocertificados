<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('unificacoes_realizadas', 'desfeito_por_nome')) {
            Schema::table('unificacoes_realizadas', fn (Blueprint $table) => $table->string('desfeito_por_nome', 150)->nullable()->after('desfeito_por'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('unificacoes_realizadas', 'desfeito_por_nome')) {
            Schema::table('unificacoes_realizadas', fn (Blueprint $table) => $table->dropColumn('desfeito_por_nome'));
        }
    }
};
