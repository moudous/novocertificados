<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lista_participantes', function (Blueprint $table): void {
            $table->string('codigo_img', 64)->nullable()->unique()->after('codigo');
            $table->string('arquivo_img')->nullable()->after('arquivo_pdf');
        });
    }

    public function down(): void
    {
        Schema::table('lista_participantes', fn (Blueprint $table) => $table->dropColumn(['codigo_img', 'arquivo_img']));
    }
};
