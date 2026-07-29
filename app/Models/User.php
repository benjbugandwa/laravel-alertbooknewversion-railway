<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'job_title',
        'is_active',
        'org_id',
        'avatar_url',
        'code_province',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'org_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'code_province', 'code_province')
            ->withoutGlobalScope('active');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'roles_users', 'user_id', 'role_id')
            ->withTimestamps();
    }

    public function monitorAssignments(): HasMany
    {
        return $this->hasMany(MonitorSupervisorAssignment::class, 'monitor_id');
    }

    public function supervisedMonitorAssignments(): HasMany
    {
        return $this->hasMany(MonitorSupervisorAssignment::class, 'supervisor_id');
    }

    public function hasRole(string $slug): bool
    {
        if (! $this->rolesSchemaAvailable()) {
            return false;
        }

        $this->loadMissing('roles');

        return $this->roles->contains('slug', $slug);
    }

    public function hasAnyRole(array $slugs): bool
    {
        if (! $this->rolesSchemaAvailable()) {
            return false;
        }

        $this->loadMissing('roles');

        return $this->roles->whereIn('slug', $slugs)->isNotEmpty();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    public function hasEffectiveRole(string $slug): bool
    {
        return $this->hasRole($slug);
    }

    public function hasAnyEffectiveRole(array $slugs): bool
    {
        return $this->hasAnyRole($slugs);
    }

    public function effectiveRole(): ?string
    {
        if (! $this->rolesSchemaAvailable()) {
            return null;
        }

        $this->loadMissing('roles');

        return $this->roles->first()?->slug;
    }

    /**
     * Cache statique du résultat pour éviter des appels SQL répétés
     * (au moins 4–6 appels par requête HTTP sinon).
     */
    private static ?bool $rolesSchemaAvailableCache = null;

    private function rolesSchemaAvailable(): bool
    {
        if (self::$rolesSchemaAvailableCache !== null) {
            return self::$rolesSchemaAvailableCache;
        }

        try {
            self::$rolesSchemaAvailableCache = Schema::hasTable('roles')
                && Schema::hasColumn('roles', 'slug')
                && Schema::hasTable('roles_users')
                && Schema::hasColumns('roles_users', ['user_id', 'role_id']);
        } catch (\Throwable) {
            // En cas d'erreur DB (connexion, timeout…), on retourne false
            // sans propager l'exception pour éviter un HTTP 500.
            self::$rolesSchemaAvailableCache = false;
        }

        return self::$rolesSchemaAvailableCache;
    }
}
