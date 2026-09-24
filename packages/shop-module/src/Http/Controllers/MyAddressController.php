<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\Customer;
use Nreljed\ShopModule\Models\CustomerAddress;

class MyAddressController extends Controller
{
    public function index(Request $request)
    {
        $customer = $this->customerFor($request);

        $addresses = $customer
            ? $customer->addresses()->orderBy('created_at', 'desc')->get()->map(fn (CustomerAddress $a) => $this->shape($a))
            : collect();

        return Inertia::render('shop/my/addresses/index', [
            'addresses' => $addresses,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $customer = $this->customerFor($request);

        if (! $customer) {
            abort(403, 'No customer profile linked to this account.');
        }

        $address = $customer->addresses()->create([
            ...$data,
            'code' => 'addr-'.strtoupper(Str::random(8)),
        ]);

        if ($data['is_primary']) {
            $this->clearPrimary($customer, $address->id);
        }

        return redirect()->back()->with('success', 'Address added.');
    }

    public function update(Request $request, CustomerAddress $address)
    {
        $this->authorizeAddress($request, $address);

        $data = $this->validated($request);
        $address->update($data);

        if ($data['is_primary']) {
            $this->clearPrimary($address->customer, $address->id);
        }

        return redirect()->back()->with('success', 'Address updated.');
    }

    public function destroy(Request $request, CustomerAddress $address)
    {
        $this->authorizeAddress($request, $address);

        $address->delete();

        return redirect()->back()->with('success', 'Address removed.');
    }

    public function setPrimary(Request $request, CustomerAddress $address)
    {
        $this->authorizeAddress($request, $address);

        $this->clearPrimary($address->customer, $address->id);
        $address->update(['is_primary' => true]);

        return redirect()->back()->with('success', 'Primary address updated.');
    }

    private function customerFor(Request $request): ?Customer
    {
        return Customer::query()
            ->whereHas('users', fn ($q) => $q->whereKey($request->user()->getKey()))
            ->first();
    }

    private function authorizeAddress(Request $request, CustomerAddress $address): void
    {
        $customer = $this->customerFor($request);

        abort_if(! $customer || $address->customer_id !== $customer->id, 403);
    }

    private function clearPrimary(Customer $customer, int $exceptId): void
    {
        $customer->addresses()
            ->where('id', '!=', $exceptId)
            ->update(['is_primary' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:billing,shipping'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_code' => ['required', 'string', 'max:2'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(CustomerAddress $a): array
    {
        return [
            'id' => $a->id,
            'code' => $a->code,
            'type' => $a->type,
            'line1' => $a->line1,
            'line2' => $a->line2,
            'city' => $a->city,
            'postal_code' => $a->postal_code,
            'country_code' => $a->country_code,
            'is_primary' => (bool) $a->is_primary,
        ];
    }
}