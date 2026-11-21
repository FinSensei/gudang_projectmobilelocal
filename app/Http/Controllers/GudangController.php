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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GudangController extends Controller
{
    // ---------- Dashboard ----------
    public function index()
    {
        $barang = Barang::orderBy('nama_barang')->get();
        $acaraAktif = Acara::where('status', 'aktif')->orderByDesc('id')->get();
        return view('gudang.dashboard', compact('barang', 'acaraAktif'));
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

        DB::transaction(function () use ($data) {
            $acara = Acara::create([
                'nama_acara'       => $data['nama_acara'],
                'penanggung_jawab' => $data['penanggung_jawab'] ?? null,
                'tanggal'          => $data['tanggal'] ?? now(),
                'status'           => 'aktif',
            ]);
            foreach ($data['items'] as $it) {
                $b = Barang::findOrFail($it['barang_id']);
                if ($b->stok_baik < $it['jumlah']) {
                    throw new \RuntimeException("Stok {$b->nama_barang} tidak cukup (baik {$b->stok_baik}).");
                }
                $b->stok_baik -= $it['jumlah'];
                $b->save();
                DetailAcara::create(['acara_id'=>$acara->id,'barang_id'=>$b->id,'jumlah_keluar'=>$it['jumlah']]);
            }
            LogAktivitas::create(['aksi'=>'acara_keluar','entitas'=>'acara','keterangan'=>$acara->nama_acara]);
        });

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
            foreach ($data['kembali'] as $bid => $kembali) {
                DetailPengembalian::create([
                    'pengembalian_id' => $p->id,
                    'barang_id'       => $bid,
                    'jumlah_kembali'  => (int)$kembali,
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
        ]);

        $n = count($data['nama']);
        DB::transaction(function () use ($data) {
            foreach ($data['nama'] as $i => $nama) {
                $barang = Barang::create([
                    'nama_barang' => $nama,
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