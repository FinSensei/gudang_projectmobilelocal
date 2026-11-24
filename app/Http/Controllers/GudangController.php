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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GudangController extends Controller
{
    /** Map barang_id => total jumlah keluar di acara aktif (1 query, bukan N+1) */
    private function keluarMap(): array
    {
        $aktifIds = Acara::where('status', 'aktif')->pluck('id')->all();
        if (!$aktifIds) {
            return [];
        }

        return DetailAcara::select('barang_id', DB::raw('SUM(jumlah_keluar) as total'))
            ->whereIn('acara_id', $aktifIds)
            ->groupBy('barang_id')
            ->pluck('total', 'barang_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

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

        $keluarMap = $this->keluarMap();

        return view('gudang.dashboard', compact('barang', 'acaraAktif', 'q', 'kategori', 'lowStock', 'totalHilang', 'totalKembali', 'totalKeluar', 'totalDimiliki', 'keluarMap'));
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
                'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
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
            'tingkat'   => 'required|in:ringan,berat',
        ]);
        $b = Barang::findOrFail($data['barang_id']);
        if ($b->stok_baik < $data['jumlah']) {
            return back()->with('err', "Stok baik {$b->nama_barang} hanya {$b->stok_baik}.");
        }
        $b->stok_baik -= $data['jumlah'];
        if ($data['tingkat'] === 'berat') {
            $b->stok_rusak_berat += $data['jumlah'];
        } else {
            $b->stok_rusak_ringan += $data['jumlah'];
        }
        $b->save();
        LogAktivitas::create(['aksi'=>'rusak','entitas'=>'barang','keterangan'=>"{$b->nama_barang} rusak {$data['tingkat']} +{$data['jumlah']}"]);
        return back()->with('ok', "{$b->nama_barang} ditandai rusak {$data['tingkat']} {$data['jumlah']}");
    }

    // ---------- Acara (keluar) ----------
    public function acaraForm()
    {
        $barang = Barang::where('stok_baik','>',0)->orderBy('nama_barang')->get();
        return view('gudang.acara', compact('barang'));
    }

    /**
     * Batas reservasi: satu item tidak boleh meminta lebih dari stok baik.
     * Dua reservasi BORNHYA boleh bersaing (stok belum dipotong), tapi satu
     * reservasi tidak boleh memesan barang yang jelas tidak ada.
     *
     * @return string|null pesan error, atau null kalau lolos
     */
    private function cekBatasStok(array $items): ?string
    {
        $gagal = [];
        foreach ($items as $item) {
            $b = Barang::find($item['barang_id'] ?? 0);
            if (!$b) {
                continue;
            }
            if ((int) ($item['jumlah'] ?? 0) > (int) $b->stok_baik) {
                $gagal[] = "{$b->nama_barang} (baik {$b->stok_baik}, diminta {$item['jumlah']})";
            }
        }

        return $gagal
            ? 'Jumlah melebihi stok baik: ' . implode(', ', $gagal) . '. Kurangi jumlahnya atau tambah stok dulu.'
            : null;
    }

    public function acaraStore(Request $request)
    {
        $data = $request->validate([
            'nama_acara'   => 'required|string|max:100',
            'penanggung_jawab' => 'nullable|string|max:100',
            'tanggal'      => 'nullable|date|after_or_equal:today',
            'items'        => 'required|array|min:1',
            'items.*.barang_id' => 'required|exists:barang,id',
            'items.*.jumlah'    => 'required|integer|min:1',
        ]);

        if ($pesan = $this->cekBatasStok($data['items'])) {
            return back()->withErrors(['items' => $pesan])->withInput();
        }

        try {
            DB::transaction(function () use ($data) {
                $acara = Acara::create([
                    'nama_acara'       => $data['nama_acara'],
                    'penanggung_jawab' => $data['penanggung_jawab'] ?? null,
                    'tanggal'          => $data['tanggal'] ?? now(),
                    'status'           => 'reservasi',
                ]);
                // Reservasi: catat pesanan, TIDAK memotong stok.
                // Stok baru dipotong saat admin menekan "Konfirmasi".
                app(CheckoutService::class)->reservasi($acara, $data['items']);
                LogAktivitas::create(['aksi'=>'acara_reservasi','entitas'=>'acara','keterangan'=>$acara->nama_acara]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('err', $e->getMessage())->withInput();
        }

        return redirect('/')->with('ok', "Acara {$data['nama_acara']} dibuat sebagai reservasi. Stok belum dipotong — tekan Konfirmasi saat barang akan keluar.");
    }

    /** True kalau acara sudah punya pengembalian tercatat (data terkunci). */
    private function sudahAdaPengembalian(Acara $acara): bool
    {
        return $acara->pengembalian()->exists();
    }

    /** Form edit acara (reservasi / aktif). */
    public function acaraEdit($id)
    {
        $acara = Acara::with('detailAcara.barang')->findOrFail($id);
        $barang = Barang::orderBy('nama_barang')->get();

        if ($this->sudahAdaPengembalian($acara)) {
            return redirect('/proses')
                ->with('err', 'Acara ini sudah ada pengembalian tercatat. Data tidak bisa diubah — pakai menu Stok untuk koreksi manual.');
        }

        return view('gudang.acara_edit', compact('acara', 'barang'));
    }

    /**
     * Simpan perubahan acara: nama, tanggal, penanggung jawab, dan daftar barang.
     * Kalau acara sudah 'aktif', perubahan jumlah barang langsung menyesuaikan stok_baik.
     * Kalau masih 'reservasi', hanya daftar pesanan yang berubah.
     */
    public function acaraUpdate(Request $request, $id)
    {
        $acara = Acara::with('detailAcara')->findOrFail($id);

        if ($this->sudahAdaPengembalian($acara)) {
            return back()
                ->with('err', 'Acara sudah ada pengembalian. Tidak bisa diubah — pakai menu Stok untuk koreksi manual.')
                ->withInput();
        }

        $data = $request->validate([
            'nama_acara'       => 'required|string|max:100',
            'tanggal'          => 'nullable|date',
            'penanggung_jawab' => 'nullable|string|max:100',
            'items'            => 'nullable|array',
            'items.*.barang_id' => 'required|exists:barang,id',
            'items.*.jumlah'    => 'required|integer|min:1',
        ]);

        // Reservasi: batasi ke stok baik (belum dipotong, jadi pakai baik saat ini)
        if ($acara->status !== 'aktif') {
            if ($pesan = $this->cekBatasStok($data['items'] ?? [])) {
                return back()->withErrors(['items' => $pesan])->withInput();
            }
        }

        try {
            DB::transaction(function () use ($acara, $data) {
                $acara->nama_acara       = $data['nama_acara'];
                $acara->tanggal          = $data['tanggal'] ?? $acara->tanggal;
                $acara->penanggung_jawab = $data['penanggung_jawab'] ?? null;
                $acara->save();

                $lama = $acara->detailAcara->keyBy('barang_id');
                $baru = collect($data['items'] ?? [])->keyBy('barang_id');
                $aktif = $acara->status === 'aktif';

                // Barang yang dikeluarkan dari daftar
                foreach ($lama as $bid => $d) {
                    if ($baru->has($bid)) {
                        continue;
                    }
                    if ($aktif) {
                        $b = Barang::find($bid);
                        $b->stok_baik += $d->jumlah_keluar;   // kembali ke stok
                        $b->save();
                    }
                    $d->delete();
                }

                // Barang baru / perubahan jumlah
                foreach ($baru as $bid => $item) {
                    $jumlah = (int) $item['jumlah'];
                    $b = Barang::find($bid);

                    if ($lama->has($bid)) {
                        $d = $lama[$bid];
                        $selisih = $jumlah - (int) $d->jumlah_keluar;
                        if ($aktif) {
                            if ($selisih > 0 && $b->stok_baik < $selisih) {
                                throw new \RuntimeException("Stok {$b->nama_barang} tidak cukup (baik {$b->stok_baik}, butuh tambahan {$selisih}).");
                            }
                            $b->stok_baik -= $selisih;
                            $b->save();
                        }
                        $d->jumlah_keluar = $jumlah;
                        $d->save();
                    } else {
                        if ($aktif) {
                            if ($b->stok_baik < $jumlah) {
                                throw new \RuntimeException("Stok {$b->nama_barang} tidak cukup (baik {$b->stok_baik}, butuh {$jumlah}).");
                            }
                            $b->stok_baik -= $jumlah;
                            $b->save();
                        }
                        DetailAcara::create([
                            'acara_id'     => $acara->id,
                            'barang_id'    => $bid,
                            'jumlah_keluar' => $jumlah,
                        ]);
                    }
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('err', $e->getMessage())->withInput();
        }

        LogAktivitas::create(['aksi'=>'edit_acara','entitas'=>'acara','keterangan'=>$acara->nama_acara]);

        return redirect('/proses')->with('ok', "Acara {$acara->nama_acara} diperbarui.");
    }

    /** Konfirmasi reservasi: barang keluar dari gudang, stok_baik dipotong. */
    public function acaraKonfirmasi($id)
    {
        $acara = Acara::with('detailAcara')->findOrFail($id);

        if ($acara->status === 'aktif') {
            return back()->with('err', 'Acara ini sudah dikonfirmasi (barang sudah keluar).');
        }

        try {
            app(CheckoutService::class)->konfirmasi($acara);
        } catch (\RuntimeException $e) {
            return back()->with('err', $e->getMessage());
        }

        LogAktivitas::create(['aksi'=>'acara_konfirmasi','entitas'=>'acara','keterangan'=>$acara->nama_acara]);

        return back()->with('ok', "Acara {$acara->nama_acara} dikonfirmasi. Barang keluar dari gudang.");
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
            'rusak_ringan'   => 'nullable|array',
            'rusak_ringan.*' => 'integer|min:0',
            'rusak_berat'    => 'nullable|array',
            'rusak_berat.*'  => 'integer|min:0',
        ]);

        // Acara yang sudah selesai tidak boleh diproses ulang
        $selesai = Acara::whereIn('id', $data['acara_ids'])->where('status', '!=', 'aktif')->exists();
        if ($selesai) {
            return back()->withErrors(['acara_ids' => 'Ada acara yang sudah selesai. Pilih hanya acara yang masih aktif.'])->withInput();
        }

        $acaraIds = $data['acara_ids'];
        $outMap = [];
        DetailAcara::whereIn('acara_id', $acaraIds)->get()->each(function($d) use (&$outMap){
            $outMap[$d->barang_id] = ($outMap[$d->barang_id] ?? 0) + $d->jumlah_keluar;
        });

        // Kembali tidak boleh melebihi jumlah keluar
        $lebih = [];
        foreach ($outMap as $bid => $out) {
            $k = (int)($data['kembali'][$bid] ?? 0);
            if ($k > $out) {
                $lebih[] = Barang::find($bid)->nama_barang;
            }
        }
        if ($lebih) {
            return back()->withErrors(['kembali' => 'Jumlah kembali melebihi jumlah keluar untuk: '.implode(', ', $lebih)])->withInput();
        }

        $rows = [];
        foreach ($outMap as $bid => $out) {
            $b = Barang::find($bid);
            $baik   = (int) ($data['kembali'][$bid] ?? 0);
            $ringan = (int) ($data['rusak_ringan'][$bid] ?? 0);
            $berat  = (int) ($data['rusak_berat'][$bid] ?? 0);

            // total tidak boleh melebihi keluar
            $total = $baik + $ringan + $berat;
            if ($total > $out) {
                $berat = max(0, $berat - ($total - $out));
                $total = $baik + $ringan + $berat;
            }
            if ($total > $out) {
                $ringan = max(0, $ringan - ($total - $out));
                $total = $baik + $ringan + $berat;
            }
            if ($total > $out) {
                $baik = max(0, $baik - ($total - $out));
            }

            $rows[] = [
                'barang_id'    => $bid,
                'nama'         => $b->nama_barang,
                'keluar'       => $out,
                'kembali'      => $baik,
                'ringan'       => $ringan,
                'berat'        => $berat,
                'hilang'       => max(0, $out - ($baik + $ringan + $berat)),
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
            'rusak_ringan'   => 'nullable|array',
            'rusak_ringan.*' => 'integer|min:0',
            'rusak_berat'    => 'nullable|array',
            'rusak_berat.*'  => 'integer|min:0',
        ]);

        // Acara yang sudah selesai tidak boleh diproses ulang
        $selesai = Acara::whereIn('id', $data['acara_ids'])->where('status', '!=', 'aktif')->exists();
        if ($selesai) {
            return back()->withErrors(['acara_ids' => 'Ada acara yang sudah selesai. Pilih hanya acara yang masih aktif.'])->withInput();
        }

        // Kembali tidak boleh melebihi jumlah keluar
        $outMap = [];
        DetailAcara::whereIn('acara_id', $data['acara_ids'])->get()->each(function ($d) use (&$outMap) {
            $outMap[$d->barang_id] = ($outMap[$d->barang_id] ?? 0) + $d->jumlah_keluar;
        });
        $lebih = [];
        foreach ($outMap as $bid => $out) {
            $k = (int) ($data['kembali'][$bid] ?? 0);
            if ($k > $out) {
                $lebih[] = Barang::find($bid)->nama_barang;
            }
        }
        if ($lebih) {
            return back()->withErrors(['kembali' => 'Jumlah kembali melebihi jumlah keluar untuk: '.implode(', ', $lebih)])->withInput();
        }

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
                    'pengembalian_id'    => $p->id,
                    'barang_id'          => $bid,
                    'jumlah_kembali'     => (int) ($data['kembali'][$bid] ?? 0),
                    'jumlah_rusak_ringan'=> (int) ($data['rusak_ringan'][$bid] ?? 0),
                    'jumlah_rusak_berat' => (int) ($data['rusak_berat'][$bid] ?? 0),
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
            'ukuran'  => 'nullable|array',
            'ukuran.*'=> 'nullable|string|max:50',
            'kategori_id' => 'nullable|exists:kategori,id',
        ]);

        $n = count($data['nama']);
        DB::transaction(function () use ($data) {
            foreach ($data['nama'] as $i => $nama) {
                $barang = Barang::create([
                    'nama_barang' => $nama,
                    'kategori_id' => $data['kategori_id'] ?? null,
                    'satuan'      => 'pcs',
                    'ukuran'      => ($data['ukuran'][$i] ?? '') !== '' ? $data['ukuran'][$i] : 'N/A',
                    'kode_qr'     => 'QR-'.strtoupper(Str::random(8)),
                    'stok_total'  => (int) $data['jumlah'][$i],
                    'stok_baik'   => (int) $data['jumlah'][$i],
                    'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
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

    /** Riwayat transaksi satu barang */
    public function barangRiwayat($id)
    {
        $barang = Barang::findOrFail($id);

        $keluar = DetailAcara::with('acara')
            ->where('barang_id', $barang->id)
            ->orderByDesc('id')->get();

        $kembali = DetailPengembalian::with('pengembalian')
            ->where('barang_id', $barang->id)
            ->whereHas('pengembalian')   // abaikan detail yg pengembaliannya sudah di-undo
            ->orderByDesc('id')->get();

        $rows = [];
        foreach ($keluar as $k) {
            if (!$k->acara) {
                continue;
            }
            $rows[] = [
                'tanggal' => $k->acara->tanggal,
                'tipe'    => 'keluar',
                'acara'   => $k->acara->nama_acara,
                'jumlah'  => $k->jumlah_keluar,
            ];
        }
        foreach ($kembali as $k) {
            if (!$k->pengembalian) {
                continue;   // pengaman: relasi null (mis. hasil undo)
            }
            $rows[] = [
                'tanggal' => $k->pengembalian->tanggal_pulang,
                'tipe'    => 'kembali',
                'acara'   => $k->pengembalian->acara->pluck('nama_acara')->join(', '),
                'jumlah'  => $k->jumlah_kembali,
                'hilang'  => $k->jumlah_hilang,
            ];
        }
        usort($rows, fn ($a, $b) => ($b['tanggal'] ?? '') <=> ($a['tanggal'] ?? ''));

        return view('gudang.barang_riwayat', compact('barang', 'rows'));
    }

    public function barangUpdate(Request $request, $id)
    {
        $barang = Barang::findOrFail($id);
        $data = $request->validate([
            'nama_barang' => 'required|string|max:100',
            'satuan'      => 'required|string|max:20',
            'ukuran'      => 'nullable|string|max:50',
            'lokasi'      => 'nullable|string|max:100',
            'kategori_id' => 'nullable|exists:kategori,id',
            'foto'        => 'nullable|image|max:2048',
            'foto_galeri' => 'nullable|image|max:2048',
        ]);

        $data['ukuran'] = ($data['ukuran'] ?? '') !== '' ? $data['ukuran'] : 'N/A';

        // Upload foto baru dari kamera ATAU galeri (salah satu)
        $fotoBaru = $request->file('foto') ?? $request->file('foto_galeri');
        if ($fotoBaru) {
            if ($barang->foto && Storage::disk('public')->exists($barang->foto)) {
                Storage::disk('public')->delete($barang->foto);
            }
            $data['foto'] = $fotoBaru->store('barang', 'public');
            $this->compressFoto($data['foto']);
        } else {
            unset($data['foto']);
        }

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
        $k = Kategori::findOrFail($id);

        // Kategori yang masih dipakai barang tidak boleh dihapus
        if (Barang::where('kategori_id', $k->id)->exists()) {
            return back()->with('err', "Kategori {$k->nama_kategori} masih dipakai barang, tidak bisa dihapus.");
        }

        $k->delete();
        return back()->with('ok', "Kategori {$k->nama_kategori} dihapus.");
    }

    // ---------- Dalam proses (barang sedang di event) ----------
    public function prosesIndex(Request $request)
    {
        $q = Acara::whereIn('status', ['reservasi', 'aktif'])->with('detailAcara.barang');

        if ($tahun = $request->input('tahun')) {
            $q->whereYear('tanggal', $tahun);
        }
        if ($tanggal = $request->input('tanggal')) {
            $q->whereDate('tanggal', $tanggal);
        }

        $aktif = $q->orderByDesc('id')->get();

        return view('gudang.proses', compact('aktif') + [
            'tahun'   => $request->input('tahun'),
            'tanggal' => $request->input('tanggal'),
        ]);
    }

    public function prosesDetail($id)
    {
        $a = Acara::with('detailAcara.barang')->findOrFail($id);
        return view('gudang.proses_detail', compact('a'));
    }

    /** Batalkan acara: barang kembali ke stok, status jadi batal */
    public function acaraBatalkan($id)
    {
        $a = Acara::with('detailAcara')->findOrFail($id);

        if ($a->status !== 'aktif') {
            return back()->with('err', 'Hanya acara aktif yang bisa dibatalkan.');
        }

        DB::transaction(function () use ($a) {
            foreach ($a->detailAcara as $d) {
                $b = Barang::find($d->barang_id);
                if ($b) {
                    $b->stok_baik += $d->jumlah_keluar;
                    $b->save();
                }
            }
            $a->update(['status' => 'batal']);
            LogAktivitas::create(['aksi'=>'batalkan_acara','entitas'=>'acara','keterangan'=>$a->nama_acara]);
        });

        return back()->with('ok', "Acara {$a->nama_acara} dibatalkan, barang kembali ke stok.");
    }

    /** Undo pengembalian: kembalikan stok & status acara ke aktif */
    public function pengembalianUndo($id)
    {
        $p = Pengembalian::withTrashed()->with(['acara', 'detailPengembalian'])->findOrFail($id);

        if ($p->trashed()) {
            return back()->with('err', 'Pengembalian ini sudah dibatalkan.');
        }

        DB::transaction(function () use ($p) {
            $acaraIds = $p->acara->pluck('id')->all();

            foreach ($p->detailPengembalian as $detail) {
                $b = Barang::find($detail->barang_id);
                if ($b) {
                    // Batalkan efek: kurangi kembali, tambah kembali yang hilang
                    $b->stok_baik -= (int) $detail->jumlah_kembali;
                    $b->stok_total += (int) $detail->jumlah_hilang;
                    $b->save();
                }
            }

            Acara::whereIn('id', $acaraIds)->update(['status' => 'aktif']);
            $p->delete(); // soft delete
            LogAktivitas::create(['aksi'=>'undo_pengembalian','entitas'=>'pengembalian','keterangan'=>'batch #'.$p->id]);
        });

        return back()->with('ok', "Pengembalian batch #{$p->id} dibatalkan, stok dikembalikan.");
    }

    // ---------- Riwayat & Log ----------
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

        $totalKeluar = 0; $totalKembali = 0; $totalHilang = 0;
        foreach ($pengembalian as $p) {
            foreach ($p->detailPengembalian as $d) {
                $totalKeluar  += $d->jumlah_keluar;
                $totalKembali += $d->jumlah_kembali;
                $totalHilang  += $d->jumlah_hilang;
            }
        }

        $dari   = $request->input('dari');
        $sampai = $request->input('sampai');

        return view('gudang.riwayat', compact(
            'pengembalian', 'q', 'totalKeluar', 'totalKembali', 'totalHilang', 'dari', 'sampai'
        ));
    }

    public function riwayatDetail($id)
    {
        $p = Pengembalian::with(['acara', 'detailPengembalian.barang'])->findOrFail($id);
        return view('gudang.riwayat_detail', compact('p'));
    }
    public function log()
    {
        $log = LogAktivitas::orderByDesc('id')->limit(LogAktivitas::MAX_ROWS)->get();
        return view('gudang.log', compact('log'));
    }

    /** Kecilkan foto agar cepat dimuat. Maks 800px, JPEG 72%. */
    private function compressFoto(string $path): void
    {
        $full = Storage::disk('public')->path($path);
        if (!is_file($full)) {
            return;
        }

        $size = @getimagesize($full);
        if (!$size) {
            return;
        }
        [$w, $h] = [$size[0], $size[1]];

        $max = 800;
        if ($w <= $max && $h <= $max) {
            return;   // sudah kecil, biarkan
        }

        $src = @imagecreatefromstring(file_get_contents($full));
        if (!$src) {
            return;
        }

        $ratio = min($max / $w, $max / $h);
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));

        $dst = imagecreatetruecolor($nw, $nh);
        // isi putih dulu (jaga PNG transparan jadi putih, bukan hitam)
        imagefilledrectangle($dst, 0, 0, $nw, $nh, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        imagejpeg($dst, $full, 72);

        imagedestroy($src);
        imagedestroy($dst);
    }

    /** Terapkan filter tanggal (dari/sampai) ke query pengembalian. */
    private function applyTanggalFilter($query, Request $request)
    {
        $dari = $request->input('dari');
        $sampai = $request->input('sampai');
        if ($dari)  $query->whereDate('tanggal_pulang', '>=', $dari);
        if ($sampai) $query->whereDate('tanggal_pulang', '<=', $sampai);
        return $query;
    }

    public function riwayatPdf(Request $request)
    {
        $pengembalian = $this->applyTanggalFilter(
            Pengembalian::with(['acara','detailPengembalian.barang'])->orderByDesc('id'),
            $request
        )->get();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('gudang.pdf', compact('pengembalian'));
        return $pdf->download('rekap-pengembalian.pdf');
    }

    public function riwayatExcel(Request $request)
    {
        $pengembalian = $this->applyTanggalFilter(
            Pengembalian::with(['acara','detailPengembalian.barang'])->orderByDesc('id'),
            $request
        )->get();

        $xlsx = app(\App\Services\ExcelExportService::class)->riwayat($pengembalian);
        $filename = 'rekap-pengembalian-'.now()->format('Y-m-d').'.xlsx';
        return response()->streamDownload(function () use ($xlsx) {
            $xlsx->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function stokExcel()
    {
        $xlsx = app(\App\Services\ExcelExportService::class)->stok();
        $filename = 'stok-barang-'.now()->format('Y-m-d').'.xlsx';
        return response()->streamDownload(function () use ($xlsx) {
            $xlsx->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ---------- Halaman QR ----------
    public function qrPage()
    {
        $barang = Barang::orderBy('nama_barang')->get();
        return view('gudang.qr', compact('barang'));
    }

    // ---------- Halaman scan kamera (pekerja) ----------
    public function scanPage()
    {
        return view('gudang.scan_kamera');
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