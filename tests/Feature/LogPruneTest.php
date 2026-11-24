<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\LogAktivitas;

class LogPruneTest extends TestCase
{
    public function test_hanya_20_aktivitas_terbaru_disimpan(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            LogAktivitas::create([
                'aksi' => 'uji',
                'entitas' => 'barang',
                'keterangan' => "aktivitas ke-$i",
            ]);
        }

        $this->assertSame(20, LogAktivitas::count(), 'hanya 20 terbaru yang tersimpan');

        // yang tersisa harus 20 terbaru (6..25), bukan 1..20
        $this->assertDatabaseMissing('log_aktivitas', ['keterangan' => 'aktivitas ke-1']);
        $this->assertDatabaseHas('log_aktivitas', ['keterangan' => 'aktivitas ke-25']);
    }

    public function test_halaman_log_menampilkan_maksimal_20(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            LogAktivitas::create([
                'aksi' => 'uji',
                'entitas' => 'barang',
                'keterangan' => "aktivitas ke-$i",
            ]);
        }

        $this->get('/log')
            ->assertOk()
            ->assertSee('20 aktivitas');
    }

    public function test_log_tidak_menampilkan_data_lama(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            LogAktivitas::create([
                'aksi' => 'uji',
                'entitas' => 'barang',
                'keterangan' => "aktivitas ke-$i",
            ]);
        }

        $res = $this->get('/log')->assertOk();
        $res->assertSee('aktivitas ke-25');
        $res->assertDontSee('>aktivitas ke-1<', false);
    }
}