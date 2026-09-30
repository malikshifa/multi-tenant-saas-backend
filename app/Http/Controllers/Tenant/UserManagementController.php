<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AcceptInviteRequest;
use App\Http\Requests\Tenant\AssignRoleRequest;
use App\Http\Requests\Tenant\InviteUserRequest;
use App\Http\Resources\Tenant\UserResource;
use App\Models\Tenant\User;
use App\Services\Tenant\UserInvitationService;

class UserManagementController extends Controller
{
    public function __construct(
        protected UserInvitationService $service
    ) {}

    public function index()
    {
        return UserResource::collection($this->service->listUsers());
    }

    public function invite(InviteUserRequest $request)
    {
        $data = $request->validated();

        $user = $this->service->invite(
            $request->attributes->get('tenant'),
            $data['name'],
            $data['email'],
            $data['role'] ?? null
        );

        return response()->json([
            'message' => 'Invitation sent.',
            'data' => UserResource::make($user),
        ], 201);
    }

    public function acceptInvite(AcceptInviteRequest $request)
    {
        $data = $request->validated();

        $this->service->acceptInvite($data['email'], $data['token'], $data['password']);

        return response()->json([
            'message' => 'Invitation accepted. You can now log in.',
        ]);
    }

    public function assignRole(AssignRoleRequest $request, User $user)
    {
        $user = $this->service->assignRole($user, $request->validated('role'));

        return response()->json([
            'message' => 'Role assigned successfully.',
            'data' => UserResource::make($user),
        ]);
    }

    public function deactivate(User $user)
    {
        $user = $this->service->deactivate($user);

        return response()->json([
            'message' => 'User deactivated successfully.',
            'data' => UserResource::make($user),
        ]);
    }
}
