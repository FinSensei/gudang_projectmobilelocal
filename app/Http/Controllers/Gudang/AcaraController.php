<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use App\Models\LogAktivitas;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcaraController extends Controller
{
    // ---------- Acara (keluar) ----------
    public function form()
    {
        $barang = Barang::where('stok_baik', '>', 0)->orderBy('nama_barang')->get();

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
            if (! $b) {
                continue;
            }
            if ((int) ($item['jumlah'] ?? 0) > (int) $b->stok_baik) {
                $gagal[] = "{$b->nama_barang} (baik {$b->stok_baik}, diminta {$item['jumlah']})";
            }
        }

        return $gagal
            ? 'Jumlah melebihi stok baik: '.implode(', ', $gagal).'. Kurangi jumlahnya atau tambah stok dulu.'
            : null;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_acara' => 'required|string|max:100',
            'penanggung_jawab' => 'nullable|string|max:100',
            'tanggal' => 'nullable|date|after_or_equal:today',
            'items' => 'required|array|min:1',
            'items.*.barang_id' => 'required|exists:barang,id',
            'items.*.jumlah' => 'required|integer|min:1',
        ]);

        if ($pesan = $this->cekBatasStok($data['items'])) {
            return back()->withErrors(['items' => $pesan])->withInput();
        }

        try {
            DB::transaction(function () use ($data) {
                $acara = Acara::create([
                    'nama_acara' => $data['nama_acara'],
                    'penanggung_jawab' => $data['penanggung_jawab'] ?? null,
                    'tanggal' => $data['tanggal'] ?? now(),
                    'status' => 'reservasi',
                ]);
                // Reservasi: catat pesanan, TIDAK memotong stok.
                // Stok baru dipotong saat admin menekan "Konfirmasi".
                app(CheckoutService::class)->reservasi($acara, $data['items']);
                LogAktivitas::create(['aksi' => 'acara_reservasi', 'entitas' => 'acara', 'keterangan' => $acara->nama_acara]);
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
    public function edit($id)
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
    public function update(Request $request, $id)
    {
        $acara = Acara::with('detailAcara')->findOrFail($id);

        if ($this->sudahAdaPengembalian($acara)) {
            return back()
                ->with('err', 'Acara sudah ada pengembalian. Tidak bisa diubah — pakai menu Stok untuk koreksi manual.')
                ->withInput();
        }

        $data = $request->validate([
            'nama_acara' => 'required|string|max:100',
            'tanggal' => 'nullable|date',
            'penanggung_jawab' => 'nullable|string|max:100',
            'items' => 'nullable|array',
            'items.*.barang_id' => 'required|exists:barang,id',
            'items.*.jumlah' => 'required|integer|min:1',
        ]);

        // Reservasi: batasi ke stok baik (belum dipotong, jadi pakai baik saat ini)
        if ($acara->status !== 'aktif') {
            if ($pesan = $this->cekBatasStok($data['items'] ?? [])) {
                return back()->withErrors(['items' => $pesan])->withInput();
            }
        }

        try {
            DB::transaction(function () use ($acara, $data) {
                $acara->nama_acara = $data['nama_acara'];
                $acara->tanggal = $data['tanggal'] ?? $acara->tanggal;
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
                            'acara_id' => $acara->id,
                            'barang_id' => $bid,
                            'jumlah_keluar' => $jumlah,
                        ]);
                    }
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('err', $e->getMessage())->withInput();
        }

        LogAktivitas::create(['aksi' => 'edit_acara', 'entitas' => 'acara', 'keterangan' => $acara->nama_acara]);

        return redirect('/proses')->with('ok', "Acara {$acara->nama_acara} diperbarui.");
    }

    /** Konfirmasi reservasi: barang keluar dari gudang, stok_baik dipotong. */
    public function konfirmasi($id)
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

        LogAktivitas::create(['aksi' => 'acara_konfirmasi', 'entitas' => 'acara', 'keterangan' => $acara->nama_acara]);

        return back()->with('ok', "Acara {$acara->nama_acara} dikonfirmasi. Barang keluar dari gudang.");
    }

    // ---------- Dalam proses (barang sedang di acara) ----------
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
            'tahun' => $request->input('tahun'),
            'tanggal' => $request->input('tanggal'),
        ]);
    }

    public function prosesDetail($id)
    {
        $a = Acara::with('detailAcara.barang')->findOrFail($id);

        return view('gudang.proses_detail', compact('a'));
    }

    /** Batalkan acara: barang kembali ke stok, status jadi batal */
    public function batalkan($id)
    {
        $a = Acara::with('detailAcara')->findOrFail($id);

        if (! in_array($a->status, ['aktif', 'reservasi'], true)) {
            return back()->with('err', 'Hanya acara aktif atau reservasi yang bisa dibatalkan.');
        }

        DB::transaction(function () use ($a) {
            // Reservasi belum memotong stok -> tidak ada yang perlu dikembalikan
            if ($a->status === 'aktif') {
                foreach ($a->detailAcara as $d) {
                    $b = Barang::find($d->barang_id);
                    if ($b) {
                        $b->stok_baik += $d->jumlah_keluar;
                        $b->save();
                    }
                }
            }
            $a->update(['status' => 'batal']);
            LogAktivitas::create(['aksi' => 'batalkan_acara', 'entitas' => 'acara', 'keterangan' => $a->nama_acara]);
        });

        return back()->with('ok', "Acara {$a->nama_acara} dibatalkan, barang kembali ke stok.");
    }
}
