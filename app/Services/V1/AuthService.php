<?php

namespace App\Services\V1;

use App\Models\Address;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\FirebaseAuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function __construct(
        private readonly FirebaseAuthService $firebase,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * LOGIN
     */
    public function login(string $email, string $password): array
    {
        Log::info('Auth login started', [
            'email' => $email,
        ]);

        $user = $this->users->findByEmail($email);

        // Deleted account
        if ($user?->is_deleted) {
            Log::warning('Deleted account login attempt', [
                'uid' => $user->firebase_uid,
            ]);

            throw new \RuntimeException('Account deleted.');
        }

        // Local login
        if ($user && $user->password && Hash::check($password, $user->password)) {

            if ($user->is_blocked) {
                Log::warning('Blocked account login attempt', [
                    'uid' => $user->firebase_uid,
                ]);

                throw new \RuntimeException('Account blocked.');
            }

            Log::info('Login success (local)', [
                'uid' => $user->firebase_uid,
            ]);

            $user = $user->fresh(['roles', 'industries', 'address', 'salesRepProfile']);

            $token = $user->createToken('api-token', ['*'], now()->addDays(30));

            return [
                'token' => $token->plainTextToken,
                'token_expires_at' => $token->accessToken->expires_at,
                'user' => $user,
            ];
        }

        // Firebase fallback
        Log::info('Fallback to Firebase login', [
            'email' => $email,
        ]);

        $fbData = $this->firebase->signInWithEmailPassword($email, $password);

        $fbUid = $fbData['localId'];

        if (! $user) {

            Log::info('Creating user from Firebase', [
                'uid' => $fbUid,
            ]);

            $user = $this->users->create([
                'firebase_uid' => $fbUid,
                'first_name' => $fbData['displayName'] ?? 'User',
                'email' => strtolower($email),
                'password' => Hash::make($password),
                'created_by' => $fbUid,
                'updated_by' => $fbUid,
            ]);

            // Default role
            $this->assignRole($user, Role::RATER);

        } else {

            Log::info('Syncing Firebase UID', [
                'old_uid' => $user->firebase_uid,
                'new_uid' => $fbUid,
            ]);

            $this->users->update($user, [
                'firebase_uid' => $fbUid,
                'password' => Hash::make($password),
                'updated_by' => $fbUid,
            ]);
        }

        $user = $user->fresh(['roles', 'industries', 'address', 'salesRepProfile']);

        $token = $user->createToken('api-token', ['*'], now()->addDays(30));

        return [
            'token' => $token->plainTextToken,
            'token_expires_at' => $token->accessToken->expires_at,
            'user' => $user,
        ];
    }

    /**
     * REGISTER
     */
    public function register(array $data): array
    {
        Log::info('Auth register started', [
            'email' => strtolower($data['email']),
            'role' => $data['role'],
        ]);

        $user = DB::transaction(function () use ($data) {

            $uid = $this->users->generateUniqueUid(10);

            // Create user
            $user = $this->users->create([
                'firebase_uid' => $uid,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => strtolower($data['email']),
                'password' => Hash::make($data['password']),
                'bio' => $data['bio'] ?? null,
                'company_name' => $data['company_name'] ?? null,
                'position' => $data['position'] ?? null,
                'fcm_token' => $data['fcm_token'] ?? null,
                'created_by' => $uid,
                'updated_by' => $uid,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Role Mapping
            |--------------------------------------------------------------------------
            */
            $roleId = match ((int) $data['role']) {
                1 => Role::RATER,
                2 => Role::REPRESENTATIVE,
                3 => Role::MANAGER_OF_RATERS,
                4 => Role::MANAGER_OF_REPRESENTATIVES,
                default => (int) $data['role'],
            };

            // Assign role
            $this->assignRole($user, $roleId);

            Log::info('Role assigned successfully', [
                'uid' => $user->firebase_uid,
                'role' => $roleId,
            ]);

            // Create sales rep profile
            if ($roleId === Role::REPRESENTATIVE) {
                $this->users->createSalesRepProfile($user->firebase_uid);
            }

            /*
            |--------------------------------------------------------------------------
            | Address
            |--------------------------------------------------------------------------
            */
            if (! empty($data['address'])) {

                Address::create([
                    'user_firebase_uid' => $user->firebase_uid,
                    'country' => $data['address']['country'] ?? null,
                    'state' => $data['address']['state'] ?? null,
                    'city' => $data['address']['city'] ?? null,
                    'postal_code' => $data['address']['postal_code'] ?? null,
                    'address_line_1' => $data['address']['address_line_1'] ?? null,
                    'created_by' => $user->firebase_uid,
                    'updated_by' => $user->firebase_uid,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Industry
            |--------------------------------------------------------------------------
            */
            if (! empty($data['industry'])) {

                $user->industries()->sync([
                    $data['industry'] => [
                        'is_primary' => true,
                        'created_by' => $user->firebase_uid,
                        'updated_by' => $user->firebase_uid,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            }

            return $user->fresh(['roles', 'industries']);
        });

        Log::info('Register completed', [
            'uid' => $user->firebase_uid,
            'roles' => $user->roles->pluck('name')->all(),
        ]);

        $user = $user->fresh(['roles', 'industries', 'address', 'salesRepProfile']);

        $token = $user->createToken('api-token', ['*'], now()->addDays(30));

        return [
            'token' => $token->plainTextToken,
            'token_expires_at' => $token->accessToken->expires_at,
            'user' => $user,
        ];
    }

    /**
     * ASSIGN ROLE
     */
    private function assignRole(User $user, int $roleId): void
    {
        $role = Role::find($roleId);

        if (! $role) {
            throw new \RuntimeException("Role not found: {$roleId}");
        }

        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'created_by' => $user->firebase_uid,
                'updated_by' => $user->firebase_uid,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * SET PASSWORD
     */
    public function setPassword(User $user, string $password): User
    {
        if ($user->password) {
            throw new \RuntimeException('Password already set.');
        }

        $this->users->update($user, [
            'password' => Hash::make($password),
            'updated_by' => $user->firebase_uid,
        ]);

        return $user->fresh();
    }

    /**
     * CHANGE EMAIL
     */
    public function changeEmail(User $user, string $newEmail): User
    {
        $this->users->update($user, [
            'email' => strtolower($newEmail),
            'updated_by' => $user->firebase_uid,
        ]);

        return $user->fresh();
    }

    /**
     * CHANGE PASSWORD
     */
    public function changePassword(User $user, string $newPassword): User
    {
        $this->users->update($user, [
            'password' => Hash::make($newPassword),
            'updated_by' => $user->firebase_uid,
        ]);

        return $user->fresh();
    }

    /**
     * LOGOUT
     */
    public function logout(User $user): void
    {
        Log::info('Logout requested', [
            'uid' => $user->firebase_uid,
        ]);

        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        $token?->delete();
    }

    /**
     * TOKEN RESPONSE
     */
    private function buildTokenPayload(User $user): array
    {
        return [
            'token' => $user->createToken('api-token', ['*'], now()->addDays(30))->plainTextToken,

            'firebase_uid' => $user->firebase_uid,

            'email' => $user->email,
            // 'roles' => $user->roles->pluck('name')->values()->all(),

            // // single role
            // 'role' => $user->roles->first()?->id,

            // // single industry
            // 'industry' => $user->industries->first()?->id,
            // ALL roles
            // 'role' => $user->roles->map(function ($role) {
            //     return [
            //         'id' => $role->id,
            //         'name' => $role->name,
            //     ];
            // }),

            // // ALL industries
            // 'industry' => $user->industries->map(function ($industry) {
            //     return [
            //         'id' => $industry->id,
            //         'name' => $industry->name ?? null,
            //     ];
            // }),

            // SINGLE ROLE (object)
            'role' => $user->roles->first() ? [
                'id' => $user->roles->first()->id,
                'name' => $user->roles->first()->name,
            ] : null,

            // SINGLE INDUSTRY (object)
            'industry' => $user->industries->first() ? [
                'id' => $user->industries->first()->id,
                'name' => $user->industries->first()->name,
            ] : null,
        ];
    }
}
