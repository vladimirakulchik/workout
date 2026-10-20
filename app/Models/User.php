<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Lab404\Impersonate\Models\Impersonate;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property-read string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property UserStatus $status
 * @property string|null $phone
 * @property string|null $photo_path
 * @property bool $must_change_password
 * @property Carbon|null $last_login_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['first_name', 'last_name', 'email', 'password', 'role', 'status', 'phone', 'photo_path', 'must_change_password', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Impersonate, LogsActivity, Notifiable;

    /**
     * @var list<string>
     */
    protected $appends = ['name'];

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
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Audit log of user create/update; credentials are never logged.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['first_name', 'last_name', 'email', 'role', 'status', 'phone', 'must_change_password'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * The user's full name (first + last).
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * Name to show in lists and history; anonymized users read "Deleted User".
     */
    public function displayName(): string
    {
        return $this->status === UserStatus::Deleted ? 'Deleted User' : $this->name;
    }

    /**
     * Public URL of the profile photo, or the default avatar when none is set.
     */
    public function photoUrl(): string
    {
        return $this->photo_path !== null
            ? Storage::disk('public')->url($this->photo_path)
            : asset('images/default-avatar.svg');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isTrainer(): bool
    {
        return $this->role === UserRole::Trainer;
    }

    public function isCoach(): bool
    {
        return $this->role === UserRole::Coach;
    }

    public function isPlayer(): bool
    {
        return $this->role === UserRole::Player;
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeOfRole(Builder $query, UserRole $role): void
    {
        $query->where('role', $role->value);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', UserStatus::Active->value);
    }

    /**
     * Match first name, last name, email or phone with a bound LIKE.
     *
     * @param  Builder<User>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '\\%_').'%';

        $query->where(function (Builder $query) use ($like): void {
            $query->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }

    /**
     * @return HasOne<TrainerProfile, $this>
     */
    public function trainerProfile(): HasOne
    {
        return $this->hasOne(TrainerProfile::class);
    }

    /**
     * @return HasOne<CoachProfile, $this>
     */
    public function coachProfile(): HasOne
    {
        return $this->hasOne(CoachProfile::class);
    }

    /**
     * Player profiles this login owns (self profile, or the child profile it logs in as).
     *
     * @return HasMany<PlayerProfile, $this>
     */
    public function playerProfiles(): HasMany
    {
        return $this->hasMany(PlayerProfile::class, 'user_id');
    }

    /**
     * Child profiles managed by this user as parent.
     *
     * @return HasMany<PlayerProfile, $this>
     */
    public function childProfiles(): HasMany
    {
        return $this->hasMany(PlayerProfile::class, 'parent_user_id');
    }

    /**
     * The player profile this login belongs to.
     *
     * @return HasOne<PlayerProfile, $this>
     */
    public function selfPlayerProfile(): HasOne
    {
        return $this->hasOne(PlayerProfile::class, 'user_id');
    }

    /**
     * Coach logins attached to this trainer.
     *
     * @return HasManyThrough<User, CoachProfile, $this>
     */
    public function coaches(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, CoachProfile::class, 'trainer_id', 'id', 'id', 'user_id');
    }

    /**
     * Whether this login belongs to a child player profile.
     */
    public function isChildAccount(): bool
    {
        return (bool) $this->selfPlayerProfile?->is_child;
    }

    public function canImpersonate(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->isSuperAdmin() && $this->status === UserStatus::Active;
    }
}
