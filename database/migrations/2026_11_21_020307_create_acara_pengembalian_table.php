<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('acara_pengembalian', function (Blueprint $t) {
            $t->foreignId('pengembalian_id')->constrained('pengembalian')->cascadeOnDelete();
            $t->foreignId('acara_id')->constrained('acara')->cascadeOnDelete();
            $t->primary(['pengembalian_id', 'acara_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('acara_pengembalian'); }
};