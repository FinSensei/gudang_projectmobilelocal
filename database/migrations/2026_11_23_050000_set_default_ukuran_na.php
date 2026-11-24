<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Barang lama yang ukurannya NULL -> "N/A"
        DB::table('barang')->whereNull('ukuran')->update(['ukuran' => 'N/A']);

        // Kolom default jadi "N/A" untuk barang baru
        Schema::table('barang', function (Blueprint $table) {
            $table->string('ukuran')->default('N/A')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            $table->string('ukuran')->nullable()->default(null)->change();
        });
    }
};