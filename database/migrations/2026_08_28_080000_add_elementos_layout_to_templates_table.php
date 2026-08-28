<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('templates', 'elementos_layout')) {
            Schema::table('templates', fn (Blueprint $table) => $table->json('elementos_layout')->nullable()->after('layout_pagina'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('templates', 'elementos_layout')) {
            Schema::table('templates', fn (Blueprint $table) => $table->dropColumn('elementos_layout'));
        }
    }
};
