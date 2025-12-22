<?php

namespace App\Services\Supplier;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function __construct(
        private readonly SupplierCodeGenerator $codeGenerator,
    ) {}

    /**
     * @param array{name:string,contact_name?:string|null,phone?:string|null,email?:string|null,address?:string|null} $data
     */
    public function create(User $actor, array $data): Supplier
    {
        return DB::transaction(function () use ($actor, $data) {
            if (!$actor->hasAnyRole(['super_admin', 'procurement'])) {
                throw new AuthorizationException('Only procurement can create suppliers.');
            }

            $supplier = new Supplier();
            $supplier->code = $this->codeGenerator->generate();
            $supplier->name = trim((string) $data['name']);
            $supplier->contact_name = Arr::get($data, 'contact_name');
            $supplier->phone = Arr::get($data, 'phone');
            $supplier->email = Arr::get($data, 'email');
            $supplier->address = Arr::get($data, 'address');
            $supplier->save();

            return $supplier;
        });
    }

    /**
     * @param array{name?:string|null,contact_name?:string|null,phone?:string|null,email?:string|null,address?:string|null} $data
     */
    public function update(User $actor, int $supplierId, array $data): Supplier
    {
        return DB::transaction(function () use ($actor, $supplierId, $data) {
            if (!$actor->hasAnyRole(['super_admin', 'procurement'])) {
                throw new AuthorizationException('Only procurement can update suppliers.');
            }

            /** @var Supplier $supplier */
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplierId);

            if (array_key_exists('name', $data)) {
                $name = trim((string) ($data['name'] ?? ''));
                if ($name === '') {
                    throw ValidationException::withMessages(['name' => 'Supplier name is required.']);
                }
                $supplier->name = $name;
            }

            if (array_key_exists('contact_name', $data)) {
                $supplier->contact_name = $data['contact_name'];
            }
            if (array_key_exists('phone', $data)) {
                $supplier->phone = $data['phone'];
            }
            if (array_key_exists('email', $data)) {
                $supplier->email = $data['email'];
            }
            if (array_key_exists('address', $data)) {
                $supplier->address = $data['address'];
            }

            $supplier->save();

            return $supplier;
        });
    }

    public function delete(User $actor, int $supplierId): void
    {
        DB::transaction(function () use ($actor, $supplierId) {
            if (!$actor->hasAnyRole(['super_admin', 'procurement'])) {
                throw new AuthorizationException('Only procurement can delete suppliers.');
            }

            Supplier::query()->whereKey($supplierId)->delete();
        });
    }
}
