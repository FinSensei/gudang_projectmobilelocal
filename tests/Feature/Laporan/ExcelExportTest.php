<?php

namespace Tests\Feature\Laporan;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\Pengembalian;
use Tests\TestCase;

class ExcelExportTest extends TestCase
{
    public function test_export_excel_menghasilkan_file_xlsx(): void
    {
        $b = Barang::create(['nama_barang' => 'Sendok', 'satuan' => 'pcs', 'ukuran' => '21 cm', 'kode_qr' => 'S1', 'stok_total' => 100, 'stok_baik' => 100, 'stok_rusak' => 0]);
        $a = Acara::create(['nama_acara' => 'Konser', 'tanggal' => '2026-01-01', 'status' => 'selesai']);
        $a->detailAcara()->create(['barang_id' => $b->id, 'jumlah_keluar' => 50]);
        $p = Pengembalian::create(['tanggal_pulang' => '2026-01-02', 'dicatat_oleh' => 'Petugas']);
        $p->acara()->attach($a->id);
        $p->detailPengembalian()->create(['barang_id' => $b->id, 'jumlah_kembali' => 48, 'jumlah_hilang' => 2]);

        $res = $this->get('/riwayat/excel');

        $res->assertOk();
        $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertGreaterThan(0, strlen($res->streamedContent() ?: $res->getContent()));
    }

    public function test_export_stok_excel_menghasilkan_file(): void
    {
        Barang::create(['nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'S2', 'stok_total' => 10, 'stok_baik' => 10, 'stok_rusak' => 0]);

        $res = $this->get('/stok/excel');

        $res->assertOk();
        $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
