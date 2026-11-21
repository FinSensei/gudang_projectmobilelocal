<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('detail_acara', function (Blueprint $t) {
            $t->id();
            $t->foreignId('acara_id')->constrained('acara')->cascadeOnDelete();
            $t->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $t->unsignedInteger('jumlah_keluar');
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('detail_acara'); }
};