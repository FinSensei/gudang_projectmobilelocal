<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\DetailAcara;

class ReservasiBatasStokTest extends TestCase
{
    private function b(int $baik = 10): Barang
    {
        return Barang::create([
            'nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'RB'.uniqid(),
            'stok_total'=>100,'stok_baik'=>$baik,
            'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0,
        ]);
    }

    private function reservasi(Barang $b, int $jumlah, string $tanggal = '+7 days'): Acara
    {
        $this->post('/acara', [
            'nama_acara' => 'Acara',
            'tanggal'    => today()->addDays(7)->toDateString(),
            'items'      => [['barang_id'=>$b->id,'jumlah'=>$jumlah]],
        ]);

        return Acara::latest('id')->first();
    }

    // ---------- BUG: satu reservasi melebihi stok baik ----------
    public function test_reservasi_tidak_boleh_melebihi_stok_baik(): void
    {
        $b = $this->b(10);

        $this->post('/acara', [
            'nama_acara'  => 'Berlebih',
            'tanggal'     => today()->addDays(7)->toDateString(),
            'items'       => [['barang_id'=>$b->id,'jumlah'=>50]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, Acara::count(), 'reservasi tidak boleh dibuat kalau melebihi baik');
    }

    public function test_reservasi_boleh_sampai_batas_stok_baik(): void
    {
        $b = $this->b(10);

        $this->post('/acara', [
            'nama_acara'  => 'Pas',
            'tanggal'     => today()->addDays(7)->toDateString(),
            'items'       => [['barang_id'=>$b->id,'jumlah'=>10]],
        ])->assertRedirect();

        $this->assertSame(1, Acara::count(), 'pas dengan baik boleh');
    }

    // ---------- BUG: edit reservasi melebihi stok baik ----------
    public function test_edit_reservasi_tidak_boleh_melebihi_stok_baik(): void
    {
        $b = $this->b(10);
        $a = $this->reservasi($b, 5);

        $this->put("/acara/{$a->id}", [
            'nama_acara' => 'Acara',
            'tanggal'    => today()->addDays(7)->toDateString(),
            'items'      => [['barang_id'=>$b->id,'jumlah'=>50]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(5, (int) $a->fresh()->detailAcara()->value('jumlah_keluar'), 'jumlah tetap 5');
    }

    // ---------- Wajar: dua reservasi boleh bersaing ----------
    public function test_dua_reservasi_boleh_melebihi_stok_baik_secara_kumulatif(): void
    {
        $b = $this->b(10);

        $a1 = $this->reservasi($b, 8);
        $a2 = $this->reservasi($b, 8);

        $this->assertNotNull($a1->fresh());
        $this->assertNotNull($a2->fresh());
        $this->assertSame(2, Acara::count(), 'dua reservasi boleh dibuat (stok belum dipotong)');
        $this->assertSame(10, $b->fresh()->stok_baik, 'stok baik tidak berubah');
    }

    public function test_konfirmasi_yang_kedua_ditolak_karena_stok_tidak_cukup(): void
    {
        $b = $this->b(10);

        $a1 = $this->reservasi($b, 8);
        $a2 = $this->reservasi($b, 8);

        $this->post("/acara/{$a1->id}/konfirmasi")->assertRedirect();
        $this->assertSame(2, $b->fresh()->stok_baik, '8 dari 10 -> sisa 2');

        $this->post("/acara/{$a2->id}/konfirmasi")->assertRedirect();

        $this->assertSame('reservasi', $a2->fresh()->status, 'konfirmasi ke-2 ditolak');
        $this->assertSame(2, $b->fresh()->stok_baik, 'stok tidak berubah');
    }
}