<?php

namespace App\Services;

use App\Models\User;
use App\Models\Lead;
use App\Models\UserActivity;
use Illuminate\Http\Request;

class UserActivityService
{
    /**
     * Log user activity
     */
    public function log(
        User $user, 
        string $action, 
        string $description, 
        ?Lead $lead = null, 
        array $metadata = [],
        ?Request $request = null
    ): UserActivity {
        $request = $request ?? request();
        
        return UserActivity::create([
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'lead_id' => $lead?->id,
            'metadata' => $metadata
        ]);
    }

    /**
     * Get user activities with pagination
     */
    public function getUserActivities(User $user, int $limit = 50)
    {
        return UserActivity::where('user_id', $user->id)
            ->with('lead:id,name')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent activities for dashboard
     */
    public function getRecentActivities(int $days = 7, ?User $user = null, int $limit = 20)
    {
        $query = UserActivity::where('created_at', '>=', now()->subDays($days))
            ->with(['user:id,name,avatar_color', 'lead:id,name']);

        if ($user) {
            $query->where('user_id', $user->id);
        }

        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get activity statistics
     */
    public function getStatistics(?User $user = null, int $days = 30): array
    {
        $query = UserActivity::where('created_at', '>=', now()->subDays($days));

        if ($user) {
            $query->where('user_id', $user->id);
        }

        $totalActivities = $query->count();
        
        $byAction = $query->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->get();

        $todayCount = UserActivity::whereDate('created_at', today())
            ->when($user, function($q) use ($user) {
                return $q->where('user_id', $user->id);
            })
            ->count();

        return [
            'total' => $totalActivities,
            'today' => $todayCount,
            'by_action' => $byAction,
            'avg_per_day' => round($totalActivities / $days, 2)
        ];
    }

    /**
     * Get user activity timeline
     */
    public function getTimeline(User $user, int $days = 30)
    {
        return UserActivity::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->with('lead:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function($activity) {
                return $activity->created_at->format('Y-m-d');
            });
    }

    /**
     * Delete old activities (for cleanup)
     */
    public function deleteOldActivities(int $days = 90): int
    {
        return UserActivity::where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
