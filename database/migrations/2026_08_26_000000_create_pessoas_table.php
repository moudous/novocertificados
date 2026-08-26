<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoas', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('usuario')->nullable();
            $table->string('nome');
            $table->string('email')->unique();
            $table->string('perfil')->nullable();
            $table->unsignedBigInteger('perfil_id')->nullable()->index();
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoas');
    }
};
