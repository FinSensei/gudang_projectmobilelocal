<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('acara', function (Blueprint $t) {
            $t->id(); $t->string('nama_acara'); $t->string('penanggung_jawab')->nullable();
            $t->date('tanggal')->nullable(); $t->string('lokasi')->nullable();
            $t->string('status')->default('aktif'); $t->text('catatan')->nullable();
            $t->timestamps(); $t->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('acara'); }
};