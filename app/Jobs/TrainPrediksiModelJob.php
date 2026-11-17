<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class TrainPrediksiModelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public ?int $fasilitasId = null,
        public ?int $obatId = null,
        public ?int $requestedBy = null,
    ) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('ai-train-models'))->dontRelease()];
    }

    public function handle(NotificationService $notifications): void
    {
        $params = ['--force' => true];
        if ($this->fasilitasId !== null) {
            $params['--fasilitas-id'] = $this->fasilitasId;
        }
        if ($this->obatId !== null) {
            $params['--obat-id'] = $this->obatId;
        }

        $exit = Artisan::call('ai:train-models', $params);
        $output = trim(Artisan::output());

        if ($this->requestedBy !== null) {
            $user = User::find($this->requestedBy);
            if ($user) {
                $exit === 0
                    ? $notifications->notifyUser($user, 'Training AI selesai', 'Model prediksi berhasil dilatih. '.mb_substr($output, -200))
                    : $notifications->notifyUser($user, 'Training AI gagal', 'Perintah ai:train-models gagal. '.mb_substr($output, -200));
            }
        }

        if ($exit !== 0) {
            Log::error('TrainPrediksiModelJob gagal', ['output' => $output]);
            $this->fail(new \RuntimeException('ai:train-models gagal: '.$output));
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('TrainPrediksiModelJob failed', ['error' => $e->getMessage()]);
        if ($this->requestedBy !== null) {
            $user = User::find($this->requestedBy);
            $user?->notify(new AppNotification('Training AI gagal', $e->getMessage()));
        }
    }
}
