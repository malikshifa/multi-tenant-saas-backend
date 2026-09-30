<?php

namespace App\Http\Requests\Platform;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subscription_id' => [
                'required',
                'integer',
                'exists:subscriptions,id',
            ],

            'payment_method' => [
                'required',
                Rule::enum(PaymentMethod::class),
            ],

            'currency' => [
                'sometimes',
                'string',
                'size:3',
            ],
        ];
    }
}
