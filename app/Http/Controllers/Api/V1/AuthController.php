<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Flat;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($validated['login']);

        $user = User::where('email', $login)
            ->orWhereHas('owner', function (Builder $query) use ($login): void {
                $query->where('phone', $login);
            })
            ->orWhereHas('tenant', function (Builder $query) use ($login): void {
                $query->where('phone', $login);
            })
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            app(AuditService::class)->logAuth(
                action: 'FAILED_LOGIN',
                user: $user,
                description: "Failed mobile login attempt for {$login}",
                details: ['login' => $login],
            );

            return ApiResponse::error(__('auth.failed'), 401);
        }

        $token = $user->createToken('resident-mobile-app')->plainTextToken;
        $flats = $this->getResidentFlats($user);

        app(AuditService::class)->logAuth(
            action: 'LOGIN',
            user: $user,
            description: "Resident {$user->email} logged in via mobile app",
        );

        $phone = $user->owner !== null ? $user->owner->phone : $user->tenant?->phone;

        return ApiResponse::success([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $phone,
                'is_owner' => $user->isOwner(),
                'is_tenant' => $user->isTenant(),
            ],
            'flats' => $flats,
        ], 'Login successful');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $flats = $this->getResidentFlats($user);
        $phone = $user->owner !== null ? $user->owner->phone : $user->tenant?->phone;

        return ApiResponse::success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $phone,
                'is_owner' => $user->isOwner(),
                'is_tenant' => $user->isTenant(),
            ],
            'flats' => $flats,
        ], 'Profile retrieved');
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        app(AuditService::class)->logAuth(
            action: 'LOGOUT',
            user: $user,
            description: "Resident {$user->email} logged out via mobile app",
        );

        $user->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logged out successfully');
    }

    public function registerFcmToken(Request $request): JsonResponse
    {
        // Backward compatibility: proxy to new device registration
        return app(DeviceApiController::class)->register($request);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('The provided password does not match your current password.')],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        app(AuditService::class)->logAuth(
            action: 'PASSWORD_CHANGE',
            user: $user,
            description: "Resident {$user->email} updated password via mobile app",
        );

        return ApiResponse::success(null, 'Password updated successfully');
    }

    /**
     * @return Collection<int, array{id: int, number: string, floor: string, building_id: int, building_name: string}>
     */
    private function getResidentFlats(User $user): Collection
    {
        $flats = collect();

        if ($user->owner) {
            $flats = $user->owner->flats()->with(['building', 'floor'])->orderBy('number')->get();
        }

        if ($user->tenant?->flat) {
            $tenantFlat = $user->tenant->flat;
            $tenantFlat->loadMissing(['building', 'floor']);
            if (! $flats->contains('id', $tenantFlat->id)) {
                $flats->push($tenantFlat);
            }
        }

        return $flats->map(fn (Flat $flat): array => [
            'id' => $flat->id,
            'number' => $flat->number,
            'floor' => $flat->floor !== null ? $flat->floor->name : '',
            'building_id' => $flat->building_id,
            'building_name' => $flat->building !== null ? $flat->building->name : '',
        ])->values();
    }
}
