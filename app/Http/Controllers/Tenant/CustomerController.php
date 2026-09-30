<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CustomerRequest;
use App\Http\Resources\Tenant\CustomerResource;
use App\Models\Tenant\Customer;
use App\Services\Tenant\CustomerService;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $service
    ) {}

    public function index()
    {
        return CustomerResource::collection(Customer::latest()->paginate(20));
    }

    public function store(CustomerRequest $request)
    {
        $customer = $this->service->create($request->validated());

        return response()->json([
            'message' => 'Customer created successfully.',
            'data' => CustomerResource::make($customer),
        ], 201);
    }

    public function show(Customer $customer)
    {
        return response()->json([
            'data' => CustomerResource::make($customer),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer)
    {
        $customer = $this->service->update($customer, $request->validated());

        return response()->json([
            'message' => 'Customer updated successfully.',
            'data' => CustomerResource::make($customer),
        ]);
    }

    public function destroy(Customer $customer)
    {
        $this->service->delete($customer);

        return response()->json([
            'message' => 'Customer deleted successfully.',
        ]);
    }
}
