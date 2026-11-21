<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('pengembalian', function (Blueprint $t) {
            $t->id(); $t->date('tanggal_pulang'); $t->string('dicatat_oleh')->nullable();
            $t->text('catatan')->nullable(); $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('pengembalian'); }
};