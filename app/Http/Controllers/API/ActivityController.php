<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * Get user activities
     */
    public function userActivities(Request $request, User $user)
    {
        // Only admins or the user themselves can view their activities
        if (!auth()->user()->isAdmin() && $user->id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to view these activities'
            ], 403);
        }

        $activities = UserActivity::where('user_id', $user->id)
                                  ->with('lead:id,name')
                                  ->orderBy('created_at', 'desc')
                                  ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $activities->items(),
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'total_pages' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total()
            ]
        ]);
    }

    /**
     * Get lead activities
     */
    public function leadActivities(Request $request, Lead $lead)
    {
        $activities = LeadActivity::where('lead_id', $lead->id)
                                  ->with(['user:id,name,avatar_color', 'attachments'])
                                  ->orderBy('created_at', 'desc')
                                  ->paginate($request->get('per_page', 50));

        // Log view activity
        UserActivity::create([
            'user_id' => auth()->id(),
            'action' => 'view_activities',
            'description' => "Viewed activities for lead: {$lead->name}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'lead_id' => $lead->id
        ]);

        return response()->json([
            'success' => true,
            'data' => $activities->items(),
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'total_pages' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total()
            ]
        ]);
    }

    /**
     * Get recent activities (dashboard feed)
     */
    public function recent(Request $request)
    {
        $days = $request->get('days', 7);
        $perPage = $request->get('per_page', 20);

        // Get activities based on user role
        if (auth()->user()->isAdmin()) {
            // Admins see all activities
            $activities = UserActivity::recent($days)
                                      ->with(['user:id,name,avatar_color', 'lead:id,name'])
                                      ->orderBy('created_at', 'desc')
                                      ->paginate($perPage);
        } else {
            // Agents see only their own activities
            $activities = UserActivity::recent($days)
                                      ->where('user_id', auth()->id())
                                      ->with(['user:id,name,avatar_color', 'lead:id,name'])
                                      ->orderBy('created_at', 'desc')
                                      ->paginate($perPage);
        }

        return response()->json([
            'success' => true,
            'data' => $activities->items(),
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'total_pages' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total()
            ]
        ]);
    }

    /**
     * Get today's activities
     */
    public function today(Request $request)
    {
        // Get activities based on user role
        if (auth()->user()->isAdmin()) {
            // Admins see all today's activities
            $activities = UserActivity::today()
                                      ->with(['user:id,name,avatar_color', 'lead:id,name'])
                                      ->orderBy('created_at', 'desc')
                                      ->get();
        } else {
            // Agents see only their own today's activities
            $activities = UserActivity::today()
                                      ->where('user_id', auth()->id())
                                      ->with(['user:id,name,avatar_color', 'lead:id,name'])
                                      ->orderBy('created_at', 'desc')
                                      ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }

    /**
     * Get activity statistics
     */
    public function stats(Request $request)
    {
        $days = $request->get('days', 30);
        $userId = auth()->user()->isAdmin() ? null : auth()->id();

        $query = UserActivity::recent($days);
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $stats = [
            'total_activities' => $query->count(),
            'by_action' => $query->selectRaw('action, COUNT(*) as count')
                                 ->groupBy('action')
                                 ->orderBy('count', 'desc')
                                 ->get()
                                 ->map(function($item) {
                                     return [
                                         'action' => $item->action,
                                         'action_display' => $item->action_display,
                                         'count' => $item->count
                                     ];
                                 }),
            'today' => UserActivity::today()->when($userId, function($q) use ($userId) {
                return $q->where('user_id', $userId);
            })->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
