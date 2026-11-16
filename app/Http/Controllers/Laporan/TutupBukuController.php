<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Models\TutupBuku;
use App\Services\TutupBukuService;
use App\Support\TableMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TutupBukuController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', TutupBuku::class);

        $filters = $request->only(['tipe', 'status', 'tahun']);

        $query = TutupBuku::query()->with(['ditutupOleh:id,name'])->latest('tahun')->latest('id');

        $query->when($filters['tipe'] ?? null, fn ($q, $v) => $q->where('tipe', $v));
        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));
        $query->when($filters['tahun'] ?? null, fn ($q, $v) => $q->where('tahun', $v));

        $perPage = (int) $request->query('per_page', $request->user()->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $items = $query->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
            ]);
        }

        return view('laporan.tutup-buku.index', [
            'initial' => [
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
                'filters' => $filters,
            ],
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, TutupBukuService $service): RedirectResponse
    {
        $this->authorize('create', TutupBuku::class);

        $data = $request->validate([
            'tipe' => ['required', 'in:inisial,tahunan'],
            'cutoff' => ['required_if:tipe,inisial', 'nullable', 'date'],
            'tahun' => ['required_if:tipe,tahunan', 'nullable', 'integer', 'min:2000', 'max:2100'],
            'deadline' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string'],
        ], [
            'tipe.required' => 'Tipe tutup buku wajib dipilih.',
            'cutoff.required_if' => 'Tanggal go-live wajib diisi untuk cut-off inisial.',
            'tahun.required_if' => 'Tahun wajib diisi untuk tutup tahun.',
        ]);

        try {
            if ($data['tipe'] === 'inisial') {
                $hasil = $service->tutupInisial($data['cutoff'], $request->user()->id, $data['catatan'] ?? null);

                return redirect()->route('tutup-buku.index')
                    ->with('success', "Cut-off inisial selesai: {$hasil['neraca_total']} neraca dibuat dari opname {$hasil['opname_diproses']} faskes.");
            }

            $service->tutupTahun(
                (int) $data['tahun'],
                null,
                $request->user()->id,
                $data['catatan'] ?? null,
                isset($data['deadline']) ? Carbon::parse($data['deadline']) : null,
            );

            return redirect()->route('tutup-buku.index')
                ->with('success', "Tahun {$data['tahun']} berhasil ditutup.");
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->withInput()
                ->with('error', 'Gagal tutup buku: '.$e->getMessage());
        }
    }

    public function destroy(TutupBuku $tutupBuku): RedirectResponse
    {
        $this->authorize('delete', $tutupBuku);

        $tutupBuku->delete();

        return redirect()->route('tutup-buku.index')->with('success', 'Catatan tutup buku dihapus.');
    }

    public function bukaKembali(Request $request, TutupBuku $tutupBuku, TutupBukuService $service): RedirectResponse
    {
        $this->authorize('update', $tutupBuku);

        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:1000'],
        ], [
            'alasan.required' => 'Alasan pembukaan kembali wajib diisi.',
        ]);

        try {
            $service->bukaKembali($tutupBuku, $data['alasan'], $request->user()->id);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', 'Gagal membuka kembali: '.$e->getMessage());
        }

        return redirect()->route('tutup-buku.index')->with('success', 'Tutup buku dibuka kembali.');
    }

    public function tutupKembali(TutupBuku $tutupBuku, TutupBukuService $service): RedirectResponse
    {
        $this->authorize('update', $tutupBuku);

        try {
            $service->tutupKembali($tutupBuku, auth()->id());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', 'Gagal menutup kembali: '.$e->getMessage());
        }

        return redirect()->route('tutup-buku.index')->with('success', 'Tutup buku ditutup kembali.');
    }
}
