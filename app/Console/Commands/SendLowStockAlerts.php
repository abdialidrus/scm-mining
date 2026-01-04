<?php

namespace App\Console\Commands;

use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendLowStockAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:send-low-stock-alerts {--dry-run : Show what would be sent without actually sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send low stock alert notifications to inventory managers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!config('notifications.stock_alert.enabled', true)) {
            $this->info('Stock alerts are disabled in configuration.');
            return;
        }

        $threshold = config('notifications.stock_alert.low_stock_threshold', 10);

        $this->info("📦 Checking Low Stock Levels");
        $this->info("   Threshold: {$threshold} units");
        $this->newLine();

        // Get low stock items
        $lowStockItems = StockBalance::query()
            ->with(['item.baseUom', 'location'])
            ->whereRaw('qty_on_hand <= ?', [$threshold])
            ->where('qty_on_hand', '>', 0)
            ->get();

        // Get out of stock items
        $outOfStockItems = StockBalance::query()
            ->with(['item.baseUom', 'location'])
            ->where('qty_on_hand', '<=', 0)
            ->get();

        $totalIssues = $lowStockItems->count() + $outOfStockItems->count();

        if ($totalIssues === 0) {
            $this->info('✓ No stock issues found. All inventory levels are healthy!');
            return;
        }

        $this->line("Found {$lowStockItems->count()} low stock item(s)");
        $this->line("Found {$outOfStockItems->count()} out of stock item(s)");
        $this->newLine();

        // Prepare data for notification
        $items = $this->prepareItemData($lowStockItems->concat($outOfStockItems));

        // Get users to notify (users with inventory management role)
        $recipients = $this->getRecipients();

        if ($recipients->isEmpty()) {
            $this->warn('⚠ No recipients found. Please assign users to inventory management roles.');
            return;
        }

        $sentCount = 0;
        $errorCount = 0;

        foreach ($recipients as $user) {
            if ($this->option('dry-run')) {
                $this->line("  [DRY RUN] Would send to: {$user->name} ({$user->email})");
                $this->line("            Low stock: {$lowStockItems->count()} | Out of stock: {$outOfStockItems->count()}");
            } else {
                try {
                    $user->notify(new \App\Notifications\Inventory\LowStockAlertNotification(
                        $items,
                        $lowStockItems->count(),
                        $outOfStockItems->count()
                    ));

                    $this->info("  ✓ Sent to: {$user->name} ({$user->email})");
                    $sentCount++;

                    Log::info('Low stock alert sent', [
                        'user_id' => $user->id,
                        'low_stock_count' => $lowStockItems->count(),
                        'out_of_stock_count' => $outOfStockItems->count(),
                    ]);
                } catch (\Exception $e) {
                    $this->error("  ✗ Failed for: {$user->name} - " . $e->getMessage());
                    $errorCount++;

                    Log::error('Failed to send low stock alert', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->info('✓ Dry run completed. No notifications were actually sent.');
        } else {
            $this->info("✓ Alert sending completed!");
            $this->info("   Sent: {$sentCount} | Errors: {$errorCount}");
        }
    }

    /**
     * Prepare item data for notification.
     */
    private function prepareItemData($stockBalances): array
    {
        $items = [];

        foreach ($stockBalances as $balance) {
            $item = $balance->item;
            $location = $balance->location;

            if (!$item || !$location) {
                continue;
            }

            $items[] = [
                'code' => $item->sku ?? 'N/A',
                'name' => $item->name ?? 'N/A',
                'location' => $location->name ?? 'Unknown',
                'current_stock' => number_format($balance->qty_on_hand, 2),
                'min_stock' => config('notifications.stock_alert.low_stock_threshold', 10),
                'uom' => $item->baseUom->code ?? 'EA',
            ];
        }

        // Limit to top 20 items for email
        return array_slice($items, 0, 20);
    }

    /**
     * Get users who should receive stock alerts.
     */
    private function getRecipients()
    {
        // Get users with inventory management permissions
        // You can customize this based on your role structure
        return User::query()
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', [
                    'inventory-manager',
                    'warehouse-manager',
                    'admin',
                ]);
            })
            ->get();
    }
}
