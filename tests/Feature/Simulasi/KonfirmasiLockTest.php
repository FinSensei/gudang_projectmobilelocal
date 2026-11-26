<?php

namespace Tests\Feature\Simulasi;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KonfirmasiLockTest extends TestCase
{
    private function b(int $baik = 10): Barang
    {
        return Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'KL'.uniqid(),
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

    public function test_konfirmasi_berurutan_sama_barang_mencegah_lebih_dari_stok(): void
    {
        $b = $this->b(10);
        $a1 = $this->acarareservasi($b, 6);
        $a2 = $this->acarareservasi($b, 6);

        $this->post("/acara/{$a1->id}/konfirmasi")->assertRedirect();
        $this->assertSame(4, (int) $b->fresh()->stok_baik);

        $res = $this->post("/acara/{$a2->id}/konfirmasi");
        $res->assertSessionHas('err');

        $b->refresh();
        $this->assertSame(4, (int) $b->stok_baik);
        $this->assertSame('reservasi', $a2->fresh()->status);
    }

    public function test_konfirmasi_mengunci_barang_tidak_hanya_acara(): void
    {
        $b = $this->b(20);
        $a = $this->acarareservasi($b, 5);

        $sql = [];
        DB::listen(function ($q) use (&$sql) {
            $sql[] = $q->sql;
        });

        $this->post("/acara/{$a->id}/konfirmasi")->assertRedirect();

        $adaLockBarang = collect($sql)->contains(fn ($s) =>
            str_contains(strtolower($s), 'from `barang`') && str_contains(strtolower($s), 'for update')
        );

        $this->assertTrue($adaLockBarang, 'konfirmasi harus mengunci baris Barang');
    }
}
