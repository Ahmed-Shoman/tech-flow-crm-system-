<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use App\Models\Note;
use App\Models\UserActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Get agent performance metrics
     */
    public function getAgentPerformance(): Collection
    {
        return User::where('role', 'agent')
            ->where('is_active', true)
            ->with(['leads' => function($query) {
                $query->select('id', 'assignee_id', 'stage', 'budget', 'created_at', 'updated_at');
            }])
            ->get()
            ->map(function($agent) {
                $leads = $agent->leads;
                $wonLeads = $leads->where('stage', 'won');
                $totalLeads = $leads->count();

                return [
                    'agent' => $agent->only(['id', 'name', 'email', 'avatar_color']),
                    'metrics' => [
                        'total_leads' => $totalLeads,
                        'won_leads' => $wonLeads->count(),
                        'lost_leads' => $leads->where('stage', 'lost')->count(),
                        'active_leads' => $leads->whereNotIn('stage', ['won', 'lost'])->count(),
                        'conversion_rate' => $totalLeads > 0 
                            ? round(($wonLeads->count() / $totalLeads) * 100, 2) 
                            : 0,
                        'total_revenue' => round($wonLeads->sum('budget'), 2),
                        'avg_deal_size' => round($wonLeads->avg('budget') ?? 0, 2),
                        'total_notes' => Note::where('author_id', $agent->id)->count(),
                        'total_activities' => UserActivity::where('user_id', $agent->id)->count()
                    ]
                ];
            });
    }

    /**
     * Calculate conversion rate for a specific stage or overall
     */
    public function getConversionRate(?string $stage = null): float
    {
        $query = Lead::query();
        
        if ($stage) {
            $totalLeads = (clone $query)->where('stage', $stage)->count();
            $wonLeads = (clone $query)->where('stage', $stage)->where('stage', 'won')->count();
        } else {
            $totalLeads = Lead::count();
            $wonLeads = Lead::where('stage', 'won')->count();
        }

        return $totalLeads > 0 ? round(($wonLeads / $totalLeads) * 100, 2) : 0;
    }

    /**
     * Get revenue statistics
     */
    public function getRevenueStats(): array
    {
        $wonLeads = Lead::where('stage', 'won');
        
        return [
            'total_revenue' => round($wonLeads->sum('budget'), 2),
            'avg_deal_size' => round($wonLeads->avg('budget') ?? 0, 2),
            'deals_count' => $wonLeads->count(),
            'largest_deal' => round($wonLeads->max('budget') ?? 0, 2),
            'smallest_deal' => round($wonLeads->min('budget') ?? 0, 2)
        ];
    }

    /**
     * Get leads trend data
     */
    public function getLeadsTrend(int $days = 30): Collection
    {
        return Lead::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * Get conversion funnel data
     */
    public function getConversionFunnel(): array
    {
        $totalLeads = Lead::count();
        
        $stages = [
            'new' => Lead::where('stage', 'new')->count(),
            'attempted' => Lead::whereIn('stage', ['attempted', 'negotiation', 'followup', 'won', 'lost'])->count(),
            'negotiation' => Lead::whereIn('stage', ['negotiation', 'followup', 'won', 'lost'])->count(),
            'followup' => Lead::whereIn('stage', ['followup', 'won', 'lost'])->count(),
            'closed' => Lead::whereIn('stage', ['won', 'lost'])->count(),
            'won' => Lead::where('stage', 'won')->count()
        ];

        $funnel = [];
        foreach ($stages as $stage => $count) {
            $funnel[$stage] = [
                'count' => $count,
                'percentage' => $totalLeads > 0 ? round(($count / $totalLeads) * 100, 2) : 0
            ];
        }

        return $funnel;
    }

    /**
     * Get source performance analysis
     */
    public function getSourceAnalysis(): Collection
    {
        return Lead::selectRaw('
                source, 
                COUNT(*) as total,
                SUM(CASE WHEN stage = "won" THEN 1 ELSE 0 END) as won,
                SUM(CASE WHEN stage = "lost" THEN 1 ELSE 0 END) as lost,
                SUM(CASE WHEN stage = "won" THEN budget ELSE 0 END) as revenue
            ')
            ->groupBy('source')
            ->orderBy('total', 'desc')
            ->get()
            ->map(function($item) {
                return [
                    'source' => $item->source ?? 'Unknown',
                    'total' => $item->total,
                    'won' => $item->won,
                    'lost' => $item->lost,
                    'active' => $item->total - $item->won - $item->lost,
                    'conversion_rate' => $item->total > 0 
                        ? round(($item->won / $item->total) * 100, 2) 
                        : 0,
                    'revenue' => round($item->revenue, 2)
                ];
            });
    }

    /**
     * Get stage distribution
     */
    public function getStageDistribution(): array
    {
        return Lead::selectRaw('
                stage, 
                COUNT(*) as count,
                SUM(budget) as total_value,
                AVG(budget) as avg_value
            ')
            ->groupBy('stage')
            ->get()
            ->mapWithKeys(function($item) {
                return [
                    $item->stage => [
                        'count' => $item->count,
                        'total_value' => round($item->total_value, 2),
                        'avg_value' => round($item->avg_value, 2)
                    ]
                ];
            })
            ->toArray();
    }

    /**
     * Get priority distribution
     */
    public function getPriorityDistribution(): array
    {
        return Lead::selectRaw('
                priority, 
                COUNT(*) as count,
                SUM(CASE WHEN stage = "won" THEN 1 ELSE 0 END) as won,
                SUM(CASE WHEN stage = "won" THEN budget ELSE 0 END) as revenue
            ')
            ->groupBy('priority')
            ->get()
            ->mapWithKeys(function($item) {
                return [
                    $item->priority => [
                        'count' => $item->count,
                        'won' => $item->won,
                        'conversion_rate' => $item->count > 0 
                            ? round(($item->won / $item->count) * 100, 2) 
                            : 0,
                        'revenue' => round($item->revenue, 2)
                    ]
                ];
            })
            ->toArray();
    }

    /**
     * Calculate average time to close
     */
    public function getAverageTimeToClose(?int $agentId = null): float
    {
        $query = Lead::where('stage', 'won')
            ->whereNotNull('created_at')
            ->whereNotNull('updated_at');

        if ($agentId) {
            $query->where('assignee_id', $agentId);
        }

        $leads = $query->get();

        if ($leads->isEmpty()) {
            return 0;
        }

        $totalDays = $leads->sum(function($lead) {
            return $lead->created_at->diffInDays($lead->updated_at);
        });

        return round($totalDays / $leads->count(), 1);
    }

    /**
     * Get team performance summary
     */
    public function getTeamSummary(): array
    {
        $agents = User::where('role', 'agent')->where('is_active', true)->count();
        $totalLeads = Lead::count();
        $activeLeads = Lead::whereNotIn('stage', ['won', 'lost'])->count();
        $wonLeads = Lead::where('stage', 'won')->count();
        
        return [
            'total_agents' => $agents,
            'total_leads' => $totalLeads,
            'active_leads' => $activeLeads,
            'won_leads' => $wonLeads,
            'avg_leads_per_agent' => $agents > 0 ? round($totalLeads / $agents, 1) : 0,
            'team_conversion_rate' => $this->getConversionRate(),
            'total_revenue' => $this->getRevenueStats()['total_revenue']
        ];
    }
}
