<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Generate comprehensive sales report
     */
    public function generateSalesReport(string $startDate, string $endDate): array
    {
        $leads = Lead::whereBetween('created_at', [$startDate, $endDate])->get();
        $wonLeads = $leads->where('stage', 'won');
        $lostLeads = $leads->where('stage', 'lost');

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'summary' => [
                'total_leads' => $leads->count(),
                'won_leads' => $wonLeads->count(),
                'lost_leads' => $lostLeads->count(),
                'active_leads' => $leads->count() - $wonLeads->count() - $lostLeads->count(),
                'conversion_rate' => $leads->count() > 0 
                    ? round(($wonLeads->count() / $leads->count()) * 100, 2) 
                    : 0,
                'total_revenue' => round($wonLeads->sum('budget'), 2),
                'avg_deal_size' => round($wonLeads->avg('budget') ?? 0, 2),
                'largest_deal' => round($wonLeads->max('budget') ?? 0, 2)
            ],
            'by_stage' => $this->getLeadsByStage($leads),
            'by_priority' => $this->getLeadsByPriority($leads),
            'by_source' => $this->getLeadsBySource($leads),
            'daily_breakdown' => $this->getDailyBreakdown($startDate, $endDate)
        ];
    }

    /**
     * Generate agent performance report
     */
    public function generateAgentReport(int $agentId, string $startDate, string $endDate): array
    {
        $agent = User::findOrFail($agentId);
        $leads = Lead::where('assignee_id', $agentId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $wonLeads = $leads->where('stage', 'won');
        $activities = UserActivity::where('user_id', $agentId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'role' => $agent->role
            ],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'performance' => [
                'total_leads' => $leads->count(),
                'won_leads' => $wonLeads->count(),
                'lost_leads' => $leads->where('stage', 'lost')->count(),
                'active_leads' => $leads->whereNotIn('stage', ['won', 'lost'])->count(),
                'conversion_rate' => $leads->count() > 0 
                    ? round(($wonLeads->count() / $leads->count()) * 100, 2) 
                    : 0,
                'total_revenue' => round($wonLeads->sum('budget'), 2),
                'avg_deal_size' => round($wonLeads->avg('budget') ?? 0, 2),
                'total_activities' => $activities->count(),
                'total_notes' => $agent->notes()
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count()
            ],
            'leads_by_stage' => $this->getLeadsByStage($leads),
            'activity_breakdown' => $activities->groupBy('action')->map->count()
        ];
    }

    /**
     * Generate team performance report
     */
    public function generateTeamReport(string $startDate, string $endDate): array
    {
        $agents = User::where('role', 'agent')->where('is_active', true)->get();
        
        $teamData = $agents->map(function($agent) use ($startDate, $endDate) {
            $leads = Lead::where('assignee_id', $agent->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();
            
            $wonLeads = $leads->where('stage', 'won');

            return [
                'agent' => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'email' => $agent->email
                ],
                'metrics' => [
                    'total_leads' => $leads->count(),
                    'won_leads' => $wonLeads->count(),
                    'conversion_rate' => $leads->count() > 0 
                        ? round(($wonLeads->count() / $leads->count()) * 100, 2) 
                        : 0,
                    'total_revenue' => round($wonLeads->sum('budget'), 2)
                ]
            ];
        });

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'team_summary' => [
                'total_agents' => $agents->count(),
                'total_leads' => $teamData->sum('metrics.total_leads'),
                'total_won' => $teamData->sum('metrics.won_leads'),
                'total_revenue' => round($teamData->sum('metrics.total_revenue'), 2),
                'avg_conversion_rate' => round($teamData->avg('metrics.conversion_rate'), 2)
            ],
            'agents' => $teamData->sortByDesc('metrics.total_revenue')->values()
        ];
    }

    /**
     * Generate activity report
     */
    public function generateActivityReport(string $startDate, string $endDate, ?int $userId = null): array
    {
        $query = UserActivity::whereBetween('created_at', [$startDate, $endDate]);
        
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $activities = $query->get();

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'summary' => [
                'total_activities' => $activities->count(),
                'unique_users' => $activities->pluck('user_id')->unique()->count(),
                'avg_per_day' => round($activities->count() / max(1, now()->parse($startDate)->diffInDays($endDate)), 2)
            ],
            'by_action' => $activities->groupBy('action')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'percentage' => round(($group->count() / max(1, $group->count())) * 100, 2)
                ];
            }),
            'by_user' => $activities->groupBy('user_id')->map(function($group) {
                $user = User::find($group->first()->user_id);
                return [
                    'user' => $user ? $user->name : 'Unknown',
                    'count' => $group->count()
                ];
            })->sortByDesc('count')->take(10)->values(),
            'daily_breakdown' => $activities->groupBy(function($activity) {
                return $activity->created_at->format('Y-m-d');
            })->map->count()
        ];
    }

    /**
     * Generate conversion report
     */
    public function generateConversionReport(string $startDate, string $endDate): array
    {
        $leads = Lead::whereBetween('created_at', [$startDate, $endDate])->get();
        
        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'funnel' => [
                'total_leads' => $leads->count(),
                'attempted' => $leads->whereIn('stage', ['attempted', 'negotiation', 'followup', 'won', 'lost'])->count(),
                'negotiation' => $leads->whereIn('stage', ['negotiation', 'followup', 'won', 'lost'])->count(),
                'followup' => $leads->whereIn('stage', ['followup', 'won', 'lost'])->count(),
                'closed' => $leads->whereIn('stage', ['won', 'lost'])->count(),
                'won' => $leads->where('stage', 'won')->count()
            ],
            'conversion_rates' => [
                'lead_to_attempt' => $this->calculateConversionRate($leads->count(), $leads->whereIn('stage', ['attempted', 'negotiation', 'followup', 'won', 'lost'])->count()),
                'attempt_to_negotiation' => $this->calculateConversionRate($leads->whereIn('stage', ['attempted', 'negotiation', 'followup', 'won', 'lost'])->count(), $leads->whereIn('stage', ['negotiation', 'followup', 'won', 'lost'])->count()),
                'negotiation_to_close' => $this->calculateConversionRate($leads->whereIn('stage', ['negotiation', 'followup', 'won', 'lost'])->count(), $leads->whereIn('stage', ['won', 'lost'])->count()),
                'overall' => $this->calculateConversionRate($leads->count(), $leads->where('stage', 'won')->count())
            ]
        ];
    }

    /**
     * Helper: Get leads by stage
     */
    protected function getLeadsByStage(Collection $leads): array
    {
        return [
            'new' => $leads->where('stage', 'new')->count(),
            'attempted' => $leads->where('stage', 'attempted')->count(),
            'negotiation' => $leads->where('stage', 'negotiation')->count(),
            'followup' => $leads->where('stage', 'followup')->count(),
            'won' => $leads->where('stage', 'won')->count(),
            'lost' => $leads->where('stage', 'lost')->count()
        ];
    }

    /**
     * Helper: Get leads by priority
     */
    protected function getLeadsByPriority(Collection $leads): array
    {
        return [
            'low' => $leads->where('priority', 'low')->count(),
            'medium' => $leads->where('priority', 'medium')->count(),
            'high' => $leads->where('priority', 'high')->count()
        ];
    }

    /**
     * Helper: Get leads by source
     */
    protected function getLeadsBySource(Collection $leads): array
    {
        return $leads->groupBy('source')->map->count()->toArray();
    }

    /**
     * Helper: Get daily breakdown
     */
    protected function getDailyBreakdown(string $startDate, string $endDate): array
    {
        return Lead::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(CASE WHEN stage = "won" THEN budget ELSE 0 END) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(function($item) {
                return [
                    $item->date => [
                        'leads' => $item->count,
                        'revenue' => round($item->revenue, 2)
                    ]
                ];
            })
            ->toArray();
    }

    /**
     * Helper: Calculate conversion rate
     */
    protected function calculateConversionRate(int $total, int $converted): float
    {
        return $total > 0 ? round(($converted / $total) * 100, 2) : 0;
    }
}
