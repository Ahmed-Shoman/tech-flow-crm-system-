<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Lead;
use App\Models\Note;
use App\Models\LeadActivity;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Alex Morgan',
            'email' => 'admin@lumencrm.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'avatar_color' => '#3b82f6',
            'is_active' => true
        ]);

        // Create agent users
        $agent1 = User::create([
            'name' => 'Priya Shah',
            'email' => 'priya@lumencrm.com',
            'password' => Hash::make('password'),
            'role' => 'agent',
            'avatar_color' => '#10b981',
            'is_active' => true
        ]);

        $agent2 = User::create([
            'name' => 'Marcus Lee',
            'email' => 'marcus@lumencrm.com',
            'password' => Hash::make('password'),
            'role' => 'agent',
            'avatar_color' => '#f59e0b',
            'is_active' => true
        ]);

        $agent3 = User::create([
            'name' => 'Sofia Reyes',
            'email' => 'sofia@lumencrm.com',
            'password' => Hash::make('password'),
            'role' => 'agent',
            'avatar_color' => '#8b5cf6',
            'is_active' => true
        ]);

        // Create sample leads
        $leads = [
            [
                'name' => 'Ahmed Mohamed',
                'email' => 'ahmed@example.com',
                'phone' => '+201234567890',
                'source' => 'Website',
                'budget' => 25000.00,
                'priority' => 'high',
                'stage' => 'negotiation',
                'assignee_id' => $agent1->id,
                'created_by' => $admin->id
            ],
            [
                'name' => 'Sara Ali',
                'email' => 'sara@example.com',
                'phone' => '+201234567891',
                'source' => 'Referral',
                'budget' => 15000.00,
                'priority' => 'medium',
                'stage' => 'new',
                'assignee_id' => $agent2->id,
                'created_by' => $admin->id
            ],
            [
                'name' => 'Omar Hassan',
                'email' => 'omar@example.com',
                'phone' => '+201234567892',
                'source' => 'LinkedIn',
                'budget' => 35000.00,
                'priority' => 'high',
                'stage' => 'followup',
                'assignee_id' => $agent1->id,
                'created_by' => $admin->id
            ],
            [
                'name' => 'Mona Ibrahim',
                'email' => 'mona@example.com',
                'phone' => '+201234567893',
                'source' => 'Cold Call',
                'budget' => 18000.00,
                'priority' => 'medium',
                'stage' => 'attempted',
                'assignee_id' => $agent3->id,
                'created_by' => $admin->id
            ],
            [
                'name' => 'Khaled Nasser',
                'email' => 'khaled@example.com',
                'phone' => '+201234567894',
                'source' => 'Instagram',
                'budget' => 50000.00,
                'priority' => 'high',
                'stage' => 'won',
                'assignee_id' => $agent2->id,
                'created_by' => $admin->id
            ],
        ];

        foreach ($leads as $leadData) {
            $lead = Lead::create($leadData);

            // Create initial activity
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => $leadData['created_by'],
                'action' => 'create',
                'description' => 'Lead created',
                'new_value' => 'new'
            ]);

            // Create assignment activity if assigned
            if ($leadData['assignee_id']) {
                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => $leadData['created_by'],
                    'action' => 'assign',
                    'description' => 'Assigned to ' . User::find($leadData['assignee_id'])->name,
                    'new_value' => $leadData['assignee_id']
                ]);
            }

            // Create stage change activity if not new
            if ($leadData['stage'] !== 'new') {
                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => $leadData['assignee_id'] ?? $leadData['created_by'],
                    'action' => 'stage_change',
                    'description' => 'Changed stage to ' . $leadData['stage'],
                    'old_value' => 'new',
                    'new_value' => $leadData['stage']
                ]);
            }

            // Add sample notes for some leads
            if (in_array($lead->stage, ['negotiation', 'followup', 'won'])) {
                Note::create([
                    'lead_id' => $lead->id,
                    'author_id' => $leadData['assignee_id'] ?? $leadData['created_by'],
                    'content' => 'Initial contact made. Client interested in premium package.'
                ]);
            }
        }

        echo "Database seeded successfully!\n";
        echo "Admin Login: admin@lumencrm.com / password\n";
        echo "Agent Login: priya@lumencrm.com / password\n";
    }
}