<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use App\Models\Note;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->isAdmin();

        // Base query for leads (admins see all, agents see only assigned)
        $leadsQuery = $isAdmin ? Lead::query() : Lead::where('assignee_id', $user->id);

        // Total leads
        $totalLeads = (clone $leadsQuery)->count();

        // Leads by stage
        $leadsByStage = (clone $leadsQuery)
            ->select('stage', DB::raw('count(*) as count'))
            ->groupBy('stage')
            ->get()
            ->mapWithKeys(function($item) {
                return [$item->stage => $item->count];
            });

        // Leads by priority
        $leadsByPriority = (clone $leadsQuery)
            ->select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->get()
            ->mapWithKeys(function($item) {
                return [$item->priority => $item->count];
            });

        // Conversion stats
        $wonLeads = (clone $leadsQuery)->where('stage', 'won')->count();
        $lostLeads = (clone $leadsQuery)->where('stage', 'lost')->count();
        $activeLeads = $totalLeads - $wonLeads - $lostLeads;
        $conversionRate = $totalLeads > 0 ? round(($wonLeads / $totalLeads) * 100, 2) : 0;

        // Revenue stats (from won leads)
        $totalRevenue = (clone $leadsQuery)
            ->where('stage', 'won')
            ->sum('budget');
        
        $avgDealSize = (clone $leadsQuery)
            ->where('stage', 'won')
            ->avg('budget');

        // Recent activities
        $recentActivities = UserActivity::when(!$isAdmin, function($q) use ($user) {
                return $q->where('user_id', $user->id);
            })
            ->with(['user:id,name,avatar_color', 'lead:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Top performers (admin only)
        $topPerformers = null;
        if ($isAdmin) {
            $topPerformers = User::where('role', 'agent')
                ->where('is_active', true)
                ->withCount([
                    'leads as total_leads',
                    'leads as won_leads' => function($q) {
                        $q->where('stage', 'won');
                    }
                ])
                ->withSum('leads as total_revenue', DB::raw('CASE WHEN stage = "won" THEN budget ELSE 0 END'))
                ->orderBy('won_leads', 'desc')
                ->limit(5)
                ->get()
                ->map(function($agent) {
                    return [
                        'id' => $agent->id,
                        'name' => $agent->name,
                        'avatar_color' => $agent->avatar_color,
                        'initials' => $agent->initials,
                        'total_leads' => $agent->total_leads ?? 0,
                        'won_leads' => $agent->won_leads ?? 0,
                        'conversion_rate' => $agent->total_leads > 0 
                            ? round(($agent->won_leads / $agent->total_leads) * 100, 2) 
                            : 0,
                        'total_revenue' => $agent->total_revenue ?? 0
                    ];
                });
        }

        // Leads created this week/month
        $leadsThisWeek = (clone $leadsQuery)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();
        
        $leadsThisMonth = (clone $leadsQuery)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        // Activity summary
        $activitySummary = [
            'today' => UserActivity::today()
                ->when(!$isAdmin, function($q) use ($user) {
                    return $q->where('user_id', $user->id);
                })
                ->count(),
            'this_week' => UserActivity::where('created_at', '>=', now()->startOfWeek())
                ->when(!$isAdmin, function($q) use ($user) {
                    return $q->where('user_id', $user->id);
                })
                ->count(),
            'this_month' => UserActivity::where('created_at', '>=', now()->startOfMonth())
                ->when(!$isAdmin, function($q) use ($user) {
                    return $q->where('user_id', $user->id);
                })
                ->count()
        ];

        // Total notes count
        $totalNotes = Note::when(!$isAdmin, function($q) use ($user) {
                return $q->where('author_id', $user->id);
            })
            ->count();

        // Log dashboard view
        UserActivity::create([
            'user_id' => $user->id,
            'action' => 'view_dashboard',
            'description' => 'Viewed dashboard',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_leads' => $totalLeads,
                    'active_leads' => $activeLeads,
                    'won_leads' => $wonLeads,
                    'lost_leads' => $lostLeads,
                    'conversion_rate' => $conversionRate,
                    'total_revenue' => round($totalRevenue, 2),
                    'avg_deal_size' => round($avgDealSize ?? 0, 2),
                    'total_notes' => $totalNotes,
                    'leads_this_week' => $leadsThisWeek,
                    'leads_this_month' => $leadsThisMonth
                ],
                'leads_by_stage' => [
                    'new' => $leadsByStage['new'] ?? 0,
                    'attempted' => $leadsByStage['attempted'] ?? 0,
                    'negotiation' => $leadsByStage['negotiation'] ?? 0,
                    'followup' => $leadsByStage['followup'] ?? 0,
                    'won' => $leadsByStage['won'] ?? 0,
                    'lost' => $leadsByStage['lost'] ?? 0
                ],
                'leads_by_priority' => [
                    'low' => $leadsByPriority['low'] ?? 0,
                    'medium' => $leadsByPriority['medium'] ?? 0,
                    'high' => $leadsByPriority['high'] ?? 0
                ],
                'activity_summary' => $activitySummary,
                'recent_activities' => $recentActivities,
                'top_performers' => $topPerformers
            ]
        ]);
    }

    /**
     * Get quick stats for header/navbar
     */
    public function quickStats(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->isAdmin();

        $leadsQuery = $isAdmin ? Lead::query() : Lead::where('assignee_id', $user->id);

        $stats = [
            'total_leads' => (clone $leadsQuery)->count(),
            'new_leads' => (clone $leadsQuery)->where('stage', 'new')->count(),
            'high_priority' => (clone $leadsQuery)->where('priority', 'high')->count(),
            'activities_today' => UserActivity::today()
                ->when(!$isAdmin, function($q) use ($user) {
                    return $q->where('user_id', $user->id);
                })
                ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
