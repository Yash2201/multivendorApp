<?php

namespace App\Console\Commands;

use App\Models\ShiprocketShipment;
use App\Services\ShiprocketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncShiprocketStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shiprocket:sync-status
                            {--limit=100 : Maximum number of shipments to sync per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync tracking status for all active Shiprocket shipments';

    /**
     * Execute the console command.
     */
    public function handle(ShiprocketService $shiprocketService): int
    {
        $limit = (int) $this->option('limit');

        $activeShipments = ShiprocketShipment::active()
            ->orderBy('updated_at', 'asc') // oldest-updated first
            ->limit($limit)
            ->get();

        if ($activeShipments->isEmpty()) {
            $this->info('No active shipments to sync.');
            return Command::SUCCESS;
        }

        $this->info("Syncing {$activeShipments->count()} active shipments...");

        $synced = 0;
        $failed = 0;

        foreach ($activeShipments as $shipment) {
            try {
                $shiprocketService->syncShipmentStatus($shipment);
                $synced++;
                $this->line("  ✓ Shipment #{$shipment->id} (AWB: {$shipment->awb_code}) — {$shipment->shipment_status}");
            } catch (\Exception $e) {
                $failed++;
                $this->error("  ✗ Shipment #{$shipment->id} — {$e->getMessage()}");
                Log::channel('shiprocket')->error('Cron sync failed for shipment', [
                    'shipment_id' => $shipment->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Small delay to avoid API rate limiting
            usleep(300000); // 300ms
        }

        $this->info("Sync complete: {$synced} synced, {$failed} failed.");

        Log::channel('shiprocket')->info('Cron sync completed', [
            'total' => $activeShipments->count(),
            'synced' => $synced,
            'failed' => $failed,
        ]);

        return Command::SUCCESS;
    }
}
