<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('templates', 'direcao_degrade')) Schema::table('templates', fn (Blueprint $table) => $table->string('direcao_degrade', 30)->default('cima_baixo')->after('cor_degrade_fim'));
    }
    public function down(): void
    {
        if (Schema::hasColumn('templates', 'direcao_degrade')) Schema::table('templates', fn (Blueprint $table) => $table->dropColumn('direcao_degrade'));
    }
};
