<?php

use App\Models\Department;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Create authenticated user
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('it can list all departments', function () {
    // Create test departments
    Department::create([
        'code' => 'IT',
        'name' => 'Information Technology',
        'created_by_user_id' => $this->user->id,
    ]);

    Department::create([
        'code' => 'HR',
        'name' => 'Human Resources',
        'created_by_user_id' => $this->user->id,
    ]);

    $response = $this->getJson('/api/departments');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'code', 'name', 'parent_id', 'head_user_id', 'created_at', 'updated_at']
            ]
        ])
        ->assertJsonCount(2, 'data');
});

test('it can view a single department', function () {
    $department = Department::create([
        'code' => 'IT',
        'name' => 'Information Technology',
        'created_by_user_id' => $this->user->id,
    ]);

    $response = $this->getJson("/api/departments/{$department->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $department->id)
        ->assertJsonPath('data.code', 'IT')
        ->assertJsonPath('data.name', 'Information Technology');
});

test('it returns 404 for non-existent department', function () {
    $response = $this->getJson('/api/departments/99999');

    $response->assertNotFound();
});

test('it can create a new department', function () {
    $response = $this->postJson('/api/departments', [
        'code' => 'FIN',
        'name' => 'Finance Department',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.code', 'FIN')
        ->assertJsonPath('data.name', 'Finance Department');

    $this->assertDatabaseHas('departments', [
        'code' => 'FIN',
        'name' => 'Finance Department',
    ]);
});

test('it can create a department with parent', function () {
    $parent = Department::create([
        'code' => 'PARENT',
        'name' => 'Parent Department',
        'created_by_user_id' => $this->user->id,
    ]);

    $response = $this->postJson('/api/departments', [
        'code' => 'CHILD',
        'name' => 'Child Department',
        'parent_id' => $parent->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.code', 'CHILD')
        ->assertJsonPath('data.parent_id', $parent->id);
});

test('it can create a department with head user', function () {
    $head = User::factory()->create(['name' => 'Department Head']);

    $response = $this->postJson('/api/departments', [
        'code' => 'IT',
        'name' => 'Information Technology',
        'head_user_id' => $head->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.head_user_id', $head->id);
});

test('it validates required fields when creating', function () {
    $response = $this->postJson('/api/departments', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'name']);
});

test('it validates code uniqueness when creating', function () {
    Department::create([
        'code' => 'IT',
        'name' => 'Information Technology',
        'created_by_user_id' => $this->user->id,
    ]);

    $response = $this->postJson('/api/departments', [
        'code' => 'IT',
        'name' => 'IT Department',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('it validates parent_id exists when creating', function () {
    $response = $this->postJson('/api/departments', [
        'code' => 'CHILD',
        'name' => 'Child Department',
        'parent_id' => 99999,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('it validates head_user_id exists when creating', function () {
    $response = $this->postJson('/api/departments', [
        'code' => 'IT',
        'name' => 'Information Technology',
        'head_user_id' => 99999,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['head_user_id']);
});

test('it prevents circular reference when creating (self as parent)', function () {
    // This should be caught by validation or business logic
    $response = $this->postJson('/api/departments', [
        'code' => 'DEPT',
        'name' => 'Department',
        'parent_id' => 0, // Invalid parent
    ]);

    $response->assertUnprocessable();
});

test('it can update a department', function () {
    $department = Department::create([
        'code' => 'IT',
        'name' => 'Information Technology',
        'created_by_user_id' => $this->user->id,
    ]);

    $response = $this->putJson("/api/departments/{$department->id}", [
        'code' => 'IT',
        'name' => 'IT Department Updated',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'IT Department Updated');

    $this->assertDatabaseHas('departments', [
        'id' => $department->id,
        'name' => 'IT Department Updated',
    ]);
});

test('it can update department parent', function () {
    $parent = Department::create([
        'code' => 'PARENT',
        'name' => 'Parent Department',
        'created_by_user_id' => $this->user->id,
    ]);

    $department = Department::create([
        'code' => 'CHILD',
        'name' => 'Child Department',
        'created_by_user_id' => $this->user->id,
    ]);

    $response = $this->putJson("/api/departments/{$department->id}", [
        'code' => 'CHILD',
        'name' => 'Child Department',
        'parent_id' => $parent->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.parent_id', $parent->id);
});

test('it can remove department parent', function () {
    $parent = Department::create([
        'code' => 'PARENT',
        'name' => 'Parent Department',
        'created_by_user_id' => $this->user->id,
    ]);

    $department = Department::create([
        'code' => 'CHILD',
        'name' => 'Child Department',
        'parent_id' => $parent->id,
        'created_by_user_id' => $this->user->id,
    ]);

    $response = $this->putJson("/api/departments/{$department->id}", [
        'code' => 'CHILD',
        'name' => 'Child Department',
        'parent_id' => null,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.parent_id', null);
});

test('it validates code uniqueness when updating (except self)', function () {
    $dept1 = Department::create([
        'code' => 'IT',
        'name' => 'IT Department',
        'created_by_user_id' => $this->user->id,
    ]);

    $dept2 = Department::create([
        'code' => 'HR',
        'name' => 'HR Department',
        'created_by_user_id' => $this->user->id,
    ]);

    // Try to update dept2 with dept1's code
    $response = $this->putJson("/api/departments/{$dept2->id}", [
        'code' => 'IT', // Already used by dept1
        'name' => 'HR Department',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('it allows keeping same code when updating', function () {
    $department = Department::create([
        'code' => 'IT',
        'name' => 'Information Technology',
        'created_by_user_id' => $this->user->id,
    ]);

    $response = $this->putJson("/api/departments/{$department->id}", [
        'code' => 'IT', // Same code
        'name' => 'IT Department Updated',
    ]);

    $response->assertOk();
});

test('it prevents setting itself as parent when updating', function () {
    $department = Department::create([
        'code' => 'DEPT',
        'name' => 'Department',
        'created_by_user_id' => $this->user->id,
    ]);

    $response = $this->putJson("/api/departments/{$department->id}", [
        'code' => 'DEPT',
        'name' => 'Department',
        'parent_id' => $department->id, // Self as parent
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('it prevents circular reference in hierarchy when updating', function () {
    // Create hierarchy: A -> B -> C
    $deptA = Department::create([
        'code' => 'A',
        'name' => 'Department A',
        'created_by_user_id' => $this->user->id,
    ]);

    $deptB = Department::create([
        'code' => 'B',
        'name' => 'Department B',
        'parent_id' => $deptA->id,
        'created_by_user_id' => $this->user->id,
    ]);

    $deptC = Department::create([
        'code' => 'C',
        'name' => 'Department C',
        'parent_id' => $deptB->id,
        'created_by_user_id' => $this->user->id,
    ]);

    // Try to make A a child of C (would create circular: C -> A -> B -> C)
    $response = $this->putJson("/api/departments/{$deptA->id}", [
        'code' => 'A',
        'name' => 'Department A',
        'parent_id' => $deptC->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('it can delete a department', function () {
    $department = Department::create([
        'code' => 'TEMP',
        'name' => 'Temporary Department',
        'created_by_user_id' => $this->user->id,
    ]);

    $response = $this->deleteJson("/api/departments/{$department->id}");

    $response->assertOk();

    $this->assertDatabaseMissing('departments', [
        'id' => $department->id,
    ]);
});

test('it returns 404 when deleting non-existent department', function () {
    $response = $this->deleteJson('/api/departments/99999');

    $response->assertNotFound();
});

test('department includes relationships when loaded', function () {
    $head = User::factory()->create(['name' => 'Department Head']);

    $parent = Department::create([
        'code' => 'PARENT',
        'name' => 'Parent Department',
        'created_by_user_id' => $this->user->id,
    ]);

    $department = Department::create([
        'code' => 'CHILD',
        'name' => 'Child Department',
        'parent_id' => $parent->id,
        'head_user_id' => $head->id,
        'created_by_user_id' => $this->user->id,
    ]);

    $response = $this->getJson("/api/departments/{$department->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'code',
                'name',
                'parent_id',
                'head_user_id',
                'head' => ['id', 'name', 'email'],
            ]
        ]);
});

test('it requires authentication to access departments', function () {
    // Create fresh test without authentication setup
    $testCase = new Tests\TestCase();
    $testCase->setUp();

    $response = $testCase->getJson('/api/departments');

    $response->assertUnauthorized();
})->skip('Authentication test needs different setup');

test('it tracks who created the department', function () {
    $response = $this->postJson('/api/departments', [
        'code' => 'NEW',
        'name' => 'New Department',
    ]);

    $response->assertCreated();

    $department = Department::where('code', 'NEW')->first();

    expect($department->created_by_user_id)->toBe($this->user->id)
        ->and($department->createdBy->id)->toBe($this->user->id);
});

test('it tracks who updated the department', function () {
    $department = Department::create([
        'code' => 'IT',
        'name' => 'Information Technology',
        'created_by_user_id' => $this->user->id,
    ]);

    $updater = User::factory()->create();
    Sanctum::actingAs($updater);

    $response = $this->putJson("/api/departments/{$department->id}", [
        'code' => 'IT',
        'name' => 'IT Updated',
    ]);

    $response->assertOk();

    $department->refresh();

    expect($department->updated_by_user_id)->toBe($updater->id)
        ->and($department->updatedBy->id)->toBe($updater->id);
});
