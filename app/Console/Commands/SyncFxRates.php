<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\SuppliersPricing\Services\FxRateSyncService;
use Throwable;

final class SyncFxRates extends Command
{
    protected $signature = 'royal:fx-sync {--dry-run : Check provider without saving}';
    protected $description = 'Fetch USD to EGP/SAR reference exchange rates';

    public function handle(FxRateSyncService $sync): int
    {
        try {
            if ($this->option('dry-run')) {
                $provider = app(\App\Modules\SuppliersPricing\Contracts\FxRateProviderInterface::class);
                $result = $provider->fetchLatestRates('USD', ['EGP','SAR']);
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                return self::SUCCESS;
            }
            $count = $sync->sync('USD', ['EGP','SAR']);
            $this->info("Saved {$count} reference rate(s).");
            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('FX synchronization failed. Check credentials, currencies and provider response.');
            return self::FAILURE;
        }
    }
}
