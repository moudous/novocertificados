<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['certificados_a1', 'templates', 'participantes'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'criado_por')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                    $after = match ($tableName) {
                        'certificados_a1' => 'nome',
                        'templates' => 'nome',
                        default => 'cpf',
                    };
                    $table->unsignedInteger('criado_por')->nullable()->index()->after($after);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['certificados_a1', 'templates', 'participantes'] as $tableName) {
            if (Schema::hasColumn($tableName, 'criado_por')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropColumn('criado_por');
                });
            }
        }
    }
};
