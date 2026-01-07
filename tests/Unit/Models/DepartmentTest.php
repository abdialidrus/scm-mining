<?php

use App\Models\Department;
use App\Models\User;

beforeEach(function () {
    // Create a test user for created_by/updated_by fields
    $this->user = User::factory()->create();
});

test('it can create a department with valid data', function () {
    $department = Department::create([
        'code' => 'IT',
        'name' => 'Information Technology',
        'created_by_user_id' => $this->user->id,
    ]);

    expect($department)->toBeInstanceOf(Department::class)
        ->and($department->code)->toBe('IT')
        ->and($department->name)->toBe('Information Technology');
});

test('it requires a code', function () {
    expect(fn() => Department::create([
        'name' => 'Test Department',
        'created_by_user_id' => $this->user->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('it requires a name', function () {
    expect(fn() => Department::create([
        'code' => 'TEST',
        'created_by_user_id' => $this->user->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('code must be unique', function () {
    Department::create([
        'code' => 'IT',
        'name' => 'Information Technology',
        'created_by_user_id' => $this->user->id,
    ]);

    expect(fn() => Department::create([
        'code' => 'IT',
        'name' => 'IT Department',
        'created_by_user_id' => $this->user->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('it can have a parent department', function () {
    $parent = Department::create([
        'code' => 'PARENT',
        'name' => 'Parent Department',
        'created_by_user_id' => $this->user->id,
    ]);

    $child = Department::create([
        'code' => 'CHILD',
        'name' => 'Child Department',
        'parent_id' => $parent->id,
        'created_by_user_id' => $this->user->id,
    ]);

    expect($child->parent)->toBeInstanceOf(Department::class)
        ->and($child->parent->id)->toBe($parent->id)
        ->and($child->parent->code)->toBe('PARENT');
});

test('it can have multiple children departments', function () {
    $parent = Department::create([
        'code' => 'PARENT',
        'name' => 'Parent Department',
        'created_by_user_id' => $this->user->id,
    ]);

    $child1 = Department::create([
        'code' => 'CHILD1',
        'name' => 'Child Department 1',
        'parent_id' => $parent->id,
        'created_by_user_id' => $this->user->id,
    ]);

    $child2 = Department::create([
        'code' => 'CHILD2',
        'name' => 'Child Department 2',
        'parent_id' => $parent->id,
        'created_by_user_id' => $this->user->id,
    ]);

    $parent->load('children');

    expect($parent->children)->toHaveCount(2)
        ->and($parent->children->pluck('code')->toArray())->toBe(['CHILD1', 'CHILD2']);
});

test('it can have a head user', function () {
    $head = User::factory()->create(['name' => 'Department Head']);

    $department = Department::create([
        'code' => 'IT',
        'name' => 'Information Technology',
        'head_user_id' => $head->id,
        'created_by_user_id' => $this->user->id,
    ]);

    expect($department->head)->toBeInstanceOf(User::class)
        ->and($department->head->id)->toBe($head->id)
        ->and($department->head->name)->toBe('Department Head');
});

test('it has createdBy relationship', function () {
    $department = Department::create([
        'code' => 'IT',
        'name' => 'Information Technology',
        'created_by_user_id' => $this->user->id,
    ]);

    $department->load('createdBy');

    expect($department->createdBy)->toBeInstanceOf(User::class)
        ->and($department->createdBy->id)->toBe($this->user->id);
});

test('it has updatedBy relationship', function () {
    $updater = User::factory()->create();

    $department = Department::create([
        'code' => 'IT',
        'name' => 'Information Technology',
        'created_by_user_id' => $this->user->id,
        'updated_by_user_id' => $updater->id,
    ]);

    $department->load('updatedBy');

    expect($department->updatedBy)->toBeInstanceOf(User::class)
        ->and($department->updatedBy->id)->toBe($updater->id);
});

test('parent_id can be null for root departments', function () {
    $department = Department::create([
        'code' => 'ROOT',
        'name' => 'Root Department',
        'parent_id' => null,
        'created_by_user_id' => $this->user->id,
    ]);

    expect($department->parent_id)->toBeNull()
        ->and($department->parent)->toBeNull();
});

test('it allows multiple root departments', function () {
    $dept1 = Department::create([
        'code' => 'ROOT1',
        'name' => 'Root Department 1',
        'parent_id' => null,
        'created_by_user_id' => $this->user->id,
    ]);

    $dept2 = Department::create([
        'code' => 'ROOT2',
        'name' => 'Root Department 2',
        'parent_id' => null,
        'created_by_user_id' => $this->user->id,
    ]);

    expect($dept1->parent_id)->toBeNull()
        ->and($dept2->parent_id)->toBeNull();
});

test('it can build nested hierarchy', function () {
    $root = Department::create([
        'code' => 'ROOT',
        'name' => 'Root',
        'created_by_user_id' => $this->user->id,
    ]);

    $level1 = Department::create([
        'code' => 'L1',
        'name' => 'Level 1',
        'parent_id' => $root->id,
        'created_by_user_id' => $this->user->id,
    ]);

    $level2 = Department::create([
        'code' => 'L2',
        'name' => 'Level 2',
        'parent_id' => $level1->id,
        'created_by_user_id' => $this->user->id,
    ]);

    expect($level2->parent->parent->id)->toBe($root->id)
        ->and($root->children->first()->children->first()->code)->toBe('L2');
});
