<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Acara;
use App\Models\DetailAcara;
use App\Models\Pengembalian;
use App\Models\DetailPengembalian;
use App\Models\LogAktivitas;
use App\Services\PengembalianService;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GudangController extends Controller
{
    // ---------- Dashboard ----------
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
        return view('gudang.dashboard', compact('barang', 'acaraAktif', 'q', 'kategori', 'lowStock'));
    }

    // ---------- Restock ----------
    public function restockForm()
    {
        $barang = Barang::orderBy('nama_barang')->get();
        return view('gudang.restock', compact('barang'));
    }

    public function restockStore(Request $request)
    {
        $data = $request->validate([
            'barang_id' => 'nullable|exists:barang,id',
            'nama_baru' => 'nullable|string|max:100',
            'jumlah'    => 'required|integer|min:1',
        ]);

        if (!empty($data['barang_id'])) {
            $b = Barang::findOrFail($data['barang_id']);
            $b->stok_total += $data['jumlah'];
            $b->stok_baik  += $data['jumlah'];
            $b->save();
            LogAktivitas::create(['aksi'=>'restock','entitas'=>'barang','keterangan'=>"{$b->nama_barang} +{$data['jumlah']}"]);
            return back()->with('ok', "Stok {$b->nama_barang} +{$data['jumlah']}");
        }

        if (!empty($data['nama_baru'])) {
            $b = Barang::create([
                'nama_barang' => $data['nama_baru'],
                'satuan'      => 'pcs',
                'kode_qr'     => 'QR-'.strtoupper(Str::random(8)),
                'stok_total'  => $data['jumlah'],
                'stok_baik'   => $data['jumlah'],
                'stok_rusak'  => 0,
            ]);
            LogAktivitas::create(['aksi'=>'barang_baru','entitas'=>'barang','keterangan'=>"{$b->nama_barang} +{$data['jumlah']}"]);
            return back()->with('ok', "Barang baru {$b->nama_barang} dibuat, stok {$data['jumlah']}");
        }

        return back()->with('err', 'Pilih barang atau isi nama barang baru.');
    }

    // ---------- Rusak ----------
    public function rusakStore(Request $request)
    {
        $data = $request->validate([
            'barang_id' => 'required|exists:barang,id',
            'jumlah'    => 'required|integer|min:1',
        ]);
        $b = Barang::findOrFail($data['barang_id']);
        if ($b->stok_baik < $data['jumlah']) {
            return back()->with('err', "Stok baik {$b->nama_barang} hanya {$b->stok_baik}.");
        }
        $b->stok_baik -= $data['jumlah'];
        $b->stok_rusak += $data['jumlah'];
        $b->save();
        LogAktivitas::create(['aksi'=>'rusak','entitas'=>'barang','keterangan'=>"{$b->nama_barang} rusak +{$data['jumlah']}"]);
        return back()->with('ok', "{$b->nama_barang} ditandai rusak {$data['jumlah']}");
    }

    // ---------- Acara (keluar) ----------
    public function acaraForm()
    {
        $barang = Barang::where('stok_baik','>',0)->orderBy('nama_barang')->get();
        return view('gudang.acara', compact('barang'));
    }

    public function acaraStore(Request $request)
    {
        $data = $request->validate([
            'nama_acara'   => 'required|string|max:100',
            'penanggung_jawab' => 'nullable|string|max:100',
            'tanggal'      => 'nullable|date',
            'items'        => 'required|array|min:1',
            'items.*.barang_id' => 'required|exists:barang,id',
            'items.*.jumlah'    => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () use ($data) {
                $acara = Acara::create([
                    'nama_acara'       => $data['nama_acara'],
                    'penanggung_jawab' => $data['penanggung_jawab'] ?? null,
                    'tanggal'          => $data['tanggal'] ?? now(),
                    'status'           => 'aktif',
                ]);
                app(CheckoutService::class)->buat($acara, $data['items']);
                LogAktivitas::create(['aksi'=>'acara_keluar','entitas'=>'acara','keterangan'=>$acara->nama_acara]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('err', $e->getMessage())->withInput();
        }

        return redirect('/')->with('ok', "Acara {$data['nama_acara']} dibuat, barang keluar.");
    }

    // ---------- Pengembalian ----------
    public function pengembalianForm()
    {
        $aktif = Acara::where('status','aktif')->with('detailAcara.barang')->orderByDesc('id')->get();
        return view('gudang.pengembalian', compact('aktif'));
    }

    // preview (langkah 1): hitung dulu, belum simpan
    public function pengembalianPreview(Request $request)
    {
        $data = $request->validate([
            'acara_ids'   => 'required|array|min:1',
            'acara_ids.*' => 'exists:acara,id',
            'kembali'     => 'required|array',
            'kembali.*'   => 'integer|min:0',
        ]);

        $acaraIds = $data['acara_ids'];
        $outMap = [];
        DetailAcara::whereIn('acara_id', $acaraIds)->get()->each(function($d) use (&$outMap){
            $outMap[$d->barang_id] = ($outMap[$d->barang_id] ?? 0) + $d->jumlah_keluar;
        });

        $rows = [];
        foreach ($outMap as $bid => $out) {
            $b = Barang::find($bid);
            $kembali = (int)($data['kembali'][$bid] ?? 0);
            $rows[] = [
                'barang_id'    => $bid,
                'nama'         => $b->nama_barang,
                'keluar'       => $out,
                'kembali'      => $kembali,
                'hilang'       => max(0, $out - $kembali),
            ];
        }
        $totalHilang = array_sum(array_column($rows,'hilang'));

        return view('gudang.pengembalian_review', [
            'acara_ids'   => $acaraIds,
            'rows'        => $rows,
            'totalHilang' => $totalHilang,
            'tanggal'     => $data['tanggal'] ?? now()->toDateString(),
        ]);
    }

    // simpan (langkah 2): panggil service
    public function pengembalianStore(Request $request)
    {
        $data = $request->validate([
            'acara_ids'   => 'required|array|min:1',
            'acara_ids.*' => 'exists:acara,id',
            'kembali'     => 'required|array',
            'kembali.*'   => 'integer|min:0',
            'tanggal'     => 'nullable|date',
        ]);

        DB::transaction(function () use ($data) {
            $p = Pengembalian::create([
                'tanggal_pulang' => $data['tanggal'] ?? now()->toDateString(),
                'dicatat_oleh'   => 'operator',
            ]);
            $p->acara()->attach($data['acara_ids']);
            $outMap = [];
            DetailAcara::whereIn('acara_id', $data['acara_ids'])->get()->each(function ($d) use (&$outMap) {
                $outMap[$d->barang_id] = ($outMap[$d->barang_id] ?? 0) + $d->jumlah_keluar;
            });
            foreach ($outMap as $bid => $out) {
                DetailPengembalian::create([
                    'pengembalian_id' => $p->id,
                    'barang_id'       => $bid,
                    'jumlah_kembali'  => (int) ($data['kembali'][$bid] ?? 0),
                ]);
            }
            app(PengembalianService::class)->hitung($p);
            LogAktivitas::create(['aksi'=>'pengembalian','entitas'=>'pengembalian','keterangan'=>'batch #'.$p->id]);
        });

        return redirect('/')->with('ok', 'Pengembalian disimpan, stok diperbarui.');
    }

    // ---------- Tambah barang (cepat, borongan) ----------
    public function barangForm()
    {
        return view('gudang.barang');
    }

    public function barangStore(Request $request)
    {
        $data = $request->validate([
            'nama'    => 'required|array|min:1',
            'nama.*'  => 'required|string|max:100',
            'jumlah'  => 'required|array',
            'jumlah.*'=> 'required|integer|min:0',
            'kategori_id' => 'nullable|exists:kategori,id',
        ]);

        $n = count($data['nama']);
        DB::transaction(function () use ($data) {
            foreach ($data['nama'] as $i => $nama) {
                $barang = Barang::create([
                    'nama_barang' => $nama,
                    'kategori_id' => $data['kategori_id'] ?? null,
                    'satuan'      => 'pcs',
                    'kode_qr'     => 'QR-'.strtoupper(Str::random(8)),
                    'stok_total'  => (int) $data['jumlah'][$i],
                    'stok_baik'   => (int) $data['jumlah'][$i],
                    'stok_rusak'  => 0,
                ]);
                LogAktivitas::create(['aksi'=>'barang_baru','entitas'=>'barang','keterangan'=>$barang->nama_barang]);
            }
        });

        return redirect('/barang')->with('ok', "{$n} barang ditambahkan.");
    }

    // ---------- Edit / Hapus barang ----------
    public function barangEdit($id)
    {
        $barang = Barang::findOrFail($id);
        $kategori = Kategori::orderBy('nama_kategori')->get();
        return view('gudang.barang_edit', compact('barang', 'kategori'));
    }

    public function barangUpdate(Request $request, $id)
    {
        $barang = Barang::findOrFail($id);
        $data = $request->validate([
            'nama_barang' => 'required|string|max:100',
            'satuan'      => 'required|string|max:20',
            'lokasi'      => 'nullable|string|max:100',
            'kategori_id' => 'nullable|exists:kategori,id',
        ]);
        $barang->update($data);
        LogAktivitas::create(['aksi'=>'edit_barang','entitas'=>'barang','keterangan'=>$barang->nama_barang]);
        return redirect('/')->with('ok', 'Barang diperbarui.');
    }

    public function barangKurangi(Request $request, $id)
    {
        $request->validate(['jumlah' => 'required|integer|min:1']);
        $barang = Barang::findOrFail($id);
        if ($barang->stok_baik < $request->jumlah) {
            return back()->with('err', "Stok baik {$barang->nama_barang} hanya {$barang->stok_baik}.");
        }
        $barang->stok_baik -= $request->jumlah;
        $barang->stok_total -= $request->jumlah;
        $barang->save();
        LogAktivitas::create(['aksi'=>'kurangi','entitas'=>'barang','keterangan'=>"{$barang->nama_barang} -{$request->jumlah}"]);
        return back()->with('ok', "Stok {$barang->nama_barang} dikurangi {$request->jumlah}.");
    }

    public function barangTambah(Request $request, $id)
    {
        $request->validate(['jumlah' => 'required|integer|min:1']);
        $barang = Barang::findOrFail($id);
        $barang->stok_total += $request->jumlah;
        $barang->stok_baik += $request->jumlah;
        $barang->save();
        LogAktivitas::create(['aksi'=>'tambah_stok','entitas'=>'barang','keterangan'=>"{$barang->nama_barang} +{$request->jumlah}"]);
        return back()->with('ok', "Stok {$barang->nama_barang} +{$request->jumlah}.");
    }

    public function barangDestroy($id)
    {
        $barang = Barang::findOrFail($id);
        if ($barang->stokKeluar() > 0) {
            return back()->with('err', "Barang {$barang->nama_barang} sedang digunakan di acara aktif, tidak bisa dihapus.");
        }
        $nama = $barang->nama_barang;
        $barang->delete();
        LogAktivitas::create(['aksi'=>'hapus_barang','entitas'=>'barang','keterangan'=>$nama]);
        return redirect('/')->with('ok', "Barang {$nama} dihapus.");
    }

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
        Kategori::findOrFail($id)->delete();
        return back()->with('ok', 'Kategori dihapus.');
    }

    // ---------- Dalam proses (barang sedang di event) ----------
    public function prosesIndex()
    {
        $aktif = Acara::where('status', 'aktif')->with('detailAcara.barang')->orderByDesc('id')->get();
        return view('gudang.proses', compact('aktif'));
    }

    public function prosesDetail($id)
    {
        $a = Acara::with('detailAcara.barang')->findOrFail($id);
        return view('gudang.proses_detail', compact('a'));
    }

    // ---------- Riwayat & Log ----------
    public function riwayat()
    {
        $pengembalian = Pengembalian::with(['acara', 'detailPengembalian.barang'])->orderByDesc('id')->get();
        return view('gudang.riwayat', compact('pengembalian'));
    }

    public function riwayatDetail($id)
    {
        $p = Pengembalian::with(['acara', 'detailPengembalian.barang'])->findOrFail($id);
        return view('gudang.riwayat_detail', compact('p'));
    }
    public function log()
    {
        $log = LogAktivitas::orderByDesc('id')->limit(100)->get();
        return view('gudang.log', compact('log'));
    }

    public function riwayatPdf()
    {
        $pengembalian = Pengembalian::with(['acara', 'detailPengembalian.barang'])->orderByDesc('id')->get();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('gudang.pdf', compact('pengembalian'));
        return $pdf->download('rekap-pengembalian.pdf');
    }

    // ---------- Halaman QR ----------
    public function qrPage()
    {
        $barang = Barang::orderBy('nama_barang')->get();
        return view('gudang.qr', compact('barang'));
    }

    // ---------- Scan (HP) ----------
    public function scan(string $kode)
    {
        $b = Barang::where('kode_qr', $kode)->firstOrFail();
        $acara = Acara::where('status','aktif')
            ->whereHas('detailAcara', fn($q) => $q->where('barang_id',$b->id))
            ->with('detailAcara')->get();
        return view('gudang.scan', compact('b','acara'));
    }
}