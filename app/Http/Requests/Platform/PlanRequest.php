<?php

namespace App\Http\Requests\Platform;

use App\Enums\BillingInterval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'name' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:255',
            ],

            'slug' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('plans', 'slug')
                    ->ignore($plan),
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'price' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'numeric',
                'min:0',
            ],

            'billing_interval' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                Rule::enum(BillingInterval::class),
            ],

            'trial_days' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'integer',
                'min:0',
            ],

            'max_users' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
