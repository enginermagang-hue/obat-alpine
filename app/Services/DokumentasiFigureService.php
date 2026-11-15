<?php

namespace App\Services;

class DokumentasiFigureService
{
    private const NAVY = '#1e3a5f';

    private const NAVY_RGB = '30,58,95';

    private const HEADER_FILL = '#254878';

    private const ZEBRA = '#ecf2f9';

    private const RULE = '#b4becd';

    private const GRID = '#e1e8f2';

    private const MUTED = '#6e6e6e';

    public static function svgStages(): string
    {
        $w = 520;
        $h = 70;
        $boxW = 110;
        $boxH = 36;
        $gap = 14;
        $cy = $h / 2;
        $labels = ["Pengumpulan\ndata", "Analisis\ndata", "Perancangan\narsitektur", "Pelaksanaan\nprediksi"];
        $startX = ($w - (4 * $boxW + 3 * $gap)) / 2;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'">';
        $svg .= '<rect width="'.$w.'" height="'.$h.'" fill="white" stroke="'.self::RULE.'" stroke-width="0.7" rx="4"/>';

        foreach ($labels as $i => $lab) {
            $bx = $startX + $i * ($boxW + $gap);
            $by = $cy - $boxH / 2;
            $svg .= '<rect x="'.$bx.'" y="'.$by.'" width="'.$boxW.'" height="'.$boxH.'" fill="'.self::ZEBRA.'" stroke="'.self::NAVY.'" stroke-width="1" rx="4"/>';
            $parts = explode("\n", $lab);
            $svg .= '<text x="'.($bx + $boxW / 2).'" y="'.($by + 14).'" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="9" font-weight="700" fill="'.self::NAVY.'">'.e($parts[0]).'</text>';
            $svg .= '<text x="'.($bx + $boxW / 2).'" y="'.($by + 26).'" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="9" font-weight="700" fill="'.self::NAVY.'">'.e($parts[1]).'</text>';
            if ($i < 3) {
                $ax0 = $bx + $boxW;
                $ax1 = $bx + $boxW + $gap;
                $ay = $cy;
                $svg .= '<line x1="'.$ax0.'" y1="'.$ay.'" x2="'.$ax1.'" y2="'.$ay.'" stroke="'.self::NAVY.'" stroke-width="1.2"/>';
                $svg .= '<polygon points="'.$ax1.','.$ay.' '.($ax1 - 6).','.($ay - 3).' '.($ax1 - 6).','.($ay + 3).'" fill="'.self::NAVY.'"/>';
            }
        }
        $svg .= '</svg>';

        return $svg;
    }

    public static function svgArch(): string
    {
        $w = 520;
        $h = 140;
        $layers = [9, 12, 8, 1];
        $layerLabels = ['Input|9', 'Hidden 1|12  (ReLU)', 'Hidden 2|8  (ReLU)', 'Output|1  (Linear)'];
        $nLayers = count($layers);
        $marginX = 30;
        $colX = [];
        for ($i = 0; $i < $nLayers; $i++) {
            $colX[] = $marginX + $i * (($w - 2 * $marginX) / ($nLayers - 1));
        }
        $diam = 10;
        $r = $diam / 2;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'">';
        $svg .= '<rect width="'.$w.'" height="'.$h.'" fill="white" stroke="'.self::RULE.'" stroke-width="0.7" rx="4"/>';
        $svg .= '<text x="'.($w / 2).'" y="12" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="6" font-style="italic" fill="'.self::MUTED.'">x (9 fitur)  →  w, b  →  z  →  ReLU  →  y  |  He init  |  233 parameter</text>';

        // compute node positions
        $nodePos = [];
        foreach ($layers as $li => $n) {
            $cxCol = $colX[$li];
            $pts = [];
            if ($n === 1) {
                $pts[] = [$cxCol, $h / 2 + 8];
            } else {
                $avail = $h - 40;
                $step = $avail / ($n - 1);
                if ($step > 12) {
                    $step = 12;
                    $avail = $step * ($n - 1);
                    $y0 = ($h - $avail) / 2 + 8;
                } else {
                    $y0 = 20;
                }
                for ($k = 0; $k < $n; $k++) {
                    $pts[] = [$cxCol, $y0 + $k * $step];
                }
            }
            $nodePos[] = $pts;
        }
        // edges
        foreach ($nodePos as $li => $pts) {
            if ($li === $nLayers - 1) {
                break;
            }
            foreach ($pts as [$sx, $sy]) {
                foreach ($nodePos[$li + 1] as [$tx, $ty]) {
                    $svg .= '<line x1="'.($sx + $r).'" y1="'.$sy.'" x2="'.($tx - $r).'" y2="'.$ty.'" stroke="#afb9cd" stroke-width="0.4" opacity="0.9"/>';
                }
            }
        }
        // nodes
        foreach ($nodePos as $li => $pts) {
            foreach ($pts as [$cx, $cy]) {
                $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="white" stroke="'.self::NAVY.'" stroke-width="0.9"/>';
                if ($li === $nLayers - 1) {
                    $svg .= '<circle cx="'.$cx.'" cy="'.$cy.'" r="1.8" fill="'.self::NAVY.'"/>';
                }
            }
        }
        // layer labels
        foreach ($colX as $li => $cx) {
            $parts = explode('|', $layerLabels[$li]);
            $svg .= '<text x="'.$cx.'" y="'.($h - 10).'" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="6.5" font-weight="700" fill="'.self::NAVY.'">'.e($parts[0]).'</text>';
            $svg .= '<text x="'.$cx.'" y="'.($h - 2).'" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="6.5" font-weight="700" fill="'.self::NAVY.'">'.e($parts[1]).'</text>';
        }
        $svg .= '</svg>';

        return $svg;
    }

    private static function svgAxes(int $w, int $h, array $xLabels, float $yMin, float $yMax, float $yStep, float $plotX, float $plotY, float $plotW, float $plotH): string
    {
        $svg = '';
        // grid horizontal
        $nGrid = (int) (($yMax - $yMin) / $yStep);
        for ($i = 0; $i <= $nGrid; $i++) {
            $vy = $yMin + $i * $yStep;
            $py = $plotY + $plotH - ($vy - $yMin) / ($yMax - $yMin) * $plotH;
            $svg .= '<line x1="'.$plotX.'" y1="'.$py.'" x2="'.($plotX + $plotW).'" y2="'.$py.'" stroke="'.self::GRID.'" stroke-width="0.5"/>';
        }
        // axes
        $svg .= '<line x1="'.$plotX.'" y1="'.$plotY.'" x2="'.$plotX.'" y2="'.($plotY + $plotH).'" stroke="#6e6e6e" stroke-width="0.8"/>';
        $svg .= '<line x1="'.$plotX.'" y1="'.($plotY + $plotH).'" x2="'.($plotX + $plotW).'" y2="'.($plotY + $plotH).'" stroke="#6e6e6e" stroke-width="0.8"/>';
        // y labels
        for ($i = 0; $i <= $nGrid; $i++) {
            $vy = $yMin + $i * $yStep;
            $py = $plotY + $plotH - ($vy - $yMin) / ($yMax - $yMin) * $plotH;
            $lab = $yStep < 1 ? str_replace('.', ',', sprintf('%.1f', $vy)) : (string) (int) round($vy);
            $svg .= '<text x="'.($plotX - 4).'" y="'.($py + 2).'" text-anchor="end" font-family="DejaVu Sans, sans-serif" font-size="6" fill="#5a5a5a">'.e($lab).'</text>';
        }
        // x labels
        $n = count($xLabels);
        foreach ($xLabels as $i => $lab) {
            $px = $n > 1 ? $plotX + ($i / ($n - 1)) * $plotW : $plotX;
            $svg .= '<text x="'.$px.'" y="'.($plotY + $plotH + 10).'" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="6" fill="#5a5a5a">'.e($lab).'</text>';
        }

        return $svg;
    }

    public static function svgTraining(): string
    {
        $w = 520;
        $h = 110;
        $plotX = 38;
        $plotY = 14;
        $plotW = 464;
        $plotH = 78;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'">';
        $svg .= '<rect width="'.$w.'" height="'.$h.'" fill="white" stroke="'.self::RULE.'" stroke-width="0.7" rx="4"/>';
        $svg .= self::svgAxes($w, $h, ['0', '200', '400', '600', '800'], 0, 1.0, 0.2, $plotX, $plotY, $plotW, $plotH);
        $svg .= '<text x="'.($plotX + $plotW / 2).'" y="'.($h - 1).'" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="6" font-style="italic" fill="#5a5a5a">Epoch</text>';
        $svg .= '<text x="10" y="12" text-anchor="start" font-family="DejaVu Sans, sans-serif" font-size="6" font-style="italic" fill="#5a5a5a">MSE</text>';

        // generate points
        $ptsTrain = [];
        $ptsVal = [];
        for ($e = 0; $e <= 800; $e += 40) {
            $t = 0.92 * exp(-$e / 155) + 0.08;
            $v = 0.97 * exp(-$e / 125) + 0.14;
            if ($e > 420) {
                $v += ($e - 420) * 0.00018;
            }
            $ptsTrain[] = [$e, $t];
            $ptsVal[] = [$e, $v];
        }
        $toPx = function (float $e, float $loss) use ($plotX, $plotY, $plotW, $plotH): array {
            return [$plotX + ($e / 800) * $plotW, $plotY + $plotH - ($loss / 1.0) * $plotH];
        };
        // train solid navy
        $path = '';
        foreach ($ptsTrain as $i => [$e,$t]) {
            [$x,$y] = $toPx($e, $t);
            $path .= ($i === 0 ? 'M' : 'L')." $x $y ";
        }
        $svg .= '<path d="'.$path.'" fill="none" stroke="'.self::NAVY.'" stroke-width="1.4" stroke-linejoin="round"/>';
        // val dashed orange
        $path = '';
        foreach ($ptsVal as $i => [$e,$v]) {
            [$x,$y] = $toPx($e, $v);
            $path .= ($i === 0 ? 'M' : 'L')." $x $y ";
        }
        $svg .= '<path d="'.$path.'" fill="none" stroke="#d26e1e" stroke-width="1.2" stroke-dasharray="5,4" stroke-linejoin="round"/>';

        // early stop line
        $esX = $plotX + (520 / 800) * $plotW;
        $svg .= '<line x1="'.$esX.'" y1="'.$plotY.'" x2="'.$esX.'" y2="'.($plotY + $plotH).'" stroke="#b43c3c" stroke-width="0.7" stroke-dasharray="4,4"/>';
        $svg .= '<text x="'.$esX.'" y="'.($plotY + 10).'" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="5.5" font-style="italic" fill="#b43c3c">early stop</text>';

        // legend
        $lx = $plotX + $plotW - 86;
        $ly = $plotY + 6;
        $svg .= '<rect x="'.($lx - 4).'" y="'.($ly - 4).'" width="84" height="18" fill="white" stroke="'.self::RULE.'" stroke-width="0.5" rx="2"/>';
        $svg .= '<line x1="'.$lx.'" y1="'.($ly + 2).'" x2="'.($lx + 16).'" y2="'.($ly + 2).'" stroke="'.self::NAVY.'" stroke-width="1.4"/>';
        $svg .= '<text x="'.($lx + 20).'" y="'.($ly + 5).'" font-family="DejaVu Sans, sans-serif" font-size="5.5" fill="#1e1e1e">train MSE</text>';
        $svg .= '<line x1="'.$lx.'" y1="'.($ly + 11).'" x2="'.($lx + 16).'" y2="'.($ly + 11).'" stroke="#d26e1e" stroke-width="1.2" stroke-dasharray="5,4"/>';
        $svg .= '<text x="'.($lx + 20).'" y="'.($ly + 14).'" font-family="DejaVu Sans, sans-serif" font-size="5.5" fill="#1e1e1e">val MSE</text>';

        $svg .= '</svg>';

        return $svg;
    }

    public static function svgActualPred(array $actual, array $pred, array $months, string $xTitle): string
    {
        $w = 520;
        $h = 120;
        $plotX = 38;
        $plotY = 12;
        $plotW = 464;
        $plotH = 84;
        $span = max(max($actual), max($pred)) - min(min($actual), min($pred));
        $step = DokumentasiPrediksiDataService::niceStep(max($span, 1));
        $yMin = max(0, floor((min(min($actual), min($pred)) - $span * 0.05) / $step) * $step);
        $yMax = ceil((max(max($actual), max($pred)) + $span * 0.05) / $step) * $step;
        if ($yMax <= $yMin) {
            $yMax = $yMin + $step;
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'">';
        $svg .= '<rect width="'.$w.'" height="'.$h.'" fill="white" stroke="'.self::RULE.'" stroke-width="0.7" rx="4"/>';
        $svg .= self::svgAxes($w, $h, $months, (float) $yMin, (float) $yMax, (float) $step, $plotX, $plotY, $plotW, $plotH);
        $svg .= '<text x="'.($plotX + $plotW / 2).'" y="'.($h - 1).'" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="6" font-style="italic" fill="#5a5a5a">'.e($xTitle).'</text>';
        $svg .= '<text x="10" y="10" text-anchor="start" font-family="DejaVu Sans, sans-serif" font-size="6" font-style="italic" fill="#5a5a5a">Unit</text>';

        $toPt = function (int $idx, float $val) use ($plotX, $plotY, $plotW, $plotH, $yMin, $yMax): array {
            return [$plotX + ($idx / 11) * $plotW, $plotY + $plotH - ($val - $yMin) / ($yMax - $yMin) * $plotH];
        };
        // actual navy solid
        $path = '';
        foreach ($actual as $i => $v) {
            [$x,$y] = $toPt($i, (float) $v);
            $path .= ($i === 0 ? 'M' : 'L')." $x $y ";
        }
        $svg .= '<path d="'.$path.'" fill="none" stroke="'.self::NAVY.'" stroke-width="1.5" stroke-linejoin="round"/>';
        foreach ($actual as $i => $v) {
            [$x,$y] = $toPt($i, (float) $v);
            $svg .= '<circle cx="'.$x.'" cy="'.$y.'" r="2.3" fill="'.self::NAVY.'"/>';
        }
        // pred orange dashed
        $path = '';
        foreach ($pred as $i => $v) {
            [$x,$y] = $toPt($i, (float) $v);
            $path .= ($i === 0 ? 'M' : 'L')." $x $y ";
        }
        $svg .= '<path d="'.$path.'" fill="none" stroke="#d26e1e" stroke-width="1.3" stroke-dasharray="6,4" stroke-linejoin="round"/>';
        foreach ($pred as $i => $v) {
            [$x,$y] = $toPt($i, (float) $v);
            $svg .= '<circle cx="'.$x.'" cy="'.$y.'" r="2.3" fill="#d26e1e" stroke="white" stroke-width="0.5"/>';
        }
        // legend
        $lx = $plotX + $plotW - 96;
        $ly = $plotY + 6;
        $svg .= '<rect x="'.($lx - 4).'" y="'.($ly - 4).'" width="92" height="18" fill="white" stroke="'.self::RULE.'" stroke-width="0.5" rx="2"/>';
        $svg .= '<line x1="'.$lx.'" y1="'.($ly + 2).'" x2="'.($lx + 16).'" y2="'.($ly + 2).'" stroke="'.self::NAVY.'" stroke-width="1.4"/>';
        $svg .= '<text x="'.($lx + 20).'" y="'.($ly + 5).'" font-family="DejaVu Sans, sans-serif" font-size="5.5" fill="#1e1e1e">aktual</text>';
        $svg .= '<line x1="'.$lx.'" y1="'.($ly + 11).'" x2="'.($lx + 16).'" y2="'.($ly + 11).'" stroke="#d26e1e" stroke-width="1.2" stroke-dasharray="6,4"/>';
        $svg .= '<text x="'.($lx + 20).'" y="'.($ly + 14).'" font-family="DejaVu Sans, sans-serif" font-size="5.5" fill="#1e1e1e">prediksi</text>';

        $svg .= '</svg>';

        return $svg;
    }

    public static function svgForecast(array $vals, array $lo, array $hi, array $labels): string
    {
        $w = 520;
        $h = 110;
        $plotX = 38;
        $plotY = 14;
        $plotW = 464;
        $plotH = 74;
        $yMin = 0;
        $yMax = (int) ceil(max($hi) * 1.15 / 50) * 50;
        $step = DokumentasiPrediksiDataService::niceStep($yMax - $yMin, 5);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'">';
        $svg .= '<rect width="'.$w.'" height="'.$h.'" fill="white" stroke="'.self::RULE.'" stroke-width="0.7" rx="4"/>';
        $svg .= self::svgAxes($w, $h, $labels, (float) $yMin, (float) $yMax, (float) $step, $plotX, $plotY, $plotW, $plotH);
        $svg .= '<text x="'.($plotX + $plotW / 2).'" y="'.($h - 1).'" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="6" font-style="italic" fill="#5a5a5a">Forecast</text>';
        $svg .= '<text x="10" y="10" text-anchor="start" font-family="DejaVu Sans, sans-serif" font-size="6" font-style="italic" fill="#5a5a5a">Unit</text>';

        $barW = $plotW / 3 * 0.42;
        foreach ($vals as $i => $v) {
            $cx = $plotX + ($i + 0.5) * $plotW / 3;
            $bx0 = $cx - $barW / 2;
            $py0 = $plotY + $plotH - ($v - $yMin) / ($yMax - $yMin) * $plotH;
            $bh = ($v - $yMin) / ($yMax - $yMin) * $plotH;
            $svg .= '<rect x="'.$bx0.'" y="'.$py0.'" width="'.$barW.'" height="'.$bh.'" fill="'.self::NAVY.'" rx="2"/>';
            $wx = $cx;
            $yLo = $plotY + $plotH - ($lo[$i] - $yMin) / ($yMax - $yMin) * $plotH;
            $yHi = $plotY + $plotH - ($hi[$i] - $yMin) / ($yMax - $yMin) * $plotH;
            $svg .= '<line x1="'.$wx.'" y1="'.$yLo.'" x2="'.$wx.'" y2="'.$yHi.'" stroke="#5a5a5a" stroke-width="1"/>';
            $svg .= '<line x1="'.($wx - 6).'" y1="'.$yLo.'" x2="'.($wx + 6).'" y2="'.$yLo.'" stroke="#5a5a5a" stroke-width="1"/>';
            $svg .= '<line x1="'.($wx - 6).'" y1="'.$yHi.'" x2="'.($wx + 6).'" y2="'.$yHi.'" stroke="#5a5a5a" stroke-width="1"/>';
            // label nilai
            $svg .= '<rect x="'.($cx - 22).'" y="'.($yHi - 16).'" width="44" height="12" fill="white" rx="1"/>';
            $svg .= '<text x="'.$cx.'" y="'.($yHi - 7).'" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="6.5" font-weight="700" fill="'.self::NAVY.'">'.e((string) $v).'</text>';
            $svg .= '<text x="'.$cx.'" y="'.($yHi - 1).'" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="5" fill="#5a5a5a">'.e($lo[$i].'-'.$hi[$i]).'</text>';
        }
        // legend
        $lx = $plotX + $plotW - 100;
        $ly = $plotY + 6;
        $svg .= '<rect x="'.($lx - 4).'" y="'.($ly - 4).'" width="98" height="18" fill="white" stroke="'.self::RULE.'" stroke-width="0.5" rx="2"/>';
        $svg .= '<rect x="'.$lx.'" y="'.$ly.'" width="12" height="6" fill="'.self::NAVY.'" rx="1"/>';
        $svg .= '<text x="'.($lx + 16).'" y="'.($ly + 5).'" font-family="DejaVu Sans, sans-serif" font-size="5.5" fill="#1e1e1e">prediksi</text>';
        $svg .= '<line x1="'.$lx.'" y1="'.($ly + 11).'" x2="'.($lx + 12).'" y2="'.($ly + 11).'" stroke="#5a5a5a" stroke-width="1"/>';
        $svg .= '<line x1="'.($lx + 6).'" y1="'.($ly + 8).'" x2="'.($lx + 6).'" y2="'.($ly + 14).'" stroke="#5a5a5a" stroke-width="1"/>';
        $svg .= '<text x="'.($lx + 16).'" y="'.($ly + 14).'" font-family="DejaVu Sans, sans-serif" font-size="5.5" fill="#1e1e1e">CI 95%</text>';

        $svg .= '</svg>';

        return $svg;
    }
}
