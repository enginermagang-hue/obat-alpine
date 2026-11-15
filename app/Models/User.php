<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Traits\LogsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'fasilitas_kesehatan_id', 'google_login_enabled'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use HasRoles;
    use LogsActivity;

    public function fasilitasKesehatan(): BelongsTo
    {
        return $this->belongsTo(FasilitasKesehatan::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function socialiteUsers(): HasMany
    {
        return $this->hasMany(SocialiteUser::class);
    }

    public function initials(): string
    {
        return (string) str($this->name)
            ->explode(' ')
            ->filter()
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->implode('');
    }

    public function avatarUrl(): ?string
    {
        $prefs = $this->preferences;

        if (! $prefs) {
            return null;
        }

        if ($prefs->avatar_type === 'dicebear') {
            $style = $prefs->avatar_dicebear_style ?? 'avataaars';
            $seed = urlencode($this->email);

            return "https://api.dicebear.com/10.x/{$style}/svg?seed={$seed}";
        }

        if ($prefs->avatar_type === 'preset' && $prefs->avatar_path) {
            return asset($prefs->avatar_path);
        }

        if ($prefs->avatar_type === 'upload' && $prefs->avatar_path) {
            return asset('storage/'.$prefs->avatar_path);
        }

        return null;
    }

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
            'google_login_enabled' => 'boolean',
            'last_active_at' => 'datetime',
        ];
    }

    public function getActivitylogName(): string
    {
        return 'user_management';
    }
}
