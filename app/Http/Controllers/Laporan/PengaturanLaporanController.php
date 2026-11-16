<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Models\FasilitasKesehatan;
use App\Models\PengaturanLaporan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PengaturanLaporanController extends Controller
{
    /**
     * Panel pengaturan laporan (akses RKO per periode).
     */
    public function index(): View
    {
        abort_unless(auth()->user()->can('view_pengaturan_laporan'), 403);

        $global = [
            'akses_dibuka' => PengaturanLaporan::get('rko', 'akses_dibuka'),
            'periode_tahun' => PengaturanLaporan::get('rko', 'periode_tahun'),
            'deadline' => PengaturanLaporan::get('rko', 'deadline'),
        ];

        $faskesList = FasilitasKesehatan::query()
            ->orderBy('nama')
            ->get(['id', 'nama', 'tipe']);

        $overrides = PengaturanLaporan::query()
            ->where('grup', 'rko')
            ->whereNotNull('fasilitas_id')
            ->get()
            ->groupBy('fasilitas_id');

        return view('laporan.pengaturan.index', [
            'global' => $global,
            'faskesList' => $faskesList,
            'overrides' => $overrides,
        ]);
    }

    /**
     * Simpan pengaturan RKO global (fasilitas_id = null).
     */
    public function updateGlobal(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('update_pengaturan_laporan'), 403);

        $data = $request->validate([
            'akses_dibuka' => ['nullable', 'boolean'],
            'periode_tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'deadline' => ['nullable', 'date'],
        ], [
            'periode_tahun.required' => 'Periode tahun wajib diisi.',
        ]);

        PengaturanLaporan::updateOrCreate(
            ['fasilitas_id' => null, 'grup' => 'rko', 'key' => 'akses_dibuka'],
            ['value' => $request->boolean('akses_dibuka') ? '1' : '0'],
        );
        PengaturanLaporan::updateOrCreate(
            ['fasilitas_id' => null, 'grup' => 'rko', 'key' => 'periode_tahun'],
            ['value' => (string) $data['periode_tahun']],
        );

        if (filled($data['deadline'] ?? null)) {
            PengaturanLaporan::updateOrCreate(
                ['fasilitas_id' => null, 'grup' => 'rko', 'key' => 'deadline'],
                ['value' => $data['deadline']],
            );
        } else {
            PengaturanLaporan::whereNull('fasilitas_id')
                ->where('grup', 'rko')
                ->where('key', 'deadline')
                ->delete();
        }

        return redirect()->route('pengaturan-laporan.index')
            ->with('success', 'Pengaturan RKO global berhasil disimpan.');
    }

    /**
     * Simpan override per faskes (atau hapus override agar ikut global).
     */
    public function updateFaskes(Request $request, FasilitasKesehatan $faskes): RedirectResponse
    {
        abort_unless($request->user()->can('update_pengaturan_laporan'), 403);

        if ($request->boolean('ikut_global')) {
            PengaturanLaporan::where('fasilitas_id', $faskes->id)
                ->where('grup', 'rko')
                ->delete();

            return redirect()->route('pengaturan-laporan.index')
                ->with('success', "Pengaturan {$faskes->nama} dikembalikan mengikuti global.");
        }

        $data = $request->validate([
            'akses_dibuka' => ['nullable', 'boolean'],
            'periode_tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'deadline' => ['nullable', 'date'],
        ], [
            'periode_tahun.required' => 'Periode tahun wajib diisi.',
        ]);

        PengaturanLaporan::updateOrCreate(
            ['fasilitas_id' => $faskes->id, 'grup' => 'rko', 'key' => 'akses_dibuka'],
            ['value' => $request->boolean('akses_dibuka') ? '1' : '0'],
        );
        PengaturanLaporan::updateOrCreate(
            ['fasilitas_id' => $faskes->id, 'grup' => 'rko', 'key' => 'periode_tahun'],
            ['value' => (string) $data['periode_tahun']],
        );

        if (filled($data['deadline'] ?? null)) {
            PengaturanLaporan::updateOrCreate(
                ['fasilitas_id' => $faskes->id, 'grup' => 'rko', 'key' => 'deadline'],
                ['value' => $data['deadline']],
            );
        } else {
            PengaturanLaporan::where('fasilitas_id', $faskes->id)
                ->where('grup', 'rko')
                ->where('key', 'deadline')
                ->delete();
        }

        return redirect()->route('pengaturan-laporan.index')
            ->with('success', "Pengaturan RKO untuk {$faskes->nama} berhasil disimpan.");
    }
}
