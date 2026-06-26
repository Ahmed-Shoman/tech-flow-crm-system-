<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'tech_support_phone',
        'store_link',
        'auth_status',
        'social_media',
        'source',
        'budget',
        'priority',
        'stage',
        'assignee_id',
        'created_by'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Get the assignee (user who is assigned to this lead)
     */
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Get the creator (user who created this lead)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get notes for this lead
     */
    public function notes()
    {
        return $this->hasMany(Note::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get activities for this lead
     */
    public function activities()
    {
        return $this->hasMany(LeadActivity::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get attachments for this lead
     */
    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Get user activities related to this lead
     */
    public function userActivities()
    {
        return $this->hasMany(UserActivity::class);
    }

    /**
     * Scope: Filter by stage
     */
    public function scopeByStage($query, $stage)
    {
        return $query->where('stage', $stage);
    }

    /**
     * Scope: Filter by assignee
     */
    public function scopeByAssignee($query, $assigneeId)
    {
        return $query->where('assignee_id', $assigneeId);
    }

    /**
     * Scope: Filter by priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope: Search by name or email
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    /**
     * Get formatted budget
     */
    public function getFormattedBudgetAttribute()
    {
        return '$' . number_format($this->budget, 2);
    }

    /**
     * Get stage label
     */
    public function getStageDisplayAttribute()
    {
        return match($this->stage) {
            'new' => 'New Lead',
            'attempted' => 'Attempted to Contact',
            'negotiation' => 'In Negotiation',
            'followup' => 'Follow-up',
            'won' => 'Closed Won',
            'lost' => 'Closed Lost',
            default => ucfirst($this->stage)
        };
    }

    /**
     * Get priority color
     */
    public function getPriorityColorAttribute()
    {
        return match($this->priority) {
            'low' => '#6b7280',
            'medium' => '#f59e0b',
            'high' => '#ef4444',
            default => '#6b7280'
        };
    }
}