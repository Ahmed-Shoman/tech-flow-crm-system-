<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Get analytics overview
     */
    public function overview(Request $request)
    {
        // Only admins can view full analytics
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Only admins can view analytics'
            ], 403);
        }

        $dateRange = $request->get('range', 30); // days

        // Leads trend
        $leadsTrend = Lead::where('created_at', '>=', now()->subDays($dateRange))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Conversion trend
        $conversionTrend = Lead::where('updated_at', '>=', now()->subDays($dateRange))
            ->whereIn('stage', ['won', 'lost'])
            ->selectRaw('DATE(updated_at) as date, stage, COUNT(*) as count')
            ->groupBy('date', 'stage')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function($items) {
                return [
                    'won' => $items->where('stage', 'won')->first()->count ?? 0,
                    'lost' => $items->where('stage', 'lost')->first()->count ?? 0
                ];
            });

        // Revenue trend
        $revenueTrend = Lead::where('stage', 'won')
            ->where('updated_at', '>=', now()->subDays($dateRange))
            ->selectRaw('DATE(updated_at) as date, SUM(budget) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Source analysis
        $sourceAnalysis = Lead::selectRaw('source, COUNT(*) as total, 
                                          SUM(CASE WHEN stage = "won" THEN 1 ELSE 0 END) as won,
                                          SUM(CASE WHEN stage = "won" THEN budget ELSE 0 END) as revenue')
            ->groupBy('source')
            ->orderBy('total', 'desc')
            ->get()
            ->map(function($item) {
                return [
                    'source' => $item->source ?? 'Unknown',
                    'total' => $item->total,
                    'won' => $item->won,
                    'conversion_rate' => $item->total > 0 ? round(($item->won / $item->total) * 100, 2) : 0,
                    'revenue' => round($item->revenue, 2)
                ];
            });

        // Stage distribution
        $stageDistribution = Lead::selectRaw('stage, COUNT(*) as count, 
                                             SUM(budget) as total_value')
            ->groupBy('stage')
            ->get()
            ->mapWithKeys(function($item) {
                return [
                    $item->stage => [
                        'count' => $item->count,
                        'value' => round($item->total_value, 2)
                    ]
                ];
            });

        // Log activity
        UserActivity::create([
            'user_id' => auth()->id(),
            'action' => 'view_analytics',
            'description' => 'Viewed analytics overview',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'leads_trend' => $leadsTrend,
                'conversion_trend' => $conversionTrend,
                'revenue_trend' => $revenueTrend,
                'source_analysis' => $sourceAnalysis,
                'stage_distribution' => $stageDistribution
            ]
        ]);
    }

    /**
     * Get agent performance analytics
     */
    public function agentPerformance(Request $request)
    {
        // Only admins can view agent performance
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Only admins can view agent performance'
            ], 403);
        }

        $agents = User::where('role', 'agent')
            ->where('is_active', true)
            ->with(['leads' => function($query) {
                $query->select('id', 'assignee_id', 'stage', 'budget', 'created_at', 'updated_at');
            }])
            ->get()
            ->map(function($agent) {
                $leads = $agent->leads;
                $wonLeads = $leads->where('stage', 'won');
                $totalLeads = $leads->count();
                $activeLeads = $leads->whereNotIn('stage', ['won', 'lost'])->count();

                // Calculate average time to close
                $avgTimeToClose = $wonLeads->filter(function($lead) {
                    return $lead->updated_at && $lead->created_at;
                })->map(function($lead) {
                    return $lead->created_at->diffInDays($lead->updated_at);
                })->avg();

                // Activity count
                $activityCount = UserActivity::where('user_id', $agent->id)
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count();

                // Notes count
                $notesCount = $agent->notes()->count();

                return [
                    'agent' => [
                        'id' => $agent->id,
                        'name' => $agent->name,
                        'email' => $agent->email,
                        'avatar_color' => $agent->avatar_color,
                        'initials' => $agent->initials
                    ],
                    'metrics' => [
                        'total_leads' => $totalLeads,
                        'active_leads' => $activeLeads,
                        'won_leads' => $wonLeads->count(),
                        'lost_leads' => $leads->where('stage', 'lost')->count(),
                        'conversion_rate' => $totalLeads > 0 
                            ? round(($wonLeads->count() / $totalLeads) * 100, 2) 
                            : 0,
                        'total_revenue' => round($wonLeads->sum('budget'), 2),
                        'avg_deal_size' => round($wonLeads->avg('budget') ?? 0, 2),
                        'avg_time_to_close' => round($avgTimeToClose ?? 0, 1),
                        'total_notes' => $notesCount,
                        'total_activities' => $activityCount,
                        'activity_score' => $this->calculateActivityScore($activityCount, $notesCount, $totalLeads)
                    ]
                ];
            })
            ->sortByDesc('metrics.total_revenue')
            ->values();

        // Log activity
        UserActivity::create([
            'user_id' => auth()->id(),
            'action' => 'view_analytics',
            'description' => 'Viewed agent performance analytics',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        return response()->json([
            'success' => true,
            'data' => $agents
        ]);
    }

    /**
     * Get lead analytics
     */
    public function leadAnalytics(Request $request)
    {
        // Only admins can view lead analytics
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Only admins can view lead analytics'
            ], 403);
        }

        // Time-based analysis
        $timeAnalysis = [
            'today' => Lead::whereDate('created_at', today())->count(),
            'this_week' => Lead::where('created_at', '>=', now()->startOfWeek())->count(),
            'this_month' => Lead::where('created_at', '>=', now()->startOfMonth())->count(),
            'this_year' => Lead::where('created_at', '>=', now()->startOfYear())->count()
        ];

        // Priority analysis
        $priorityAnalysis = Lead::selectRaw('priority, 
                                            COUNT(*) as total,
                                            SUM(CASE WHEN stage = "won" THEN 1 ELSE 0 END) as won,
                                            SUM(CASE WHEN stage = "won" THEN budget ELSE 0 END) as revenue')
            ->groupBy('priority')
            ->get()
            ->mapWithKeys(function($item) {
                return [
                    $item->priority => [
                        'total' => $item->total,
                        'won' => $item->won,
                        'conversion_rate' => $item->total > 0 
                            ? round(($item->won / $item->total) * 100, 2) 
                            : 0,
                        'revenue' => round($item->revenue, 2)
                    ]
                ];
            });

        // Average budget by stage
        $avgBudgetByStage = Lead::selectRaw('stage, AVG(budget) as avg_budget, COUNT(*) as count')
            ->groupBy('stage')
            ->get()
            ->mapWithKeys(function($item) {
                return [
                    $item->stage => [
                        'avg_budget' => round($item->avg_budget, 2),
                        'count' => $item->count
                    ]
                ];
            });

        // Conversion funnel
        $totalLeads = Lead::count();
        $conversionFunnel = [
            'new' => ['count' => Lead::where('stage', 'new')->count(), 'percentage' => 100],
            'attempted' => [
                'count' => Lead::whereIn('stage', ['attempted', 'negotiation', 'followup', 'won', 'lost'])->count(),
                'percentage' => 0
            ],
            'negotiation' => [
                'count' => Lead::whereIn('stage', ['negotiation', 'followup', 'won', 'lost'])->count(),
                'percentage' => 0
            ],
            'followup' => [
                'count' => Lead::whereIn('stage', ['followup', 'won', 'lost'])->count(),
                'percentage' => 0
            ],
            'closed' => [
                'count' => Lead::whereIn('stage', ['won', 'lost'])->count(),
                'percentage' => 0
            ],
            'won' => [
                'count' => Lead::where('stage', 'won')->count(),
                'percentage' => 0
            ]
        ];

        // Calculate percentages
        if ($totalLeads > 0) {
            foreach ($conversionFunnel as $key => $value) {
                $conversionFunnel[$key]['percentage'] = round(($value['count'] / $totalLeads) * 100, 2);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'time_analysis' => $timeAnalysis,
                'priority_analysis' => $priorityAnalysis,
                'avg_budget_by_stage' => $avgBudgetByStage,
                'conversion_funnel' => $conversionFunnel
            ]
        ]);
    }

    /**
     * Calculate activity score
     */
    private function calculateActivityScore($activities, $notes, $leads): int
    {
        if ($leads === 0) return 0;
        
        $activityWeight = 0.4;
        $noteWeight = 0.6;
        
        $activityScore = min(($activities / max($leads, 1)) * 10, 10) * $activityWeight;
        $noteScore = min(($notes / max($leads, 1)) * 10, 10) * $noteWeight;
        
        return round(($activityScore + $noteScore) * 10);
    }
}
