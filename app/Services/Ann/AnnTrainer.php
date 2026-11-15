<?php

namespace App\Services\Ann;

class AnnTrainer
{
    public function __construct(
        public array $hiddenLayers = [12, 8],
        public float $lr = 0.01,
        public int $epochs = 800,
        public float $l2 = 1e-4,
        public int $patience = 20,
    ) {}

    /**
     * @param  array<array<float>>  $X
     * @param  array<float>  $y
     * @return array{model: AnnModel, r2: float|null, mae: float|null, mape: float|null}
     */
    public function train(array $X, array $y): array
    {
        $n = count($X);
        $inDim = count($X[0]);
        $layers = array_merge([$inDim], $this->hiddenLayers, [1]);

        $scaler = new AnnScaler;
        $scaler->fit($X);
        $Xs = $scaler->transform($X);

        $yMean = array_sum($y) / max(1, $n);
        $var = 0;
        foreach ($y as $v) {
            $var += ($v - $yMean) ** 2;
        }
        $yStd = sqrt($var / max(1, $n));
        if ($yStd < 1e-8) {
            $yStd = 1.0;
        }
        $ys = array_map(fn ($v) => ($v - $yMean) / $yStd, $y);

        // init weights He
        $weights = [];
        $biases = [];
        for ($l = 0; $l < count($layers) - 1; $l++) {
            $fanIn = $layers[$l];
            $fanOut = $layers[$l + 1];
            $std = sqrt(2 / $fanIn);
            $w = [];
            $b = array_fill(0, $fanOut, 0.0);
            for ($o = 0; $o < $fanOut; $o++) {
                $row = [];
                for ($j = 0; $j < $fanIn; $j++) {
                    $row[$j] = $this->randn() * $std;
                }
                $w[$o] = $row;
            }
            $weights[$l] = $w;
            $biases[$l] = $b;
        }

        $split = (int) floor($n * 0.8);
        $bestLoss = INF;
        $bestW = $weights;
        $bestB = $biases;
        $wait = 0;

        for ($epoch = 0; $epoch < $this->epochs; $epoch++) {
            // shuffle training indices only
            $indices = range(0, max(0, $split - 1));
            shuffle($indices);
            foreach ($indices as $idx) {
                [$activations, $zs] = $this->forward($Xs[$idx], $weights, $biases);
                $pred = $activations[count($activations) - 1][0];
                $error = $pred - $ys[$idx];
                [$gradW, $gradB] = $this->backward($activations, $zs, $weights, $error);
                // SGD update
                foreach ($weights as $l => &$wl) {
                    foreach ($wl as $o => &$row) {
                        foreach ($row as $j => &$wv) {
                            $wv -= $this->lr * ($gradW[$l][$o][$j] + $this->l2 * $wv);
                        }
                    }
                }
                foreach ($biases as $l => &$bl) {
                    foreach ($bl as $o => &$bv) {
                        $bv -= $this->lr * $gradB[$l][$o];
                    }
                }
                unset($wl, $row, $wv, $bl, $bv);
            }

            // validation loss on held-out split
            if ($split < $n) {
                $valLoss = 0;
                for ($i = $split; $i < $n; $i++) {
                    [$acts] = $this->forward($Xs[$i], $weights, $biases);
                    $p = $acts[count($acts) - 1][0];
                    $valLoss += ($p - $ys[$i]) ** 2;
                }
                $valLoss /= max(1, $n - $split);
                if ($valLoss + 1e-9 < $bestLoss) {
                    $bestLoss = $valLoss;
                    $bestW = $weights;
                    $bestB = $biases;
                    $wait = 0;
                } else {
                    $wait++;
                    if ($wait >= $this->patience) {
                        break;
                    }
                }
            }
        }

        if ($bestLoss !== INF) {
            $weights = $bestW;
            $biases = $bestB;
        }

        $model = new AnnModel($weights, $biases, $layers, $scaler, $yMean, $yStd);

        $r2 = null;
        $mae = null;
        $mape = null;
        if ($split > 0 && $split < $n) {
            $testX = array_slice($X, $split);
            $testY = array_slice($y, $split);
            $preds = array_map(fn ($x) => $model->predict($x), $testX);
            $r2 = $this->rSquared($preds, $testY);
            $mae = $this->mae($preds, $testY);
            $mape = $this->mape($preds, $testY);
        }

        return [
            'model' => $model,
            'r2' => $r2,
            'mae' => $mae,
            'mape' => $mape,
        ];
    }

    private function forward(array $x, array $weights, array $biases): array
    {
        $activations = [$x];
        $zs = [];
        $h = $x;
        foreach ($weights as $l => $w) {
            $b = $biases[$l];
            $isLast = $l === count($weights) - 1;
            $z = [];
            $next = [];
            foreach ($w as $o => $row) {
                $sum = $b[$o] ?? 0;
                foreach ($row as $j => $wv) {
                    $sum += $wv * ($h[$j] ?? 0);
                }
                $z[$o] = $sum;
                $next[$o] = $isLast ? $sum : max(0, $sum);
            }
            $zs[] = $z;
            $activations[] = $next;
            $h = $next;
        }

        return [$activations, $zs];
    }

    private function backward(array $acts, array $zs, array $weights, float $error): array
    {
        $L = count($weights);
        $deltas = array_fill(0, $L, []);
        // output layer linear: delta = error
        $deltas[$L - 1] = [$error];
        for ($l = $L - 2; $l >= 0; $l--) {
            $nextDelta = $deltas[$l + 1];
            $wNext = $weights[$l + 1];
            $z = $zs[$l];
            $curr = [];
            $currSize = count($z);
            for ($j = 0; $j < $currSize; $j++) {
                $sum = 0;
                foreach ($wNext as $o => $row) {
                    $sum += $row[$j] * ($nextDelta[$o] ?? 0);
                }
                $curr[$j] = $sum * ($z[$j] > 0 ? 1 : 0);
            }
            $deltas[$l] = $curr;
        }

        $gradW = [];
        $gradB = [];
        foreach ($weights as $l => $w) {
            $aPrev = $acts[$l];
            $d = $deltas[$l];
            $gw = [];
            foreach ($w as $o => $row) {
                $grow = [];
                foreach ($row as $j => $_) {
                    $grow[$j] = $d[$o] * ($aPrev[$j] ?? 0);
                }
                $gw[$o] = $grow;
            }
            $gradW[$l] = $gw;
            $gradB[$l] = $d;
        }

        return [$gradW, $gradB];
    }

    private function randn(): float
    {
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();
        $u1 = max($u1, 1e-9);

        return sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }

    private function rSquared(array $pred, array $true): float
    {
        $mean = array_sum($true) / max(1, count($true));
        $ssTot = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $true));
        if ($ssTot == 0) {
            return 0;
        }
        $ssRes = 0;
        foreach ($pred as $i => $p) {
            $ssRes += ($true[$i] - $p) ** 2;
        }

        return 1 - $ssRes / $ssTot;
    }

    private function mae(array $pred, array $true): float
    {
        $s = 0;
        foreach ($pred as $i => $p) {
            $s += abs($true[$i] - $p);
        }

        return $s / max(1, count($true));
    }

    private function mape(array $pred, array $true): ?float
    {
        $s = 0;
        $cnt = 0;
        foreach ($pred as $i => $p) {
            if ($true[$i] == 0) {
                continue;
            }
            $s += abs(($true[$i] - $p) / $true[$i]);
            $cnt++;
        }

        return $cnt > 0 ? $s / $cnt * 100 : null;
    }
}
