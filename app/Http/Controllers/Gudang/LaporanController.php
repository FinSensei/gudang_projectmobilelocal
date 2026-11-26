<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\Acara;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LogAktivitas;
use App\Models\Pengembalian;
use App\Services\ExcelExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    // ---------- Kategori ----------
    public function kategoriIndex()
    {
        $kategori = Kategori::orderBy('nama_kategori')->get();

        return view('gudang.kategori', compact('kategori'));
    }

    public function kategoriStore(Request $request)
    {
        $data = $request->validate(['nama_kategori' => 'required|string|max:100', 'deskripsi' => 'nullable|string']);
        Kategori::create($data);

        return back()->with('ok', 'Kategori ditambahkan.');
    }

    public function kategoriUpdate(Request $request, $id)
    {
        $k = Kategori::findOrFail($id);
        $data = $request->validate(['nama_kategori' => 'required|string|max:100', 'deskripsi' => 'nullable|string']);
        $k->update($data);

        return back()->with('ok', 'Kategori diperbarui.');
    }

    public function kategoriDestroy($id)
    {
        $k = Kategori::findOrFail($id);

        // Kategori yang masih dipakai barang tidak boleh dihapus
        if (Barang::where('kategori_id', $k->id)->exists()) {
            return back()->with('err', "Kategori {$k->nama_kategori} masih dipakai barang, tidak bisa dihapus.");
        }

        $k->delete();

        return back()->with('ok', "Kategori {$k->nama_kategori} dihapus.");
    }

    // ---------- Riwayat & Log ----------
    /**
     * Terapkan filter laporan: tanggal (dari/sampai) dan/atau satu acara.
     */
    private function applyTanggalFilter($query, Request $request)
    {
        $dari = $request->input('dari');
        $sampai = $request->input('sampai');
        $acaraId = $request->input('acara_id');

        if ($dari) {
            $query->whereDate('tanggal_pulang', '>=', $dari);
        }
        if ($sampai) {
            $query->whereDate('tanggal_pulang', '<=', $sampai);
        }
        if ($acaraId) {
            $query->whereHas('acara', fn ($sub) => $sub->where('acara.id', $acaraId));
        }

        return $query;
    }

    public function riwayat(Request $request)
    {
        $q = $request->query('q');

        $pengembalian = $this->applyTanggalFilter(
            Pengembalian::with(['acara', 'detailPengembalian.barang'])
                ->when($q, function ($query) use ($q) {
                    $query->whereHas('acara', fn ($sub) => $sub->where('nama_acara', 'like', "%{$q}%"))
                        ->orWhereHas('detailPengembalian.barang', fn ($sub) => $sub->where('nama_barang', 'like', "%{$q}%"));
                })
                ->orderByDesc('id'),
            $request
        )->get();

        // Rekap dari detail pengembalian (jumlah_keluar TIDAK ada di tabel ini,
        // jadi total keluar tidak dihitung di sini — lihat halaman Proses).
        $totalKembali = 0;
        $totalHilang = 0;
        foreach ($pengembalian as $p) {
            foreach ($p->detailPengembalian as $d) {
                $totalKembali += $d->jumlah_kembali;
                $totalHilang += $d->jumlah_hilang;
            }
        }

        $dari = $request->input('dari');
        $sampai = $request->input('sampai');

        return view('gudang.riwayat', compact(
            'pengembalian', 'q', 'totalKembali', 'totalHilang', 'dari', 'sampai'
        ));
    }

    public function riwayatDetail($id)
    {
        $p = Pengembalian::with(['acara', 'detailPengembalian.barang'])->findOrFail($id);

        return view('gudang.riwayat_detail', compact('p'));
    }

    public function riwayatPdf(Request $request)
    {
        $pengembalian = $this->applyTanggalFilter(
            Pengembalian::with(['acara', 'detailPengembalian.barang'])->orderByDesc('id'),
            $request
        )->get();
        $pdf = Pdf::loadView('gudang.pdf', compact('pengembalian'));

        return $pdf->download('rekap-pengembalian.pdf');
    }

    public function riwayatExcel(Request $request)
    {
        $pengembalian = $this->applyTanggalFilter(
            Pengembalian::with(['acara', 'detailPengembalian.barang'])->orderByDesc('id'),
            $request
        )->get();

        $xlsx = app(ExcelExportService::class)->riwayat($pengembalian);
        $filename = 'rekap-pengembalian-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($xlsx) {
            $xlsx->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function stokExcel()
    {
        $xlsx = app(ExcelExportService::class)->stok();
        $filename = 'stok-barang-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($xlsx) {
            $xlsx->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function log()
    {
        $log = LogAktivitas::orderByDesc('id')->limit(LogAktivitas::MAX_ROWS)->get();

        return view('gudang.log', compact('log'));
    }

    /** Export log aktivitas (100 terbaru) ke Excel. */
    public function logExport()
    {
        $xlsx = app(ExcelExportService::class)->log();
        $filename = 'log-aktivitas-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($xlsx) {
            $xlsx->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
