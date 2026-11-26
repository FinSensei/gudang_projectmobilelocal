<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use App\Models\DetailPengembalian;
use App\Models\Kategori;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Map barang_id => total jumlah keluar di acara aktif (1 query, bukan N+1) */
    private function keluarMap(): array
    {
        $aktifIds = Acara::where('status', 'aktif')->pluck('id')->all();
        if (! $aktifIds) {
            return [];
        }

        return DetailAcara::select('barang_id', DB::raw('SUM(jumlah_keluar) as total'))
            ->whereIn('acara_id', $aktifIds)
            ->groupBy('barang_id')
            ->pluck('total', 'barang_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function index(Request $request)
    {
        $q = $request->query('q');
        $kategoriId = $request->query('kategori');
        $barang = Barang::query()
            ->when($q, fn ($query) => $query->where('nama_barang', 'like', "%{$q}%"))
            ->when($kategoriId, fn ($query) => $query->where('kategori_id', $kategoriId))
            ->orderBy('nama_barang')->get();
        $acaraAktif = Acara::where('status', 'aktif')->orderByDesc('id')->get();
        $kategori = Kategori::orderBy('nama_kategori')->get();
        $lowStock = Barang::orderBy('stok_baik')->get()->filter(fn ($b) => $b->statusStok() !== 'aman')->values();

        // Ringkasan: hanya transaksi yang masih aktif
        // (pengembalian yang di-undo tidak dihitung, acara batal tidak dihitung)
        $totalHilang = DetailPengembalian::whereHas('pengembalian', fn ($q) => $q->whereNull('deleted_at'))
            ->sum('jumlah_hilang');
        $totalKembali = DetailPengembalian::whereHas('pengembalian', fn ($q) => $q->whereNull('deleted_at'))
            ->sum('jumlah_kembali');
        $totalKeluar = DetailAcara::whereHas('acara', fn ($q) => $q->where('status', '!=', 'batal'))
            ->sum('jumlah_keluar');

        // Total unit yang dimiliki gudang (baik + rusak, belum termasuk yang keluar)
        $totalDimiliki = (int) Barang::sum('stok_total');

        // Perlu perhatian: reservasi menunggu konfirmasi + stok habis
        $reservasiPending = Acara::with('detailAcara.barang')
            ->where('status', 'reservasi')->orderBy('tanggal')->get();
        $stokHabis = Barang::where('stok_baik', 0)->orderBy('nama_barang')->get();

        $keluarMap = $this->keluarMap();

        return view('gudang.dashboard', compact(
            'barang', 'acaraAktif', 'q', 'kategori', 'lowStock',
            'totalHilang', 'totalKembali', 'totalKeluar', 'totalDimiliki',
            'reservasiPending', 'stokHabis', 'keluarMap'
        ));
    }
}
