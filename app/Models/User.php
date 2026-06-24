<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $primaryKey = 'firebase_uid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'firebase_uid',
        'first_name',
        'last_name',
        'email',
        'password',
        'bio',
        'image_url',
        'company_name',
        'industry',
        'position',
        'is_blocked',
        'is_deleted',
        'fcm_token',
        'email_verified_at',
        'prefered_locale',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_blocked' => 'boolean',
        'is_deleted' => 'boolean',
        'prefered_locale' => 'string',
    ];

    /**
     * Relationship: Single Role
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_firebase_uid', 'role_id')
            ->withPivot(['created_by', 'updated_by'])
            ->withTimestamps();
    }

    /**
     * Helper to get the single role
     */
    public function getRoleAttribute()
    {
        return $this->roles->first();
    }

    public function roleNames(): array
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->pluck('name')->all();
        }

        return $this->roles()->pluck('name')->all();
    }

    public function isRole(int|string|array $roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Check if user has a specific role (by ID or name).
     */
    public function hasRole(int|string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        if (is_int($roles[0]) || (is_string($roles[0]) && ctype_digit($roles[0]))) {
            return $this->roles()->whereIn('roles.id', array_map('intval', $roles))->exists();
        }

        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Check if user has a specific permission (via role).
     */
    public function hasPermission(string $permission): bool
    {
        return $this->roles()->whereHas('permissions', function ($query) use ($permission) {
            $query->where('permission', $permission);
        })->exists();
    }

    public function industries()
    {
        return $this->belongsToMany(Industry::class, 'user_industries', 'user_firebase_uid', 'industry_id')
            ->withPivot('is_primary');
    }

    public function salesRepProfile()
    {
        return $this->hasOne(SalesRepUser::class, 'user_firebase_uid', 'firebase_uid');
    }

    public function address()
    {
        return $this->hasOne(Address::class, 'user_firebase_uid', 'firebase_uid');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'firebase_uid');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'firebase_uid');
    }

    public function teamMembers()
    {
        return $this->belongsToMany(User::class, 'manager_team_members', 'manager_firebase_uid', 'member_firebase_uid')
            ->withPivot(['manager_type_role_id', 'active', 'joined_at', 'left_at', 'created_by', 'updated_by'])
            ->wherePivot('active', true)
            ->wherePivotNull('left_at');
    }

    public function managers()
    {
        return $this->belongsToMany(User::class, 'manager_team_members', 'member_firebase_uid', 'manager_firebase_uid')
            ->withPivot(['manager_type_role_id', 'active', 'joined_at', 'left_at', 'created_by', 'updated_by'])
            ->wherePivot('active', true)
            ->wherePivotNull('left_at');
    }

    public function manages(string $memberUid): bool
    {
        return $this->teamMembers()->where('member_firebase_uid', $memberUid)->exists();
    }

    public function teamInvitesReceived(): HasMany
    {
        return $this->hasMany(Team::class, 'target_user_firebase_uid', 'firebase_uid');
    }

    public function favoriteUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_favorites', 'user_firebase_uid', 'favorite_user_firebase_uid')
            ->withPivot(['created_by', 'updated_by'])
            ->withTimestamps();
    }

    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_favorites', 'favorite_user_firebase_uid', 'user_firebase_uid')
            ->withPivot(['created_by', 'updated_by'])
            ->withTimestamps();
    }
}
