<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fontes_layout')) {
            Schema::create('fontes_layout', function (Blueprint $table): void {
                $table->id();
                $table->string('nome', 100);
                $table->string('arquivo', 100)->unique();
                $table->string('nome_original', 255);
                $table->dateTime('criado_em')->nullable()->useCurrent();
                $table->dateTime('alterado_em')->nullable()->useCurrent();
            });
        }
    }

    public function down(): void { Schema::dropIfExists('fontes_layout'); }
};
