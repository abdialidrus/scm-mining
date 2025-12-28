<?php

namespace App\Providers;

use App\Models\ApprovalWorkflow;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PutAway;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Policies\ApprovalWorkflowPolicy;
use App\Policies\GoodsReceiptPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseRequestPolicy;
use App\Policies\PutAwayPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UserPolicy;
use App\Policies\WarehousePolicy;
use App\Policies\WarehouseLocationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register explicit route model binding for approval workflows
        Route::bind('approvalWorkflow', function ($value) {
            return ApprovalWorkflow::findOrFail($value);
        });

        Gate::policy(PurchaseRequest::class, PurchaseRequestPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(GoodsReceipt::class, GoodsReceiptPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(WarehouseLocation::class, WarehouseLocationPolicy::class);
        Gate::policy(PutAway::class, PutAwayPolicy::class);
        Gate::policy(ApprovalWorkflow::class, ApprovalWorkflowPolicy::class);
    }

    protected $policies = [];
}
