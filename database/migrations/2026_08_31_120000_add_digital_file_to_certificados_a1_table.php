<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificados_a1', function (Blueprint $table): void {
            $table->string('arquivo',160)->nullable()->after('nome');
            $table->string('nome_arquivo_original',255)->nullable()->after('arquivo');
            $table->text('senha')->nullable()->after('nome_arquivo_original');
            $table->string('titular',255)->nullable()->after('senha');
            $table->string('impressao_digital',128)->nullable()->after('titular');
            $table->dateTime('valido_de')->nullable()->after('impressao_digital');
            $table->dateTime('valido_ate')->nullable()->after('valido_de');
        });
    }

    public function down(): void
    {
        Schema::table('certificados_a1',fn(Blueprint $table)=>$table->dropColumn(['arquivo','nome_arquivo_original','senha','titular','impressao_digital','valido_de','valido_ate']));
    }
};
