<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('variaveis');
    }

    public function down(): void
    {
        // O módulo foi removido definitivamente. Não recriamos uma estrutura obsoleta no rollback.
    }
};
