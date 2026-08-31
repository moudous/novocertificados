<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biblioteca_imagens', function (Blueprint $table): void {
            $table->longText('svg')->nullable()->after('tamanho');
        });
    }

    public function down(): void
    {
        Schema::table('biblioteca_imagens', fn (Blueprint $table) => $table->dropColumn('svg'));
    }
};
