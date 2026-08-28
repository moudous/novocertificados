<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participantes', function (Blueprint $table): void {
            $table->timestamp('excluido_em')->nullable()->after('atualizado_em')->index();
        });
    }

    public function down(): void
    {
        Schema::table('participantes', function (Blueprint $table): void {
            $table->dropIndex(['excluido_em']);
            $table->dropColumn('excluido_em');
        });
    }
};
