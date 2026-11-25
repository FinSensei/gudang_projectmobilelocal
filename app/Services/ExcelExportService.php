<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\Acara;
use App\Models\DetailAcara;
use App\Models\LogAktivitas;
use App\Models\Pengembalian;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class ExcelExportService
{
    private const HEADER_FILL = 'FF1F6FEB';   // biru
    private const HEADER_TEXT = 'FFFFFFFF';
    private const ALT_FILL    = 'FFF3F6FB';   // abu sangat muda
    private const LINE        = 'FFD1D5DB';

        /**
     * Peta barang_id => total jumlah keluar di acara aktif.
     * Dihitung sekali (2 query) supaya tidak N+1 saat loop ratusan barang.
     */
    private function keluarMap(): array
    {
        $aktifIds = Acara::where('status', 'aktif')->pluck('id');

        return DetailAcara::whereIn('acara_id', $aktifIds)
            ->selectRaw('barang_id, SUM(jumlah_keluar) as total')
            ->groupBy('barang_id')
            ->pluck('total', 'barang_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function base(): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        return $ss;
    }

    /** Header tebal + biru + teks putih + border */
    private function styleHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB(self::HEADER_TEXT);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::LINE);
    }

    /** Baris bergantian + border tipis */
    private function styleBody(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $fromRow, int $toRow, int $cols): void
    {
        if ($toRow < $fromRow) return;
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cols);

        $sheet->getStyle("A{$fromRow}:{$lastCol}{$toRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::LINE);

        for ($r = $fromRow; $r <= $toRow; $r++) {
            if (($r - $fromRow) % 2 === 1) {
                $sheet->getStyle("A{$r}:{$lastCol}{$r}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ALT_FILL);
            }
        }
    }

    /**
     * Rekap pengembalian — format per acara (image c106b4):
     *   Nama Pemesan / Lokasi & Tanggal sebagai header blok,
     *   lalu tabel: Nama Barang | Keterangan | Stok Awal | Jumlah keluar | Jumlah Kembali | Stok Akhir | keterangan
     */
    public function riwayat($pengembalian = null): Xlsx
    {
        $ss = $this->base();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Rekap Pengembalian');

        $sheet->fromArray(['Rekap Pengembalian Barang'], null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true)->setSize(14);
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = 3;
        $pengembalian = $pengembalian ?? Pengembalian::with(['acara', 'detailPengembalian.barang'])
            ->orderByDesc('id')->get();

        if ($pengembalian->isEmpty()) {
            $sheet->fromArray(['Belum ada pengembalian.'], null, 'A3');
            return new Xlsx($ss);
        }

        foreach ($pengembalian as $p) {
            // Blok identitas acara
            $sheet->setCellValue('A'.$row, 'Nama Pemesan');
            $sheet->setCellValue('B'.$row, $p->acara->pluck('nama_acara')->join(', '));
            $row++;

            $sheet->setCellValue('A'.$row, 'Lokasi & Tanggal');
            $tanggal = $p->tanggal_pulang?->format('d F Y') ?: '-';
            $sheet->setCellValue('B'.$row, $tanggal);
            $row++;

            // Header tabel blok
            $headerRow = $row;
            $sheet->fromArray([
                'Nama Barang', 'Keterangan', 'Stok Awal',
                'Jumlah keluar', 'Jumlah Kembali', 'Stok Akhir', 'Stok hilang',
            ], null, 'A'.$row);
            $this->styleHeader($sheet, "A{$row}:G{$row}");
            $row++;

            // Isi per barang
            $dataStart = $row;
            $rows = $p->detailRows();
            foreach ($rows as $r) {
                $stokAkhir = $r['tersedia'];
                $sheet->fromArray([
                    $r['barang'],
                    '',                                  // keterangan (opsional)
                    $r['keluar'] + $r['kembali'],        // stok awal = keluar + kembali
                    $r['keluar'],
                    $r['kembali'],
                    $stokAkhir,
                    (int) $r['hilang'],
                ], null, 'A'.$row);
                $row++;
            }
            $this->styleBody($sheet, $dataStart, $row - 1, 7);

            // total blok
            $sheet->setCellValue('F'.$row, 'Total hilang');
            $sheet->setCellValue('G'.$row, (int) collect($rows)->sum('hilang'));
            $sheet->getStyle("F{$row}:G{$row}")->getFont()->setBold(true);
            $row += 2; // jarak antar blok
        }

        for ($c = 'A'; $c <= 'G'; $c++) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        return new Xlsx($ss);
    }

    /**
     * Stok barang — format image a9719c:
     *   No | Kode Barang | Nama Barang | Photo | Jumlah | Ukuran | Keterangan
     */

    /**
     * Export log aktivitas: 100 terbaru dengan waktu lengkap (tanggal + jam).
     */
    public function log(): Xlsx
    {
        $ss = $this->base();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Log Aktivitas');

        $sheet->fromArray(['Log Aktivitas — 100 Terbaru'], null, 'A1');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true)->setSize(14);
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headerRow = 3;
        $sheet->fromArray(['No', 'Waktu', 'Aksi', 'Entitas', 'Keterangan'], null, 'A'.$headerRow);
        $this->styleHeader($sheet, "A{$headerRow}:E{$headerRow}");

        $row = $headerRow + 1;
        $no  = 0;

        LogAktivitas::orderByDesc('id')
            ->limit(LogAktivitas::MAX_ROWS)
            ->get()
            ->each(function ($l) use ($sheet, &$row, &$no) {
                $no++;
                $sheet->fromArray([
                    $no,
                    $l->created_at?->format('Y-m-d H:i:s') ?: '-',
                    $l->aksi,
                    $l->entitas,
                    $l->keterangan,
                ], null, 'A'.$row);
                $row++;
            });

        $this->styleBody($sheet, $headerRow + 1, $row - 1, 5);

        // kolom Waktu rata tengah, angka No rata kanan
        $sheet->getStyle('A'.($headerRow+1).':A'.($row-1))
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        for ($c = 'A'; $c <= 'E'; $c++) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        return new Xlsx($ss);
    }

    public function stok(): Xlsx
    {
        $ss = $this->base();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Stok Barang');

        $sheet->fromArray(['Data Stok Barang'], null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true)->setSize(14);
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headerRow = 3;
        $sheet->fromArray([
            'No', 'Kode Barang', 'Nama Barang', 'Photo',
            'Total Stok', 'Stok Baik', 'Rusak Ringan', 'Rusak Berat', 'Keterangan',
        ], null, 'A'.$headerRow);
        $this->styleHeader($sheet, "A{$headerRow}:I{$headerRow}");

        $row = $headerRow + 1;
        $no = 0;
        $keluarMap = $this->keluarMap();
        Barang::orderBy('nama_barang')->get()->each(function ($b) use ($sheet, &$row, &$no, $keluarMap) {
            $no++;
            $keluar = (int) ($keluarMap[$b->id] ?? 0);
            $ket = [];
            if ($keluar > 0) $ket[] = "keluar {$keluar}";
            if ($b->statusStok() !== 'aman') $ket[] = $b->statusStok();

            $sheet->fromArray([
                $no,
                $b->kode_qr,
                $b->nama_barang,
                $b->foto ? basename($b->foto) : 'N/A',
                $b->stok_baik + $b->stok_rusak_ringan + $b->stok_rusak_berat,  // Total Stok
                $b->stok_baik,
                $b->stok_rusak_ringan,
                $b->stok_rusak_berat,
                $ket ? implode(', ', $ket) : '',
            ], null, 'A'.$row);
            $row++;
        });

        $this->styleBody($sheet, $headerRow + 1, $row - 1, 9);

        // kolom angka rata kanan
        $sheet->getStyle('E'.($headerRow+1).':G'.($row-1))
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        for ($c = 'A'; $c <= 'I'; $c++) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        return new Xlsx($ss);
    }
}