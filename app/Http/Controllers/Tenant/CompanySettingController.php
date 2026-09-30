<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CompanySettingRequest;
use App\Models\Tenant\Setting;

class CompanySettingController extends Controller
{
    public function show()
    {
        return response()->json([
            'data' => Setting::group('company'),
        ]);
    }

    public function update(CompanySettingRequest $request)
    {
        $validated = $request->validated();

        Setting::setGroup(
            'company',
            $validated
        );

        return response()->json([
            'message' => 'Company settings updated successfully.',
            'data' => Setting::group('company'),
        ]);
    }
}
