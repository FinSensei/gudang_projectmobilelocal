<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index untuk kolom yang sering dipakai di WHERE / ORDER BY.
     * Tanpa ini, setiap query full-scan — makin lambat saat data ratusan.
     */
    public function up(): void
    {
        Schema::table('acara', function (Blueprint $table) {
            $table->index('status', 'acara_status_index');
            $table->index('tanggal', 'acara_tanggal_index');
        });

        Schema::table('pengembalian', function (Blueprint $table) {
            $table->index('tanggal_pulang', 'pengembalian_tanggal_index');
        });

        Schema::table('barang', function (Blueprint $table) {
            $table->index('nama_barang', 'barang_nama_index');
            $table->index('stok_baik', 'barang_stok_baik_index');
        });

        Schema::table('detail_acara', function (Blueprint $table) {
            $table->index(['acara_id', 'barang_id'], 'detail_acara_acara_barang_index');
        });
    }

    public function down(): void
    {
        Schema::table('acara', function (Blueprint $table) {
            $table->dropIndex('acara_status_index');
            $table->dropIndex('acara_tanggal_index');
        });
        Schema::table('pengembalian', function (Blueprint $table) {
            $table->dropIndex('pengembalian_tanggal_index');
        });
        Schema::table('barang', function (Blueprint $table) {
            $table->dropIndex('barang_nama_index');
            $table->dropIndex('barang_stok_baik_index');
        });
        Schema::table('detail_acara', function (Blueprint $table) {
            $table->dropIndex('detail_acara_acara_barang_index');
        });
    }
};