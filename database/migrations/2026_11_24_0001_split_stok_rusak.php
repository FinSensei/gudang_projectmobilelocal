<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            $table->unsignedInteger('stok_rusak_ringan')->default(0)->after('stok_rusak');
            $table->unsignedInteger('stok_rusak_berat')->default(0)->after('stok_rusak_ringan');
        });

        // Data lama: nilai "rusak" umum -> dianggap rusak berat (konservatif,
        // karena tidak bisa dibuktikan masih ringan). Saat ini semua 0.
        DB::table('barang')->update(['stok_rusak_berat' => DB::raw('stok_rusak')]);

        // Kolom stok_rusak tunggal dihapus — sekarang jadi accessor (ringan + berat)
        Schema::table('barang', function (Blueprint $table) {
            $table->dropColumn('stok_rusak');
        });
    }

    public function down(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            $table->unsignedInteger('stok_rusak')->default(0)->after('stok_baik');
        });

        DB::table('barang')->update(['stok_rusak' => DB::raw('stok_rusak_ringan + stok_rusak_berat')]);

        Schema::table('barang', function (Blueprint $table) {
            $table->dropColumn(['stok_rusak_ringan', 'stok_rusak_berat']);
        });
    }
};