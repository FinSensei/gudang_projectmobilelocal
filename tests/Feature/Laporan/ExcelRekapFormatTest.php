<?php

namespace Tests\Feature\Laporan;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\Pengembalian;
use App\Services\ExcelExportService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExcelRekapFormatTest extends TestCase
{
    private function loadRows(): array
    {
        $xlsx = app(ExcelExportService::class)->riwayat();
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        $xlsx->save($path);
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        @unlink($path);

        return $rows;
    }

    protected function seedData(): void
    {
        $b = Barang::create(['nama_barang' => 'Pemanas air', 'satuan' => 'pcs', 'kode_qr' => 'P1', 'stok_total' => 50, 'stok_baik' => 50, 'stok_rusak' => 0]);
        $a = Acara::create(['nama_acara' => 'Musrembang', 'penanggung_jawab' => 'Ibu Peni', 'tanggal' => '2026-11-13', 'status' => 'selesai']);
        $a->detailAcara()->create(['barang_id' => $b->id, 'jumlah_keluar' => 10]);
        $p = Pengembalian::create(['tanggal_pulang' => '2026-11-14', 'dicatat_oleh' => 'Petugas']);
        $p->acara()->attach($a->id);
        $p->detailPengembalian()->create(['barang_id' => $b->id, 'jumlah_kembali' => 8, 'jumlah_hilang' => 2]);
        $b->update(['stok_baik' => 58, 'stok_total' => 48]);
    }

    public function test_rekap_memuat_identitas_acara(): void
    {
        $this->seedData();

        $rows = $this->loadRows();
        $flat = implode("\n", array_map(fn ($r) => implode(' | ', array_map(fn ($v) => (string) $v, $r)), $rows));

        $this->assertStringContainsString('Nama Pemesan', $flat);
        $this->assertStringContainsString('Lokasi & Tanggal', $flat);
        $this->assertStringContainsString('Musrembang', $flat);
    }

    public function test_rekap_punya_kolom_stok_awal_keluar_kembali_akhir(): void
    {
        $this->seedData();

        $rows = $this->loadRows();
        $flat = implode("\n", array_map(fn ($r) => implode(' | ', array_map(fn ($v) => (string) $v, $r)), $rows));

        $this->assertStringContainsString('Stok Awal', $flat);
        $this->assertStringContainsString('Jumlah keluar', $flat);
        $this->assertStringContainsString('Jumlah Kembali', $flat);
        $this->assertStringContainsString('Stok Akhir', $flat);
    }

    public function test_rekap_menampilkan_nama_barang_dan_hilang(): void
    {
        $this->seedData();

        $rows = $this->loadRows();
        $flat = implode("\n", array_map(fn ($r) => implode(' | ', array_map(fn ($v) => (string) $v, $r)), $rows));

        $this->assertStringContainsString('Pemanas air', $flat);
        $this->assertStringContainsString('Stok hilang', $flat, 'header kolom terakhir harus Stok hilang');
        $this->assertStringContainsString('Pemanas air', $flat);
    }

    public function test_rekap_tanpa_data_tetap_valid(): void
    {
        $res = $this->get('/riwayat/excel');
        $res->assertOk();
        $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
