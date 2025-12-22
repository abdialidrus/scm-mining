<?php

namespace App\Providers;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\GoodsReceiptPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseRequestPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UserPolicy;
use App\Policies\WarehousePolicy;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(PurchaseRequest::class, PurchaseRequestPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(GoodsReceipt::class, GoodsReceiptPolicy::class);
    }

    protected $policies = [];
}
