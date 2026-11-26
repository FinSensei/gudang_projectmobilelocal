<?php

namespace Tests\Feature\Laporan;

use App\Models\LogAktivitas;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class LogExportTest extends TestCase
{
    public function test_log_menyimpan_100_terbaru(): void
    {
        for ($i = 1; $i <= 130; $i++) {
            LogAktivitas::create([
                'aksi' => 'uji', 'entitas' => 'barang', 'keterangan' => "aktivitas ke-$i",
            ]);
        }

        $this->assertSame(100, LogAktivitas::count(), 'simpan 100 terbaru');
        $this->assertDatabaseMissing('log_aktivitas', ['keterangan' => 'aktivitas ke-1']);
        $this->assertDatabaseHas('log_aktivitas', ['keterangan' => 'aktivitas ke-130']);
    }

    public function test_halaman_log_menampilkan_100(): void
    {
        for ($i = 1; $i <= 120; $i++) {
            LogAktivitas::create([
                'aksi' => 'uji', 'entitas' => 'barang', 'keterangan' => "aktivitas ke-$i",
            ]);
        }

        $this->get('/log')->assertOk()->assertSee('100 aktivitas');
    }

    public function test_export_log_excel_valid(): void
    {
        LogAktivitas::create(['aksi' => 'login', 'entitas' => 'user', 'keterangan' => 'Admin']);

        $res = $this->get('/log/export')->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'log').'.xlsx';
        file_put_contents($path, $res->streamedContent());

        $rows = IOFactory::load($path)
            ->getActiveSheet()->toArray();
        @unlink($path);

        $teks = json_encode($rows);
        $this->assertStringContainsString('Waktu', $teks);
        $this->assertStringContainsString('Aksi', $teks);
        $this->assertStringContainsString('login', $teks);
    }

    public function test_export_log_memuat_waktu_lengkap_tanggal_dan_jam(): void
    {
        LogAktivitas::create(['aksi' => 'login', 'entitas' => 'user', 'keterangan' => 'Admin']);

        $res = $this->get('/log/export')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'log').'.xlsx';
        file_put_contents($path, $res->streamedContent());
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        @unlink($path);

        $teks = json_encode($rows);
        // ada tahun, bulan, jam, menit, detik
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $teks);
    }

    public function test_export_log_maksimal_100_baris(): void
    {
        for ($i = 1; $i <= 130; $i++) {
            LogAktivitas::create([
                'aksi' => 'uji', 'entitas' => 'barang', 'keterangan' => "aktivitas ke-$i",
            ]);
        }

        $res = $this->get('/log/export')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'log').'.xlsx';
        file_put_contents($path, $res->streamedContent());

        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        // 4 baris header + 100 baris data
        $this->assertLessThanOrEqual(106, $sheet->getHighestRow(), 'maks 100 data + header');
    }
}
