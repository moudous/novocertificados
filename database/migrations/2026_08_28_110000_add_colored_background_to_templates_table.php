<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('templates', 'fundo_colorido')) $table->string('fundo_colorido', 100)->nullable()->after('fundo');
            if (! Schema::hasColumn('templates', 'cor_fundo')) $table->string('cor_fundo', 7)->nullable()->after('fundo_colorido');
            if (! Schema::hasColumn('templates', 'fundo_colorido_ativo')) $table->boolean('fundo_colorido_ativo')->default(false)->after('cor_fundo');
        });
    }
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            foreach (['fundo_colorido_ativo','cor_fundo','fundo_colorido'] as $column) if (Schema::hasColumn('templates', $column)) $table->dropColumn($column);
        });
    }
};
