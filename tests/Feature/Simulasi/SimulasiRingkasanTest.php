<?php

namespace Tests\Feature\Simulasi;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\Pengembalian;
use App\Services\CheckoutService;
use App\Services\PengembalianService;
use Tests\TestCase;

/**
 * Reproduksi keluhan user: setelah undo + catat kembali lagi,
 * Beranda menampilkan "unit kembali" > "unit keluar".
 */
class SimulasiRingkasanTest extends TestCase
{
    public function test_ringkasan_kembali_tidak_melebihi_keluar(): void
    {
        $b = Barang::create(['nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'S1', 'stok_total' => 100, 'stok_baik' => 100, 'stok_rusak' => 0]);

        $a = Acara::create(['nama_acara' => 'Konser', 'tanggal' => '2026-01-01', 'status' => 'aktif']);
        app(CheckoutService::class)->buat($a, [['barang_id' => $b->id, 'jumlah' => 40]]);

        // return #1: 35 kembali
        $p1 = Pengembalian::create(['tanggal_pulang' => '2026-01-02', 'dicatat_oleh' => 'P']);
        $p1->acara()->attach($a->id);
        $p1->detailPengembalian()->create(['barang_id' => $b->id, 'jumlah_kembali' => 35]);
        app(PengembalianService::class)->hitung($p1);

        // undo -> acara aktif lagi
        $this->post("/pengembalian/{$p1->id}/undo")->assertRedirect();

        // return #2: 40 kembali
        $p2 = Pengembalian::create(['tanggal_pulang' => '2026-01-03', 'dicatat_oleh' => 'P']);
        $p2->acara()->attach($a->id);
        $p2->detailPengembalian()->create(['barang_id' => $b->id, 'jumlah_kembali' => 40]);
        app(PengembalianService::class)->hitung($p2);

        $res = $this->get('/')->assertOk();

        $keluar = (int) $res->viewData('totalKeluar');
        $kembali = (int) $res->viewData('totalKembali');

        $this->assertSame(40, $keluar, 'unit keluar = 40');
        $this->assertSame(40, $kembali, 'unit kembali = 40 (bukan 35+40=75)');
        $this->assertLessThanOrEqual($keluar, $kembali, 'kembali tidak boleh > keluar');
    }

    public function test_ringkasan_keluar_tidak_menghitung_acara_batal(): void
    {
        $b = Barang::create(['nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'S1', 'stok_total' => 100, 'stok_baik' => 100, 'stok_rusak' => 0]);

        $a = Acara::create(['nama_acara' => 'Batal', 'tanggal' => '2026-01-01', 'status' => 'aktif']);
        app(CheckoutService::class)->buat($a, [['barang_id' => $b->id, 'jumlah' => 30]]);
        $this->post("/acara/{$a->id}/batalkan")->assertRedirect();

        $res = $this->get('/')->assertOk();

        $this->assertSame(0, (int) $res->viewData('totalKeluar'), 'acara batal tidak dihitung');
    }

    public function test_ringkasan_normal_tanpa_undo(): void
    {
        $b = Barang::create(['nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'S1', 'stok_total' => 100, 'stok_baik' => 100, 'stok_rusak' => 0]);
        $a = Acara::create(['nama_acara' => 'Konser', 'tanggal' => '2026-01-01', 'status' => 'aktif']);
        app(CheckoutService::class)->buat($a, [['barang_id' => $b->id, 'jumlah' => 40]]);

        $p = Pengembalian::create(['tanggal_pulang' => '2026-01-02', 'dicatat_oleh' => 'P']);
        $p->acara()->attach($a->id);
        $p->detailPengembalian()->create(['barang_id' => $b->id, 'jumlah_kembali' => 38]);
        app(PengembalianService::class)->hitung($p);

        $res = $this->get('/')->assertOk();

        $this->assertSame(40, (int) $res->viewData('totalKeluar'));
        $this->assertSame(38, (int) $res->viewData('totalKembali'));
    }
}
