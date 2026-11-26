<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use App\Models\LogAktivitas;
use App\Services\StokService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RestockController extends Controller
{
    public function form()
    {
        $barang = Barang::orderBy('nama_barang')->get();

        return view('gudang.restock', compact('barang'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'barang_id' => 'nullable|exists:barang,id',
            'nama_baru' => 'nullable|string|max:100',
            'jumlah' => 'required|integer|min:1',
        ]);

        if (! empty($data['barang_id'])) {
            $b = Barang::findOrFail($data['barang_id']);
            $b->stok_total += $data['jumlah'];
            $b->stok_baik += $data['jumlah'];
            $b->save();
            LogAktivitas::create(['aksi' => 'restock', 'entitas' => 'barang', 'keterangan' => "{$b->nama_barang} +{$data['jumlah']}"]);

            return back()->with('ok', "Stok {$b->nama_barang} +{$data['jumlah']}");
        }

        if (! empty($data['nama_baru'])) {
            $b = Barang::create([
                'nama_barang' => $data['nama_baru'],
                'satuan' => 'pcs',
                'kode_qr' => 'QR-'.strtoupper(Str::random(8)),
                'stok_total' => $data['jumlah'],
                'stok_baik' => $data['jumlah'],
                'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
            ]);
            LogAktivitas::create(['aksi' => 'barang_baru', 'entitas' => 'barang', 'keterangan' => "{$b->nama_barang} +{$data['jumlah']}"]);

            return back()->with('ok', "Barang baru {$b->nama_barang} dibuat, stok {$data['jumlah']}");
        }

        return back()->with('err', 'Pilih barang atau isi nama barang baru.');
    }

    // ---------- Rusak / Hilang ----------
    public function rusak(Request $request)
    {
        $data = $request->validate([
            'barang_id' => 'required|exists:barang,id',
            'jumlah' => 'required|integer|min:1',
            'jenis' => 'required|in:rusak,hilang',
            'tingkat' => 'nullable|in:ringan,berat',
            'keterangan' => 'nullable|string|max:255',
        ]);

        $b = Barang::findOrFail($data['barang_id']);

        $dipesan = app(StokService::class)->jumlahDipesan($b->id);

        // Barang yang sudah dipesan di reservasi tidak boleh ditandai hilang —
        // nanti reservasinya tidak bisa dipenuhi.
        if ($data['jenis'] === 'hilang' && $dipesan > 0) {
            return back()
                ->withErrors(['jumlah' => "{$b->nama_barang} sedang dipesan/dipakai {$dipesan}. Tidak bisa ditandai hilang."])
                ->withInput();
        }

        // Rusak juga mengurangi stok baik: jangan sampai sisa baik kurang dari
        // yang sudah dipesan di reservasi.
        $sisaSetelahRusak = (int) $b->stok_baik - (int) $data['jumlah'];
        if ($data['jenis'] === 'rusak' && $sisaSetelahRusak < $dipesan) {
            return back()
                ->withErrors(['jumlah' => "{$b->nama_barang} masih dipesan {$dipesan}. Stok baik hanya {$b->stok_baik}, sisa setelah rusak {$sisaSetelahRusak}."])
                ->withInput();
        }

        if ($b->stok_baik < $data['jumlah']) {
            return back()
                ->withErrors(['jumlah' => "Stok baik {$b->nama_barang} hanya {$b->stok_baik}."])
                ->withInput();
        }

        // Rusak wajib pilih tingkat; hilang tidak perlu
        if ($data['jenis'] === 'rusak' && empty($data['tingkat'])) {
            return back()
                ->withErrors(['tingkat' => 'Pilih tingkat kerusakan (ringan atau berat).'])
                ->withInput();
        }

        $b->stok_baik -= $data['jumlah'];

        if ($data['jenis'] === 'hilang') {
            // Barang hilang: tidak ada di gudang, jadi total ikut turun
            $b->stok_total -= $data['jumlah'];
            $pesan = "{$b->nama_barang} ditandai hilang {$data['jumlah']}";
            $aksi = 'hilang';
        } elseif ($data['tingkat'] === 'berat') {
            $b->stok_rusak_berat += $data['jumlah'];
            $pesan = "{$b->nama_barang} rusak berat {$data['jumlah']}";
            $aksi = 'rusak';
        } else {
            $b->stok_rusak_ringan += $data['jumlah'];
            $pesan = "{$b->nama_barang} rusak ringan {$data['jumlah']}";
            $aksi = 'rusak';
        }

        $b->save();

        $catatan = trim((string) ($data['keterangan'] ?? ''));
        LogAktivitas::create([
            'aksi' => $aksi,
            'entitas' => 'barang',
            'keterangan' => $catatan !== '' ? "{$pesan} — {$catatan}" : $pesan,
        ]);

        return back()->with('ok', $pesan);
    }

    /**
     * Sinkronkan stok_total tiap barang dari komponennya:
     *   total = baik + rusak ringan + rusak berat + yang sedang keluar
     * Berguna kalau ada data lama yang tidak konsisten.
     */
    public function sinkron()
    {
        $aktifIds = Acara::where('status', 'aktif')->pluck('id');

        $keluarMap = DetailAcara::whereIn('acara_id', $aktifIds)
            ->selectRaw('barang_id, SUM(jumlah_keluar) as total')
            ->groupBy('barang_id')
            ->pluck('total', 'barang_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $diperbaiki = 0;

        Barang::orderBy('id')->get()->each(function ($b) use ($keluarMap, &$diperbaiki) {
            $keluar = (int) ($keluarMap[$b->id] ?? 0);
            $baru = (int) $b->stok_baik
                  + (int) $b->stok_rusak_ringan
                  + (int) $b->stok_rusak_berat
                  + $keluar;

            if ((int) $b->stok_total !== $baru) {
                $b->stok_total = $baru;
                $b->save();
                $diperbaiki++;
            }
        });

        LogAktivitas::create([
            'aksi' => 'sinkron_stok',
            'entitas' => 'barang',
            'keterangan' => $diperbaiki > 0 ? "{$diperbaiki} barang dibetulkan" : 'semua sudah konsisten',
        ]);

        $pesan = $diperbaiki > 0
            ? "{$diperbaiki} stok barang dibetulkan."
            : 'Semua stok sudah konsisten.';

        return back()->with('ok', $pesan);
    }
}
