<?php

namespace Tests\Feature\Stok;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use Tests\TestCase;

class RusakReservasiTest extends TestCase
{
    private function b(int $baik = 100): Barang
    {
        return Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'RR'.uniqid(),
            'stok_total' => $baik, 'stok_baik' => $baik,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);
    }

    private function acarareservasi(Barang $b, int $jumlah): Acara
    {
        $a = Acara::create([
            'nama_acara' => 'Acara', 'tanggal' => '2026-06-10', 'status' => 'reservasi',
            'penanggung_jawab' => 'P',
        ]);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => $jumlah]);
        return $a;
    }

    public function test_tandai_rusak_ditolak_kalau_barang_dipesan(): void
    {
        $b = $this->b(100);
        $this->acarareservasi($b, 50);

        $res = $this->post('/rusak', [
            'barang_id' => $b->id, 'jumlah' => 10, 'jenis' => 'rusak', 'tingkat' => 'ringan',
        ]);
        $res->assertRedirect();
        $this->assertSame(90, (int) $b->fresh()->stok_baik);

        $res = $this->post('/rusak', [
            'barang_id' => $b->id, 'jumlah' => 50, 'jenis' => 'rusak', 'tingkat' => 'berat',
        ]);
        $res->assertSessionHasErrors('jumlah');

        $b->refresh();
        $this->assertSame(90, (int) $b->stok_baik);
    }

    public function test_tandai_hilang_tetap_ditolak_kalau_ada_reservasi(): void
    {
        $b = $this->b(100);
        $this->acarareservasi($b, 50);

        $this->post('/rusak', [
            'barang_id' => $b->id, 'jumlah' => 10, 'jenis' => 'hilang',
        ])->assertSessionHasErrors('jumlah');

        $b->refresh();
        $this->assertSame(100, (int) $b->stok_baik);
    }
}
