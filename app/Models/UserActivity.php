<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserActivity extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'lead_id',
        'metadata'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Get the user who performed this activity
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the lead related to this activity (if any)
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Scope: Filter by action type
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope: Filter by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Recent activities
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: Today's activities
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Get action display name
     */
    public function getActionDisplayAttribute()
    {
        return match($this->action) {
            'login' => 'Logged In',
            'logout' => 'Logged Out',
            'create_lead' => 'Created Lead',
            'update_lead' => 'Updated Lead',
            'assign_lead' => 'Assigned Lead',
            'add_note' => 'Added Note',
            'upload_file' => 'Uploaded File',
            'stage_change' => 'Changed Stage',
            'view_analytics' => 'Viewed Analytics',
            'view_lead' => 'Viewed Lead',
            'print_report' => 'Printed Report',
            default => ucfirst(str_replace('_', ' ', $this->action))
        };
    }

    /**
     * Get formatted date
     */
    public function getFormattedDateAttribute()
    {
        return $this->created_at->format('M j, Y g:i A');
    }

    /**
     * Get browser from user agent
     */
    public function getBrowserAttribute()
    {
        if (!$this->user_agent) return 'Unknown';
        
        if (str_contains($this->user_agent, 'Chrome')) return 'Chrome';
        if (str_contains($this->user_agent, 'Firefox')) return 'Firefox';
        if (str_contains($this->user_agent, 'Safari')) return 'Safari';
        if (str_contains($this->user_agent, 'Edge')) return 'Edge';
        
        return 'Unknown';
    }
}