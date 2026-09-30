<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('POST');
        $product = $this->route('product');

        return [
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'sku' => [
                $isCreate ? 'required' : 'sometimes',
                'string',
                'max:100',
                Rule::unique('tenant.products', 'sku')->ignore($product),
            ],
            'description' => ['nullable', 'string'],
            'price' => [$isCreate ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
