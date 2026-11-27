<?php

namespace Tests\Feature\Pengembalian;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use Tests\TestCase;

class PengembalianCollapseTest extends TestCase
{
    public function test_retur_punya_header_dengan_toggle(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'PC1',
            'stok_total' => 50, 'stok_baik' => 50,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);
        $a = Acara::create([
            'nama_acara' => 'Acara', 'tanggal' => '2026-06-10', 'status' => 'aktif',
            'penanggung_jawab' => 'P',
        ]);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => 10]);

        $this->get('/pengembalian')->assertOk()->assertSee('retur-head', false);
    }

    public function test_input_rusak_hilang_tersembunyi_di_retur_body(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'PC2',
            'stok_total' => 50, 'stok_baik' => 50,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);
        $a = Acara::create([
            'nama_acara' => 'Acara', 'tanggal' => '2026-06-10', 'status' => 'aktif',
            'penanggung_jawab' => 'P',
        ]);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => 10]);

        $html = $this->get('/pengembalian')->assertOk()->getContent();

        $this->assertStringContainsString('retur-body', $html, 'harus ada retur-body');
        $this->assertStringContainsString('display:none', $html, 'retur-body harus hidden default');
        $this->assertStringContainsString('name="rusak_ringan', $html, 'input rusak_ringan harus ada');
    }

    public function test_nilai_baik_ditampilkan_di_header(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'PC3',
            'stok_total' => 50, 'stok_baik' => 50,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);
        $a = Acara::create([
            'nama_acara' => 'Acara', 'tanggal' => '2026-06-10', 'status' => 'aktif',
            'penanggung_jawab' => 'P',
        ]);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => 10]);

        $this->get('/pengembalian')->assertOk()->assertSee('Baik', false);
    }

    public function test_toggle_span_ada_di_header(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'PC4',
            'stok_total' => 50, 'stok_baik' => 50,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);
        $a = Acara::create([
            'nama_acara' => 'Acara', 'tanggal' => '2026-06-10', 'status' => 'aktif',
            'penanggung_jawab' => 'P',
        ]);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => 10]);

        $this->get('/pengembalian')->assertOk()->assertSee('retur-toggle', false);
    }
}