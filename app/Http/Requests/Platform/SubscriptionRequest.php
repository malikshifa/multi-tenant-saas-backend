<?php

namespace App\Http\Requests\Platform;

use App\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'integer',
                'exists:tenants,id',
            ],

            'plan_id' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'integer',
                'exists:plans,id',
            ],

            'status' => [
                'sometimes',
                Rule::enum(SubscriptionStatus::class),
            ],

            'starts_at' => [
                'sometimes',
                'date',
            ],

        ];
    }
}
