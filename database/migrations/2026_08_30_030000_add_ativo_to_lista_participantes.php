<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lista_participantes', 'ativo')) {
            Schema::table('lista_participantes', fn (Blueprint $table) => $table->boolean('ativo')->default(true)->index()->after('novo_certificado_id'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('lista_participantes', 'ativo')) Schema::table('lista_participantes', fn (Blueprint $table) => $table->dropColumn('ativo'));
    }
};
