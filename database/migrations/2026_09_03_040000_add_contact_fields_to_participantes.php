<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participantes', function (Blueprint $table): void {
            if (! Schema::hasColumn('participantes', 'email2')) $table->string('email2', 150)->nullable()->after('email');
            if (! Schema::hasColumn('participantes', 'email_institucional')) $table->string('email_institucional', 150)->nullable()->after('email2');
            if (! Schema::hasColumn('participantes', 'instituicao_ensino')) $table->string('instituicao_ensino', 80)->nullable()->after('email_institucional');
        });
    }

    public function down(): void
    {
        $colunas = collect(['email2', 'email_institucional', 'instituicao_ensino'])->filter(fn ($coluna) => Schema::hasColumn('participantes', $coluna))->all();
        if ($colunas !== []) Schema::table('participantes', fn (Blueprint $table) => $table->dropColumn($colunas));
    }
};
