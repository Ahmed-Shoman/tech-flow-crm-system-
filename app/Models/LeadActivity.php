<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadActivity extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'lead_id',
        'user_id',
        'action',
        'description',
        'old_value',
        'new_value',
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
     * Get the lead this activity belongs to
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the user who performed this activity
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get attachments for this activity
     */
    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'activity_id');
    }

    /**
     * Scope: Filter by action type
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope: Recent activities
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get action display name
     */
    public function getActionDisplayAttribute()
    {
        return match($this->action) {
            'create' => 'Created Lead',
            'stage_change' => 'Changed Stage',
            'assign' => 'Assignment',
            'note' => 'Added Note',
            'call' => 'Phone Call',
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
}