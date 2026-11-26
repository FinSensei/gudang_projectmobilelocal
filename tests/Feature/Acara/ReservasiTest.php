<?php

namespace Tests\Feature\Acara;

use App\Models\Acara;
use App\Models\Barang;
use Tests\TestCase;

class ReservasiTest extends TestCase
{
    private function b(int $baik = 100): Barang
    {
        return Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'RS'.uniqid(),
            'stok_total' => $baik, 'stok_baik' => $baik,
        ]);
    }

    private function buatAcara(Barang $b, int $jumlah, string $tanggal): Acara
    {
        $this->post('/acara', [
            'nama_acara' => 'Acara '.$b->id,
            'tanggal' => $tanggal,
            'items' => [['barang_id' => $b->id, 'jumlah' => $jumlah]],
        ])->assertRedirect();

        return Acara::latest('id')->first();
    }

    public function test_acara_baru_berstatus_reservasi(): void
    {
        $a = $this->buatAcara($this->b(), 30, today()->addDays(7)->toDateString());

        $this->assertSame('reservasi', $a->status, 'acara baru harus reservasi, bukan langsung aktif');
    }

    public function test_reservasi_tidak_memotong_stok_baik(): void
    {
        $b = $this->b(100);
        $this->buatAcara($b, 30, today()->addDays(7)->toDateString());

        $this->assertSame(100, $b->fresh()->stok_baik, 'reservasi belum memotong stok');
    }

    public function test_reservasi_tetap_mencatat_barang_yang_dipesan(): void
    {
        $b = $this->b(100);
        $a = $this->buatAcara($b, 30, today()->addDays(7)->toDateString());

        $this->assertSame(30, (int) $a->detailAcara()->sum('jumlah_keluar'));
    }

    public function test_konfirmasi_mengubah_status_jadi_aktif(): void
    {
        $b = $this->b(100);
        $a = $this->buatAcara($b, 30, today()->addDays(7)->toDateString());

        $this->post("/acara/{$a->id}/konfirmasi")->assertRedirect();

        $this->assertSame('aktif', $a->fresh()->status);
    }

    public function test_konfirmasi_memotong_stok_baik(): void
    {
        $b = $this->b(100);
        $a = $this->buatAcara($b, 30, today()->addDays(7)->toDateString());

        $this->post("/acara/{$a->id}/konfirmasi")->assertRedirect();

        $this->assertSame(70, $b->fresh()->stok_baik, '100 - 30 = 70');
    }

    public function test_konfirmasi_ditolak_jika_stok_baik_tidak_cukup(): void
    {
        $b = $this->b(10);
        // reservasi pas (10 dari baik 10)
        $a = $this->buatAcara($b, 10, today()->addDays(7)->toDateString());

        // sumber lain memakai stok sebelum konfirmasi (mis. acara lain)
        $b->stok_baik = 3;
        $b->save();

        $this->post("/acara/{$a->id}/konfirmasi")->assertRedirect();

        $a->refresh();
        $this->assertSame('reservasi', $a->status, 'konfirmasi gagal, status tetap reservasi');
        $this->assertSame(3, $b->fresh()->stok_baik, 'stok tidak berubah');
    }

    public function test_dua_reservasi_barang_sama_boleh_selama_baik_cukup(): void
    {
        $b = $this->b(100);
        $a1 = $this->buatAcara($b, 30, today()->addDays(7)->toDateString());
        $a2 = $this->buatAcara($b, 30, today()->addDays(9)->toDateString());

        $this->assertSame(100, $b->fresh()->stok_baik, 'reservasi tidak mengunci stok');

        $this->post("/acara/{$a1->id}/konfirmasi")->assertRedirect();
        $this->assertSame(70, $b->fresh()->stok_baik);

        $this->post("/acara/{$a2->id}/konfirmasi")->assertRedirect();
        $this->assertSame(40, $b->fresh()->stok_baik, '100 - 30 - 30 = 40');
    }

    public function test_konfirmasi_tidak_bisa_dua_kali(): void
    {
        $b = $this->b(100);
        $a = $this->buatAcara($b, 30, today()->addDays(7)->toDateString());

        $this->post("/acara/{$a->id}/konfirmasi")->assertRedirect();
        $this->assertSame(70, $b->fresh()->stok_baik);

        // konfirmasi kedua harus ditolak, tidak memotong lagi
        $this->post("/acara/{$a->id}/konfirmasi")->assertRedirect();
        $this->assertSame(70, $b->fresh()->stok_baik, 'tidak boleh memotong 2x');
    }
}
