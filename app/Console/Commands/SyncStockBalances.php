<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStockBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:sync-balances
                          {--recalculate : Recalculate all balances from movements}
                          {--warehouse= : Sync specific warehouse only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize stock_balances from stock_movements';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting stock balance synchronization...');

        if ($this->option('recalculate')) {
            $this->info('Recalculating all balances from scratch...');
            DB::table('stock_balances')->delete();
        }

        // Get all movements grouped by location and item
        $query = DB::table('stock_movements')
            ->select(
                'item_id',
                DB::raw('destination_location_id as location_id'),
                'uom_id',
                DB::raw('SUM(qty) as total_qty')
            )
            ->whereNotNull('destination_location_id')
            ->groupBy('item_id', 'destination_location_id', 'uom_id');

        if ($warehouse = $this->option('warehouse')) {
            $query->join('warehouse_locations', 'stock_movements.destination_location_id', '=', 'warehouse_locations.id')
                ->where('warehouse_locations.warehouse_id', $warehouse);
        }

        $inboundMovements = $query->get();

        $this->info("Processing {$inboundMovements->count()} inbound movements...");
        $bar = $this->output->createProgressBar($inboundMovements->count());

        foreach ($inboundMovements as $movement) {
            // Get outbound movements for this item/location
            $outbound = DB::table('stock_movements')
                ->where('item_id', $movement->item_id)
                ->where('source_location_id', $movement->location_id)
                ->where('uom_id', $movement->uom_id)
                ->sum('qty');

            $netQty = $movement->total_qty - $outbound;

            if ($netQty != 0) {
                DB::table('stock_balances')->updateOrInsert(
                    [
                        'location_id' => $movement->location_id,
                        'item_id' => $movement->item_id,
                        'uom_id' => $movement->uom_id,
                    ],
                    [
                        'qty_on_hand' => $netQty,
                        'as_of_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Show summary
        $totalBalances = DB::table('stock_balances')->count();
        $totalQty = DB::table('stock_balances')->sum('qty_on_hand');
        $uniqueItems = DB::table('stock_balances')->distinct('item_id')->count();

        $this->info("✅ Synchronization complete!");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Balance Records', number_format($totalBalances)],
                ['Unique Items', number_format($uniqueItems)],
                ['Total Quantity', number_format($totalQty, 2)],
            ]
        );

        return Command::SUCCESS;
    }
}
