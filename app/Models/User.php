<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar_color',
        'is_active',
        'last_login_at'
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean'
        ];
    }

    /**
     * Get leads assigned to this user
     */
    public function leads()
    {
        return $this->hasMany(Lead::class, 'assignee_id');
    }

    /**
     * Get leads created by this user
     */
    public function createdLeads()
    {
        return $this->hasMany(Lead::class, 'created_by');
    }

    /**
     * Get user activities
     */
    public function activities()
    {
        return $this->hasMany(UserActivity::class);
    }

    /**
     * Get notes written by this user
     */
    public function notes()
    {
        return $this->hasMany(Note::class, 'author_id');
    }

    /**
     * Get lead activities performed by this user
     */
    public function leadActivities()
    {
        return $this->hasMany(LeadActivity::class);
    }

    /**
     * Get attachments uploaded by this user
     */
    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is agent
     */
    public function isAgent(): bool
    {
        return $this->role === 'agent';
    }

    /**
     * Get user initials for avatar
     */
    public function getInitialsAttribute(): string
    {
        $nameParts = explode(' ', $this->name);
        return strtoupper(
            (isset($nameParts[0]) ? $nameParts[0][0] : '') .
            (isset($nameParts[1]) ? $nameParts[1][0] : '')
        );
    }
}