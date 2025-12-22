<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Item;
use App\Models\Uom;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('allows requester to create draft, submit, then department head can approve', function () {
    $uom = Uom::query()->create(['code' => 'EA', 'name' => 'Each']);
    $item = Item::query()->create([
        'sku' => 'IT-001',
        'name' => 'Test Item',
        'base_uom_id' => $uom->id,
    ]);

    $requester = User::factory()->create(['department_id' => null]);
    $head = User::factory()->create(['department_id' => null]);

    $dept = Department::query()->create([
        'code' => 'D01',
        'name' => 'Ops',
        'head_user_id' => $head->id,
    ]);

    $requester->forceFill(['department_id' => $dept->id])->save();
    $head->forceFill(['department_id' => $dept->id])->save();

    Sanctum::actingAs($requester);

    $create = postJson('/api/purchase-requests', [
        'department_id' => $dept->id,
        'remarks' => 'draft',
        'lines' => [
            [
                'item_id' => $item->id,
                'quantity' => 2,
                'uom_id' => $uom->id,
                'remarks' => 'line',
            ],
        ],
    ])->assertCreated();

    $prId = $create->json('data.id');

    postJson("/api/purchase-requests/{$prId}/submit", [])
        ->assertOk()
        ->assertJsonPath('data.status', 'SUBMITTED');

    // requester cannot approve
    postJson("/api/purchase-requests/{$prId}/approve", [])
        ->assertForbidden();

    Sanctum::actingAs($head);

    postJson("/api/purchase-requests/{$prId}/approve", [])
        ->assertOk()
        ->assertJsonPath('data.status', 'APPROVED');
});

it('requires reject reason and records status history', function () {
    $uom = Uom::query()->create(['code' => 'EA2', 'name' => 'Each']);
    $item = Item::query()->create([
        'sku' => 'IT-002',
        'name' => 'Test Item 2',
        'base_uom_id' => $uom->id,
    ]);

    $requester = User::factory()->create();
    $head = User::factory()->create();

    $dept = Department::query()->create([
        'code' => 'D02',
        'name' => 'Plant',
        'head_user_id' => $head->id,
    ]);

    $requester->forceFill(['department_id' => $dept->id])->save();
    $head->forceFill(['department_id' => $dept->id])->save();

    Sanctum::actingAs($requester);

    $create = postJson('/api/purchase-requests', [
        'department_id' => $dept->id,
        'remarks' => null,
        'lines' => [
            ['item_id' => $item->id, 'quantity' => 1, 'uom_id' => $uom->id],
        ],
    ])->assertCreated();

    $prId = $create->json('data.id');

    postJson("/api/purchase-requests/{$prId}/submit", [])->assertOk();

    Sanctum::actingAs($head);

    // reject reason required
    postJson("/api/purchase-requests/{$prId}/reject", [])
        ->assertUnprocessable();

    postJson("/api/purchase-requests/{$prId}/reject", [
        'reason' => 'Need revision',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'DRAFT');

    $show = getJson("/api/purchase-requests/{$prId}")
        ->assertOk();

    expect($show->json('data.status_histories'))->toBeArray();
    expect(collect($show->json('data.status_histories'))->pluck('action')->all())
        ->toContain('submit', 'reject');
});
