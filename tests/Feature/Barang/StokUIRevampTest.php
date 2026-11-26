<?php

namespace Tests\Feature\Barang;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use App\Models\DetailPengembalian;
use App\Models\Pengembalian;
use Tests\TestCase;

class StokUIRevampTest extends TestCase
{
    private function b(string $kode, int $total, int $baik): Barang
    {
        return Barang::create([
            'nama_barang' => 'Sendok '.$kode, 'satuan' => 'pcs', 'kode_qr' => 'SR'.$kode,
            'stok_total' => $total, 'stok_baik' => $baik,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);
    }

    // ---------- 1. Nav "Tambah" dihapus ----------
    public function test_nav_tidak_lagi_punya_menu_tambah(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('>Tambah<', $html, 'menu Tambah harus hilang dari nav');
    }

    // ---------- 2. Tambah barang pindah ke halaman Stok ----------
    public function test_halaman_stok_punya_form_tambah_barang(): void
    {
        $this->b('1', 10, 10);

        $this->get('/restock')
            ->assertOk()
            ->assertSee('name="nama[]"', false)
            ->assertSee('name="ukuran[]"', false)
            ->assertSee('Tambah barang', false);
    }

    public function test_tambah_barang_dengan_ukuran_tersimpan(): void
    {
        $this->post('/barang', [
            'nama' => ['Sendok Baru'],
            'ukuran' => ['21 cm'],
            'jumlah' => [24],
        ])->assertRedirect();

        $b = Barang::where('nama_barang', 'Sendok Baru')->first();
        $this->assertNotNull($b, 'barang baru tersimpan');
        $this->assertSame('21 cm', $b->ukuran);
        $this->assertSame(24, (int) $b->stok_total);
    }

    // ---------- 3. Tandai rusak: pilih ringan / berat ----------
    public function test_tandai_rusak_punya_pilihan_ringan_dan_berat(): void
    {
        $this->b('1', 10, 10);

        $this->get('/restock')
            ->assertOk()
            ->assertSee('name="tingkat"', false)
            ->assertSee('Rusak ringan', false)
            ->assertSee('Rusak berat', false);
    }

    public function test_tandai_rusak_berat_masuk_ke_kolom_berat(): void
    {
        $b = $this->b('1', 10, 10);

        $this->post('/rusak', [
            'barang_id' => $b->id,
            'jumlah' => 3,
            'jenis' => 'rusak',
            'tingkat' => 'berat',
        ])->assertRedirect();

        $b->refresh();
        $this->assertSame(0, (int) $b->stok_rusak_ringan, 'ringan tetap 0');
        $this->assertSame(3, (int) $b->stok_rusak_berat, 'berat 3');
        $this->assertSame(7, (int) $b->stok_baik, 'baik 10 - 3 = 7');
    }

    public function test_tandai_rusak_ringan_masuk_ke_kolom_ringan(): void
    {
        $b = $this->b('1', 10, 10);

        $this->post('/rusak', [
            'barang_id' => $b->id,
            'jumlah' => 2,
            'jenis' => 'rusak',
            'tingkat' => 'ringan',
        ])->assertRedirect();

        $b->refresh();
        $this->assertSame(2, (int) $b->stok_rusak_ringan, 'ringan 2');
        $this->assertSame(0, (int) $b->stok_rusak_berat, 'berat tetap 0');
    }

    // ---------- 4. Ringkasan beranda ----------
    public function test_beranda_menampilkan_total_dimiliki_dan_total_hilang(): void
    {
        $a = $this->b('A', 100, 90);
        $b = $this->b('B', 50, 50);

        $this->get('/')
            ->assertOk()
            ->assertSee('Total barang dimiliki', false)
            ->assertSee('Total barang hilang', false);
    }

    public function test_total_dimiliki_adalah_jumlah_stok_total(): void
    {
        $this->b('A', 100, 90);
        $this->b('B', 50, 50);

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('>150<', $html, '100 + 50 = 150 unit dimiliki');
    }

    public function test_total_hilang_dihitung_dari_pengembalian(): void
    {
        $b = $this->b('A', 100, 100);

        $a = Acara::create(['nama_acara' => 'Acara Hilang', 'tanggal' => '2025-06-10', 'status' => 'selesai', 'penanggung_jawab' => 'P']);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => 10]);

        $p = Pengembalian::create(['tanggal_pulang' => '2025-06-11', 'dicatat_oleh' => 'P', 'catatan' => null]);
        $p->acara()->attach($a->id);
        DetailPengembalian::create([
            'pengembalian_id' => $p->id, 'barang_id' => $b->id,
            'jumlah_kembali' => 8, 'jumlah_rusak_ringan' => 0, 'jumlah_rusak_berat' => 0, 'jumlah_hilang' => 2,
        ]);

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('Total barang hilang', $html);
        $this->assertStringContainsString('>2<', $html, '2 unit hilang tampil di ringkasan');
    }
}
