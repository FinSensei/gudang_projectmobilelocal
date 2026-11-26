<?php

namespace Tests\Feature\Acara;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use Tests\TestCase;

class ReservasiBatasTest extends TestCase
{
    private function b(int $baik = 100): Barang
    {
        return Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'RB'.uniqid(),
            'stok_total' => 100, 'stok_baik' => $baik,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);
    }

    private function reservasi(Barang $b, int $jumlah): Acara
    {
        $this->post('/acara', [
            'nama_acara' => 'Acara',
            'tanggal' => today()->addDays(7)->toDateString(),
            'items' => [['barang_id' => $b->id, 'jumlah' => $jumlah]],
        ])->assertRedirect();

        return Acara::latest('id')->first();
    }

    // ---------- Buat reservasi ----------
    public function test_reservasi_melebihi_stok_baik_ditolak(): void
    {
        $b = $this->b(100);

        $this->post('/acara', [
            'nama_acara' => 'Kebanyakan',
            'tanggal' => today()->addDays(7)->toDateString(),
            'items' => [['barang_id' => $b->id, 'jumlah' => 150]],
        ])->assertRedirect();

        $this->assertSame(0, Acara::count(), 'reservasi tidak boleh dibuat');
    }

    public function test_reservasi_boleh_sampai_batas_stok_baik(): void
    {
        $b = $this->b(100);

        $a = $this->reservasi($b, 100);

        $this->assertNotNull($a, 'reservasi tepat sebanyak stok baik harus boleh');
        $this->assertSame('reservasi', $a->status);
    }

    public function test_reservasi_tidak_boleh_melebihi_stok_total(): void
    {
        // baik 60, rusak 40 -> total 100. Reservasi 80 harus ditolak
        // karena yang bisa dipakai hanya 60.
        $b = Barang::create([
            'nama_barang' => 'Gelas', 'satuan' => 'pcs', 'kode_qr' => 'RBX',
            'stok_total' => 100, 'stok_baik' => 60,
            'stok_rusak_ringan' => 25, 'stok_rusak_berat' => 15,
        ]);

        $this->post('/acara', [
            'nama_acara' => 'Minta 80',
            'tanggal' => today()->addDays(7)->toDateString(),
            'items' => [['barang_id' => $b->id, 'jumlah' => 80]],
        ])->assertRedirect();

        $this->assertSame(0, Acara::count(), 'baik cuma 60, tidak boleh ambil 80');
    }

    // ---------- Edit reservasi ----------
    public function test_edit_reservasi_melebihi_stok_baik_ditolak(): void
    {
        $b = $this->b(100);
        $a = $this->reservasi($b, 10);

        $this->put("/acara/{$a->id}", [
            'nama_acara' => 'Acara', 'tanggal' => today()->addDays(7)->toDateString(),
            'items' => [['barang_id' => $b->id, 'jumlah' => 150]],
        ])->assertRedirect();

        $this->assertSame(10, (int) DetailAcara::where('acara_id', $a->id)->value('jumlah_keluar'),
            'jumlah lama (10) harus tetap, bukan 150');
    }

    public function test_edit_reservasi_boleh_menaikkan_selama_masih_cukup(): void
    {
        $b = $this->b(100);
        $a = $this->reservasi($b, 10);

        $this->put("/acara/{$a->id}", [
            'nama_acara' => 'Acara', 'tanggal' => today()->addDays(7)->toDateString(),
            'items' => [['barang_id' => $b->id, 'jumlah' => 70]],
        ])->assertRedirect();

        $this->assertSame(70, (int) DetailAcara::where('acara_id', $a->id)->value('jumlah_keluar'));
    }

    public function test_edit_aktif_melebihi_stok_baik_ditolak(): void
    {
        $b = $this->b(100);
        $a = Acara::create([
            'nama_acara' => 'Aktif', 'tanggal' => today()->toDateString(),
            'status' => 'aktif', 'penanggung_jawab' => 'P',
        ]);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => 10]);
        $b->stok_baik = 90;
        $b->save();

        $this->put("/acara/{$a->id}", [
            'nama_acara' => 'Aktif', 'tanggal' => today()->toDateString(),
            'items' => [['barang_id' => $b->id, 'jumlah' => 500]],
        ])->assertRedirect();

        $this->assertSame(10, (int) DetailAcara::where('acara_id', $a->id)->value('jumlah_keluar'));
        $this->assertSame(90, $b->fresh()->stok_baik, 'stok tidak berubah');
    }
}
