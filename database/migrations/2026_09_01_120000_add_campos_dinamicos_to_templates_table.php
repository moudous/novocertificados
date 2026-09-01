<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('templates', 'campos_dinamicos')) {
            Schema::table('templates', fn (Blueprint $table) => $table->json('campos_dinamicos')->nullable()->after('elementos_layout'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('templates', 'campos_dinamicos')) Schema::table('templates', fn (Blueprint $table) => $table->dropColumn('campos_dinamicos'));
    }
};
