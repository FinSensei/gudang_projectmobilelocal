<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('log_aktivitas', function (Blueprint $t) {
            $t->id(); $t->string('aksi'); $t->string('entitas')->nullable();
            $t->text('keterangan')->nullable(); $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('log_aktivitas'); }
};