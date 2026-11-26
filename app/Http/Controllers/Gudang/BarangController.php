<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use App\Models\DetailPengembalian;
use App\Models\Kategori;
use App\Models\LogAktivitas;
use App\Services\StokService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BarangController extends Controller
{
    // ---------- Tambah barang (cepat, borongan) ----------
    public function form()
    {
        return view('gudang.barang');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => 'required|array|min:1',
            'nama.*' => 'required|string|max:100',
            'jumlah' => 'required|array',
            'jumlah.*' => 'required|integer|min:0',
            'ukuran' => 'nullable|array',
            'ukuran.*' => 'nullable|string|max:50',
            'kategori_id' => 'nullable|exists:kategori,id',
            'foto' => 'nullable|array',
            'foto.*' => 'nullable|image|max:2048',
        ]);

        $foto = $request->file('foto', []);

        $n = count($data['nama']);
        DB::transaction(function () use ($data, $foto) {
            foreach ($data['nama'] as $i => $nama) {
                $barang = Barang::create([
                    'nama_barang' => $nama,
                    'kategori_id' => $data['kategori_id'] ?? null,
                    'satuan' => 'pcs',
                    'ukuran' => ($data['ukuran'][$i] ?? '') !== '' ? $data['ukuran'][$i] : 'N/A',
                    'kode_qr' => 'QR-'.strtoupper(Str::random(8)),
                    'stok_total' => (int) $data['jumlah'][$i],
                    'stok_baik' => (int) $data['jumlah'][$i],
                    'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
                ]);

                // Foto opsional per baris — baris tanpa foto tidak menggeser baris lain
                if (! empty($foto[$i])) {
                    $path = $foto[$i]->store('barang', 'public');
                    $this->compressFoto($path);
                    $barang->foto = $path;
                    $barang->save();
                }

                LogAktivitas::create(['aksi' => 'barang_baru', 'entitas' => 'barang', 'keterangan' => $barang->nama_barang]);
            }
        });

        return redirect('/restock')->with('ok', "{$n} barang ditambahkan.");
    }

    // ---------- Edit / Hapus barang ----------
    public function edit($id)
    {
        $barang = Barang::findOrFail($id);
        $kategori = Kategori::orderBy('nama_kategori')->get();

        return view('gudang.barang_edit', compact('barang', 'kategori'));
    }

    /** Riwayat transaksi satu barang */
    public function riwayat($id)
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
            if (! $k->acara) {
                continue;
            }
            $rows[] = [
                'tanggal' => $k->acara->tanggal,
                'tipe' => 'keluar',
                'acara' => $k->acara->nama_acara,
                'jumlah' => $k->jumlah_keluar,
            ];
        }
        foreach ($kembali as $k) {
            if (! $k->pengembalian) {
                continue;   // pengaman: relasi null (mis. hasil undo)
            }
            $rows[] = [
                'tanggal' => $k->pengembalian->tanggal_pulang,
                'tipe' => 'kembali',
                'acara' => $k->pengembalian->acara->pluck('nama_acara')->join(', '),
                'jumlah' => $k->jumlah_kembali,
                'hilang' => $k->jumlah_hilang,
            ];
        }
        usort($rows, fn ($a, $b) => ($b['tanggal'] ?? '') <=> ($a['tanggal'] ?? ''));

        return view('gudang.barang_riwayat', compact('barang', 'rows'));
    }

    public function update(Request $request, $id)
    {
        $barang = Barang::findOrFail($id);
        $data = $request->validate([
            'nama_barang' => 'required|string|max:100',
            'satuan' => 'required|string|max:20',
            'ukuran' => 'nullable|string|max:50',
            'lokasi' => 'nullable|string|max:100',
            'kategori_id' => 'nullable|exists:kategori,id',
            'foto' => 'nullable|image|max:2048',
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
        LogAktivitas::create(['aksi' => 'edit_barang', 'entitas' => 'barang', 'keterangan' => $barang->nama_barang]);

        return redirect('/')->with('ok', 'Barang diperbarui.');
    }

    public function kurangi(Request $request, $id)
    {
        $request->validate(['jumlah' => 'required|integer|min:1']);
        $barang = Barang::findOrFail($id);

        // Jangan sampai sisa baik kurang dari yang sudah dipesan di reservasi
        $dipesan = app(StokService::class)->jumlahDipesan($barang->id);
        $bebas = max(0, (int) $barang->stok_baik - $dipesan);

        if ($request->jumlah > $bebas) {
            $ket = $dipesan > 0
                ? "Sisa yang bebas hanya {$bebas} ({$dipesan} sedang dipesan)."
                : "Stok baik {$barang->nama_barang} hanya {$barang->stok_baik}.";

            return back()->with('err', $ket)->withInput();
        }
        $barang->stok_baik -= $request->jumlah;
        $barang->stok_total -= $request->jumlah;
        $barang->save();
        LogAktivitas::create(['aksi' => 'kurangi', 'entitas' => 'barang', 'keterangan' => "{$barang->nama_barang} -{$request->jumlah}"]);

        return back()->with('ok', "Stok {$barang->nama_barang} dikurangi {$request->jumlah}.");
    }

    public function tambah(Request $request, $id)
    {
        $request->validate(['jumlah' => 'required|integer|min:1']);
        $barang = Barang::findOrFail($id);
        $barang->stok_total += $request->jumlah;
        $barang->stok_baik += $request->jumlah;
        $barang->save();
        LogAktivitas::create(['aksi' => 'tambah_stok', 'entitas' => 'barang', 'keterangan' => "{$barang->nama_barang} +{$request->jumlah}"]);

        return back()->with('ok', "Stok {$barang->nama_barang} +{$request->jumlah}.");
    }

    public function destroy($id)
    {
        $barang = Barang::findOrFail($id);
        if ($barang->stokKeluar() > 0) {
            return back()->with('err', "Barang {$barang->nama_barang} sedang digunakan di acara aktif, tidak bisa dihapus.");
        }
        // Barang yang masih dipesan di reservasi juga tidak boleh dihapus
        $dipesan = DetailAcara::where('barang_id', $barang->id)
            ->whereHas('acara', fn ($q) => $q->where('status', 'reservasi'))
            ->exists();
        if ($dipesan) {
            return back()->with('err', "Barang {$barang->nama_barang} sedang dipesan di reservasi, tidak bisa dihapus.");
        }
        $nama = $barang->nama_barang;
        $barang->delete();
        LogAktivitas::create(['aksi' => 'hapus_barang', 'entitas' => 'barang', 'keterangan' => $nama]);

        return redirect('/')->with('ok', "Barang {$nama} dihapus.");
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
        $acara = Acara::where('status', 'aktif')
            ->whereHas('detailAcara', fn ($q) => $q->where('barang_id', $b->id))
            ->with('detailAcara')->get();

        return view('gudang.scan', compact('b', 'acara'));
    }

    /** Kecilkan foto agar cepat dimuat. Maks 800px, JPEG 72%. */
    private function compressFoto(string $path): void
    {
        $full = Storage::disk('public')->path($path);
        if (! is_file($full)) {
            return;
        }

        $size = @getimagesize($full);
        if (! $size) {
            return;
        }
        [$w, $h] = [$size[0], $size[1]];

        $max = 800;
        if ($w <= $max && $h <= $max) {
            return;   // sudah kecil, biarkan
        }

        $src = @imagecreatefromstring(file_get_contents($full));
        if (! $src) {
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
}
