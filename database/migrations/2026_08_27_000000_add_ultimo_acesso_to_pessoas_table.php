<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pessoas', function (Blueprint $table): void {
            $table->timestamp('ultimo_acesso')->nullable()->after('ativo')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pessoas', function (Blueprint $table): void {
            $table->dropIndex(['ultimo_acesso']);
            $table->dropColumn('ultimo_acesso');
        });
    }
};
