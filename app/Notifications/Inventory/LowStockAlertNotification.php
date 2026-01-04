<?php

namespace App\Notifications\Inventory;

use App\Notifications\BaseNotification;

class LowStockAlertNotification extends BaseNotification
{
    protected array $items;
    protected int $lowStockCount;
    protected int $outOfStockCount;

    public function __construct(array $items, int $lowStockCount, int $outOfStockCount)
    {
        $this->items = $items;
        $this->lowStockCount = $lowStockCount;
        $this->outOfStockCount = $outOfStockCount;
    }

    /**
     * {@inheritDoc}
     */
    public function getNotificationType(): string
    {
        return 'low_stock_alert';
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toMail($notifiable): array
    {
        return [
            'subject' => "⚠️ Low Stock Alert: {$this->lowStockCount} items need attention",
            'view' => 'emails.inventory.low-stock',
            'data' => [
                'items' => $this->items,
                'lowStockCount' => $this->lowStockCount,
                'outOfStockCount' => $this->outOfStockCount,
                'reportUrl' => route('reports.stock-by-location'),
            ],
        ];
    }

    /**
     * Get the push notification representation.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toPush($notifiable): array
    {
        $message = "Low stock: {$this->lowStockCount} items";

        if ($this->outOfStockCount > 0) {
            $message .= " | Out of stock: {$this->outOfStockCount} items";
        }

        return [
            'title' => '⚠️ Low Stock Alert',
            'body' => $message,
            'data' => [
                'type' => 'low_stock_alert',
                'low_stock_count' => $this->lowStockCount,
                'out_of_stock_count' => $this->outOfStockCount,
            ],
            'options' => [
                'url' => route('reports.stock-by-location'),
                'icon' => asset('images/warning-icon.png'),
            ],
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Low Stock Alert',
            'message' => "{$this->lowStockCount} item(s) have low stock levels",
            'type' => 'low_stock_alert',
            'low_stock_count' => $this->lowStockCount,
            'out_of_stock_count' => $this->outOfStockCount,
            'items' => array_slice($this->items, 0, 5), // Only first 5 for in-app
            'url' => route('reports.stock-by-location'),
            'created_at' => now()->toISOString(),
        ];
    }
}
