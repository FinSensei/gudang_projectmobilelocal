<?php

namespace Tests\Feature\Pengembalian;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use Tests\TestCase;

/**
 * Collapse pada halaman pengembalian: saat barang diklik,
 * form rusak ringan/berat/hilang + keterangan harus terbuka.
 */
class PengembalianCollapseBugTest extends TestCase
{
    public function test_retur_head_punya_onclick_toggle(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'PCB1',
            'stok_total' => 50, 'stok_baik' => 50,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);
        $a = Acara::create([
            'nama_acara' => 'Acara', 'tanggal' => '2026-06-10', 'status' => 'aktif',
            'penanggung_jawab' => 'P',
        ]);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => 10]);

        $this->get('/pengembalian')->assertOk()->assertSee('onclick="toggleRetur', false);
    }

    public function test_toggle_retur_fungsi_di_scope_global(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'PCB2',
            'stok_total' => 50, 'stok_baik' => 50,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);
        $a = Acara::create([
            'nama_acara' => 'Acara', 'tanggal' => '2026-06-10', 'status' => 'aktif',
            'penanggung_jawab' => 'P',
        ]);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => 10]);

        $html = $this->get('/pengembalian')->assertOk()->getContent();

        // Fungsi toggleRetur harus diakses dari window (global), bukan didalam IIFE
        $this->assertStringContainsString('function toggleRetur', $html, 'toggleRetur harus ada sebagai function global (bukan dalam IIFE)');
    }

    public function test_retur_body_tersembunyi_default(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'PCB3',
            'stok_total' => 50, 'stok_baik' => 50,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);
        $a = Acara::create([
            'nama_acara' => 'Acara', 'tanggal' => '2026-06-10', 'status' => 'aktif',
            'penanggung_jawab' => 'P',
        ]);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => 10]);

        $this->get('/pengembalian')->assertOk()->assertSee('display:none', false);
    }
}