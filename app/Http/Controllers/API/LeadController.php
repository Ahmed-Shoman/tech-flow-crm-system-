<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\UserActivity;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LeadController extends Controller
{
    /**
     * Display a listing of leads
     */
    public function index(Request $request)
    {
        $query = Lead::with(['assignee:id,name,avatar_color', 'creator:id,name', 'notes.author:id,name', 'activities.attachments'])
                     ->withCount(['notes', 'activities']);

        // Restrict agents to only see their assigned leads
        if (!auth()->user()->isAdmin()) {
            $query->where('assignee_id', auth()->id());
        }

        // Filter by stage
        if ($request->has('stage')) {
            $query->byStage($request->stage);
        }

        // Filter by assignee
        if ($request->has('assignee_id')) {
            $query->byAssignee($request->assignee_id);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->byPriority($request->priority);
        }

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $leads = $query->paginate($request->get('per_page', 100));

        // Log activity
        UserActivity::create([
            'user_id' => auth()->id(),
            'action' => 'view_leads',
            'description' => 'Viewed leads list',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        return response()->json([
            'success' => true,
            'data' => $leads->items(),
            'pagination' => [
                'current_page' => $leads->currentPage(),
                'total_pages' => $leads->lastPage(),
                'per_page' => $leads->perPage(),
                'total' => $leads->total()
            ]
        ]);
    }

    /**
     * Store a newly created lead
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'tech_support_phone' => 'nullable|string|max:50',
            'store_link' => 'nullable|string|max:255',
            'auth_status' => 'nullable|string|max:100',
            'social_media' => 'nullable|string',
            'source' => 'nullable|string|max:100',
            'budget' => 'nullable|numeric|min:0',
            'priority' => 'nullable|in:low,medium,high',
            'stage' => 'nullable|in:new,attempted,negotiation,followup,won,lost',
            'assignee_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $lead = Lead::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'tech_support_phone' => $request->tech_support_phone,
                'store_link' => $request->store_link,
                'auth_status' => $request->auth_status,
                'social_media' => $request->social_media,
                'source' => $request->source ?? 'Manual',
                'budget' => $request->budget ?? 0,
                'priority' => $request->priority ?? 'medium',
                'stage' => $request->stage ?? 'new',
                'assignee_id' => $request->assignee_id,
                'created_by' => auth()->id()
            ]);

            // Create initial activity
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'action' => 'create',
                'description' => 'Lead created',
                'new_value' => 'new'
            ]);

            // Log user activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'create_lead',
                'description' => "Created new lead: {$lead->name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'lead_id' => $lead->id
            ]);

            DB::commit();

            $lead->load(['assignee:id,name,avatar_color', 'creator:id,name']);

            return response()->json([
                'success' => true,
                'data' => $lead,
                'message' => 'Lead created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'error' => 'Failed to create lead'
            ], 500);
        }
    }

    /**
     * Display the specified lead
     */
    public function show(Request $request, Lead $lead)
    {
        // Check permissions
        if (!auth()->user()->isAdmin() && $lead->assignee_id !== auth()->id() && $lead->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to view this lead'
            ], 403);
        }

        $lead->load([
            'assignee:id,name,avatar_color,role',
            'creator:id,name',
            'notes.author:id,name',
            'activities.user:id,name',
            'activities.attachments',
            'attachments'
        ]);

        // Log view activity
        UserActivity::create([
            'user_id' => auth()->id(),
            'action' => 'view_lead',
            'description' => "Viewed lead details: {$lead->name}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'lead_id' => $lead->id
        ]);

        return response()->json([
            'success' => true,
            'data' => $lead
        ]);
    }

    /**
     * Update the specified lead
     */
    public function update(Request $request, Lead $lead)
    {
        // Check permissions
        if (!auth()->user()->isAdmin() && $lead->assignee_id !== auth()->id() && $lead->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to update this lead'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'nullable|string|max:20',
            'tech_support_phone' => 'nullable|string|max:50',
            'store_link' => 'nullable|string|max:255',
            'auth_status' => 'nullable|string|max:100',
            'social_media' => 'nullable|string',
            'source' => 'nullable|string|max:100',
            'budget' => 'nullable|numeric|min:0',
            'priority' => 'nullable|in:low,medium,high',
            'stage' => 'nullable|in:new,attempted,negotiation,followup,won,lost',
            'assignee_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $fields = ['name', 'email', 'phone', 'tech_support_phone', 'store_link', 'auth_status', 'social_media', 'source', 'budget', 'priority', 'stage', 'assignee_id'];
            $oldData = $lead->only($fields);
            
            $lead->update($request->only($fields));

            // Log activity for changes
            if ($request->has('stage') && $request->stage !== $oldData['stage']) {
                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => auth()->id(),
                    'action' => 'stage_change',
                    'description' => "Stage changed from {$oldData['stage']} to {$request->stage}",
                    'old_value' => $oldData['stage'],
                    'new_value' => $request->stage
                ]);
            }

            if ($request->has('assignee_id') && $request->assignee_id !== $oldData['assignee_id']) {
                $assigneeName = $request->assignee_id ? 
                    \App\Models\User::find($request->assignee_id)->name : 'Unassigned';
                
                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => auth()->id(),
                    'action' => 'assign',
                    'description' => "Assigned to {$assigneeName}",
                    'old_value' => $oldData['assignee_id'],
                    'new_value' => $request->assignee_id
                ]);
            }

            // Log user activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'update_lead',
                'description' => "Updated lead: {$lead->name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'lead_id' => $lead->id,
                'metadata' => ['changes' => array_diff_assoc($request->only(array_keys($oldData)), $oldData)]
            ]);

            DB::commit();

            $lead->load(['assignee:id,name,avatar_color', 'creator:id,name']);

            return response()->json([
                'success' => true,
                'data' => $lead,
                'message' => 'Lead updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'error' => 'Failed to update lead'
            ], 500);
        }
    }

    /**
     * Remove the specified lead
     */
    public function destroy(Request $request, Lead $lead)
    {
        // Only admins or lead creators can delete
        if (!auth()->user()->isAdmin() && $lead->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to delete this lead'
            ], 403);
        }

        try {
            $leadName = $lead->name;
            $leadId = $lead->id;
            
            $lead->delete();

            // Log user activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'delete_lead',
                'description' => "Deleted lead: {$leadName}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => ['deleted_lead_id' => $leadId]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lead deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete lead'
            ], 500);
        }
    }

    /**
     * Assign lead to user
     */
    public function assign(Request $request, Lead $lead)
    {
        // Check permissions
        if (!auth()->user()->isAdmin() && $lead->assignee_id !== auth()->id() && $lead->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to assign this lead'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'assignee_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldAssigneeId = $lead->assignee_id;
            $lead->update(['assignee_id' => $request->assignee_id]);

            $assigneeName = $request->assignee_id ? 
                \App\Models\User::find($request->assignee_id)->name : 'Unassigned';

            // Create lead activity
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'action' => 'assign',
                'description' => "Assigned to {$assigneeName}",
                'old_value' => $oldAssigneeId,
                'new_value' => $request->assignee_id
            ]);

            // Log user activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'assign_lead',
                'description' => "Assigned lead '{$lead->name}' to {$assigneeName}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'lead_id' => $lead->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Lead assigned to {$assigneeName}"
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'error' => 'Failed to assign lead'
            ], 500);
        }
    }

    /**
     * Update lead stage with comment and attachments
     */
    public function updateStage(Request $request, Lead $lead)
    {
        // Check permissions
        if (!auth()->user()->isAdmin() && $lead->assignee_id !== auth()->id() && $lead->created_by !== auth()->id()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to update this lead'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'stage' => 'required|in:new,attempted,negotiation,followup,won,lost',
            'comment' => 'required|string|min:1|max:1000',
            'attachments.*' => 'file|mimes:jpeg,png,gif,webp|max:5120' // 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        if ($lead->stage === $request->stage) {
            return response()->json([
                'success' => false,
                'error' => 'Lead is already in this stage'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldStage = $lead->stage;
            $lead->update(['stage' => $request->stage]);

            // Create lead activity
            $activity = LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'action' => 'stage_change',
                'description' => $request->comment,
                'old_value' => $oldStage,
                'new_value' => $request->stage
            ]);

            // Handle attachments
            if ($request->hasFile('attachments')) {
                $this->handleAttachments($request->file('attachments'), $lead, $activity->id);
            }

            // Log user activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'stage_change',
                'description' => "Changed stage from {$oldStage} to {$request->stage}: {$request->comment}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'lead_id' => $lead->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stage updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'error' => 'Failed to update stage'
            ], 500);
        }
    }

    /**
     * Bulk assign leads
     */
    public function bulkAssign(Request $request)
    {
        // Check permissions
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Only admins can bulk assign leads'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'lead_ids' => 'required|array',
            'lead_ids.*' => 'exists:leads,id',
            'assignee_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $leads = Lead::whereIn('id', $request->lead_ids)->get();
            $assigneeName = $request->assignee_id ? 
                \App\Models\User::find($request->assignee_id)->name : 'Unassigned';

            foreach ($leads as $lead) {
                $lead->update(['assignee_id' => $request->assignee_id]);

                // Create lead activity
                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => auth()->id(),
                    'action' => 'assign',
                    'description' => "Bulk assigned to {$assigneeName}",
                    'new_value' => $request->assignee_id
                ]);
            }

            // Log user activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'bulk_assign',
                'description' => "Bulk assigned " . count($leads) . " leads to {$assigneeName}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => ['lead_ids' => $request->lead_ids, 'assignee_id' => $request->assignee_id]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($leads) . " leads assigned to {$assigneeName}"
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'error' => 'Failed to bulk assign leads'
            ], 500);
        }
    }

    /**
     * Bulk create leads
     */
    public function bulkCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'leads' => 'required|array|min:1',
            'leads.*.name' => 'required|string|max:255',
            'leads.*.email' => 'required|email|max:255',
            'leads.*.phone' => 'nullable|string|max:20',
            'leads.*.tech_support_phone' => 'nullable|string|max:50',
            'leads.*.store_link' => 'nullable|string|max:255',
            'leads.*.auth_status' => 'nullable|string|max:100',
            'leads.*.social_media' => 'nullable|string',
            'leads.*.source' => 'nullable|string|max:100',
            'leads.*.budget' => 'nullable|numeric|min:0',
            'leads.*.priority' => 'nullable|in:low,medium,high',
            'leads.*.stage' => 'nullable|in:new,attempted,negotiation,followup,won,lost',
            'leads.*.assignee_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $createdLeads = [];
            $userId = auth()->id();
            
            foreach ($request->leads as $leadData) {
                $lead = Lead::create([
                    'name' => $leadData['name'],
                    'email' => $leadData['email'],
                    'phone' => $leadData['phone'] ?? null,
                    'tech_support_phone' => $leadData['tech_support_phone'] ?? null,
                    'store_link' => $leadData['store_link'] ?? null,
                    'auth_status' => $leadData['auth_status'] ?? null,
                    'social_media' => $leadData['social_media'] ?? null,
                    'source' => $leadData['source'] ?? 'Import',
                    'budget' => $leadData['budget'] ?? 0,
                    'priority' => $leadData['priority'] ?? 'medium',
                    'stage' => $leadData['stage'] ?? 'new',
                    'assignee_id' => $leadData['assignee_id'] ?? null,
                    'created_by' => $userId
                ]);

                // Create initial activity
                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => $userId,
                    'action' => 'create',
                    'description' => 'Lead imported',
                    'new_value' => 'new'
                ]);

                $lead->load(['assignee:id,name,avatar_color', 'creator:id,name']);
                $createdLeads[] = $lead;
            }

            // Log user activity
            UserActivity::create([
                'user_id' => $userId,
                'action' => 'bulk_import',
                'description' => "Imported " . count($createdLeads) . " leads",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => ['count' => count($createdLeads)]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $createdLeads,
                'message' => count($createdLeads) . ' leads created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'error' => 'Failed to create leads: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete leads
     */
    public function bulkDelete(Request $request)
    {
        // Check permissions
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Only admins can bulk delete leads'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'lead_ids' => 'required|array|min:1',
            'lead_ids.*' => 'exists:leads,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $leads = Lead::whereIn('id', $request->lead_ids)->get();
            $leadNames = $leads->pluck('name')->toArray();
            $count = $leads->count();
            
            // Delete the leads
            Lead::whereIn('id', $request->lead_ids)->delete();

            // Log user activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'bulk_delete',
                'description' => "Deleted {$count} leads",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => ['deleted_lead_ids' => $request->lead_ids, 'lead_names' => $leadNames]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$count} leads deleted successfully"
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'error' => 'Failed to bulk delete leads: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle file attachments
     */
    private function handleAttachments($files, Lead $lead, $activityId = null)
    {
        $uploadedFiles = [];
        
        foreach ($files as $file) {
            // Validate file
            if (!$this->validateFile($file)) {
                continue;
            }
            
            // Store file
            $filename = Str::random(32) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('attachments/' . date('Y/m'), $filename, 'public');
            
            // Create attachment record
            $attachment = Attachment::create([
                'lead_id' => $lead->id,
                'activity_id' => $activityId,
                'user_id' => auth()->id(),
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType()
            ]);
            
            $uploadedFiles[] = $attachment;
        }
        
        return $uploadedFiles;
    }

    /**
     * Validate uploaded file
     */
    private function validateFile($file): bool
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        return in_array($file->getMimeType(), $allowedTypes) && $file->getSize() <= $maxSize;
    }
}