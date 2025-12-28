<?php

namespace App\Http\Middleware;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->map(fn($role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                    ]),
                    'department_id' => $user->department_id,
                ] : null,
                'abilities' => fn() => $this->buildAbilities($request),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAbilities(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            return [];
        }

        // General abilities (non model-specific)
        $abilities = [
            'users.viewAny' => $user->can('viewAny', User::class),
            'users.create' => $user->can('create', User::class),
        ];

        // Purchase Request abilities (model-specific on the current page, if present)
        // NOTE: The Inertia web route uses `{purchaseRequestId}` (int), while the API uses `{purchaseRequest}` (model binding).
        $purchaseRequestId = $request->route('purchaseRequestId');
        if ($purchaseRequestId) {
            /** @var PurchaseRequest|null $pr */
            $pr = PurchaseRequest::query()->find((int) $purchaseRequestId);
            if ($pr) {
                $abilities['purchaseRequests.view'] = $user->can('view', $pr);
                $abilities['purchaseRequests.update'] = $user->can('update', $pr);
                $abilities['purchaseRequests.submit'] = $user->can('submit', $pr);
                $abilities['purchaseRequests.approve'] = $user->can('approve', $pr);
            }
        }

        return $abilities;
    }
}
