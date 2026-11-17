<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\PrediksiRekomendasiService;
use App\Support\FaskesAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dash): View
    {
        $user = $request->user();
        $isDinas = $user->hasAnyRole(['super_admin', 'admin_dinas', 'admin_gudang']);

        $insight = null;
        if ($user->can('view_prediksi_kebutuhan')) {
            $ids = FaskesAccess::visibleFasilitasIds($user);
            $svc = new PrediksiRekomendasiService(
                fasilitasId: count($ids) === 1 ? $ids[0] : null,
                visibleFasilitasIds: count($ids) === 1 ? null : ($isDinas ? null : $ids),
            );
            $top = $svc->lonjakan(2);
            $defisit = collect($svc->rows())->where('rekom', '>', 0)->count();
            $insight = ['top' => $top, 'defisit' => $defisit];
        }

        return view('dashboard', [
            'user' => $user,
            'isDinas' => $isDinas,
            'dinas' => $isDinas ? $dash->dinas() : null,
            'faskes' => $isDinas ? null : $dash->faskes($user),
            'insight' => $insight,
            'fasilitas' => $user->fasilitasKesehatan,
        ]);
    }
}
