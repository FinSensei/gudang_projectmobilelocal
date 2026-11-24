<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\User;

/**
 * Simulasi celah & kendala operasional.
 */
class SimulasiCelahTest extends TestCase
{
    private function admin(): User
    {
        return User::create(['name'=>'A','email'=>'a@x.id','password'=>bcrypt('x'),'role'=>'admin']);
    }

    // ---------- 1. Kategori terhapus -> barang jadi tanpa kategori (aman?) ----------
    public function test_barang_tetap_ada_setelah_kategori_dihapus(): void
    {
        $this->actingAs($this->admin());

        $k = \App\Models\Kategori::create(['nama_kategori'=>'Makan']);
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'K1','kategori_id'=>$k->id,'stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0]);

        // kategori dipakai -> tidak bisa dihapus
        $this->delete("/kategori/{$k->id}")->assertRedirect();
        $this->assertNotNull($k->fresh());
        $this->assertSame($k->id, $b->fresh()->kategori_id);
    }

    // ---------- 2. Barang di-soft-delete tidak muncul di scan QR ----------
    public function test_barang_terhapus_tidak_bisa_di_scan(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'DEL1','stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0]);
        $b->delete();

        $this->get('/b/DEL1')->assertNotFound();
    }

    // ---------- 3. Nama barang duplikat (datalist) ----------
    public function test_duplikat_nama_barang_tidak_bikin_crash(): void
    {
        Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'D1','stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0]);
        Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'D2','stok_total'=>20,'stok_baik'=>20,'stok_rusak'=>0]);

        $this->get('/')->assertOk();
        $this->get('/barang')->assertOk();
    }

    // ---------- 4. Stok 0 ----------
    public function test_barang_stok_nol_tampil_habis(): void
    {
        Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'Z1','stok_total'=>10,'stok_baik'=>0,'stok_rusak'=>0]);

        $b = Barang::first();
        $this->assertSame('habis', $b->statusStok());
        $this->get('/')->assertOk()->assertSee('habis');
    }

    // ---------- 5. Acara tanpa barang (kosong) ----------
    public function test_acara_tanpa_barang_ditolak(): void
    {
        $this->post('/acara', [
            'nama_acara' => 'Kosong',
            'items' => [],
        ])->assertSessionHasErrors('items');
    }

    // ---------- 6. Reservasi > stok : boleh dibuat, tapi KONFIRMASI ditolak ----------
    public function test_checkout_melebihi_stok_rollback(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'C1','stok_total'=>10,'stok_baik'=>10,'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0]);

        // Reservasi di atas stok baik ditolak sejak awal — tidak ada gunanya
        // memesan barang yang jelas tidak ada.
        $this->post('/acara', [
            'nama_acara' => 'Berlebih',
            'items' => [['barang_id'=>$b->id, 'jumlah'=>50]],
        ])->assertSessionHasErrors('items');

        $b->refresh();
        $this->assertSame(10, $b->stok_baik, 'stok TIDAK berubah');
        $this->assertSame(0, \App\Models\Acara::count(), 'reservasi tidak dibuat');
    }

    // ---------- 7. Restock barang yang tidak ada ----------
    public function test_restock_tanpa_pilihan_ditolak(): void
    {
        // Tidak pilih barang & tidak isi nama baru -> ditolak (flash 'err')
        $this->post('/restock', ['jumlah'=>10])->assertRedirect();
        $this->assertSame(0, Barang::count(), 'tidak ada barang dibuat');
    }

    // ---------- 8. Scan kode acak ----------
    public function test_scan_kode_acak_404(): void
    {
        $this->actingAs($this->admin());
        $this->get('/b/TIDAK-ADA-KODONYA')->assertNotFound();
    }
}