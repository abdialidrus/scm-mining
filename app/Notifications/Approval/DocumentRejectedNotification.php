<?php

namespace App\Notifications\Approval;

use App\Models\User;
use App\Notifications\BaseNotification;

class DocumentRejectedNotification extends BaseNotification
{
    protected $approval;
    protected $document;
    protected $rejector;

    public function __construct($approval, $document, User $rejector)
    {
        $this->approval = $approval;
        $this->document = $document;
        $this->rejector = $rejector;
    }

    /**
     * {@inheritDoc}
     */
    public function getNotificationType(): string
    {
        return 'document_rejected';
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toMail($notifiable): array
    {
        $documentType = $this->getDocumentType();

        return [
            'subject' => "{$documentType} {$this->document->document_number} Rejected",
            'view' => 'emails.approval.rejected',
            'data' => [
                'submitterName' => $notifiable->name,
                'documentType' => $documentType,
                'documentNumber' => $this->document->document_number,
                'rejectedBy' => $this->rejector->name,
                'rejectedDate' => now()->format('d M Y H:i'),
                'reason' => $this->approval->comments ?? 'No reason provided',
                'documentUrl' => $this->getDocumentUrl(),
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
        $documentType = $this->getDocumentType();

        return [
            'title' => '✗ Document Rejected',
            'body' => "{$documentType} {$this->document->document_number} was rejected by {$this->rejector->name}",
            'data' => [
                'type' => 'document_rejected',
                'document_id' => $this->document->id,
                'document_type' => $this->getDocumentTypeKey(),
                'document_number' => $this->document->document_number,
            ],
            'options' => [
                'url' => $this->getDocumentUrl(),
                'icon' => asset('images/error-icon.png'),
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
        $documentType = $this->getDocumentType();

        return [
            'title' => 'Document Rejected',
            'message' => "{$documentType} {$this->document->document_number} was rejected",
            'type' => 'document_rejected',
            'document_id' => $this->document->id,
            'document_type' => $this->getDocumentTypeKey(),
            'document_number' => $this->document->document_number,
            'rejected_by' => $this->rejector->name,
            'reason' => $this->approval->comments ?? '',
            'url' => $this->getDocumentUrl(),
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Get human-readable document type.
     */
    protected function getDocumentType(): string
    {
        return match ($this->document->getMorphClass()) {
            'App\Models\PurchaseRequest' => 'Purchase Request',
            'App\Models\PurchaseOrder' => 'Purchase Order',
            'App\Models\GoodsReceipt' => 'Goods Receipt',
            default => 'Document',
        };
    }

    /**
     * Get document type key.
     */
    protected function getDocumentTypeKey(): string
    {
        return match ($this->document->getMorphClass()) {
            'App\Models\PurchaseRequest' => 'purchase_request',
            'App\Models\PurchaseOrder' => 'purchase_order',
            'App\Models\GoodsReceipt' => 'goods_receipt',
            default => 'document',
        };
    }

    /**
     * Get document URL.
     */
    protected function getDocumentUrl(): string
    {
        $typeKey = $this->getDocumentTypeKey();
        $routeMap = [
            'purchase_request' => 'purchase-requests.show',
            'purchase_order' => 'purchase-orders.show',
            'goods_receipt' => 'goods-receipts.show',
        ];

        $route = $routeMap[$typeKey] ?? null;
        return $route ? route($route, $this->document->id) : '#';
    }
}
