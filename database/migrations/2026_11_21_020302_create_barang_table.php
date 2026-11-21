<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('barang', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kategori_id')->nullable()->constrained('kategori')->nullOnDelete();
            $t->string('nama_barang'); $t->string('satuan')->default('pcs');
            $t->string('kode_qr')->unique();
            $t->unsignedInteger('stok_total')->default(0);
            $t->unsignedInteger('stok_baik')->default(0);
            $t->unsignedInteger('stok_rusak')->default(0);
            $t->string('lokasi')->nullable(); $t->string('foto')->nullable();
            $t->string('status_kondisi')->default('baik');
            $t->timestamps(); $t->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('barang'); }
};