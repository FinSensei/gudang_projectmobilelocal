<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('detail_pengembalian', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pengembalian_id')->constrained('pengembalian')->cascadeOnDelete();
            $t->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $t->unsignedInteger('jumlah_kembali')->default(0);
            $t->unsignedInteger('jumlah_hilang')->default(0);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('detail_pengembalian'); }
};