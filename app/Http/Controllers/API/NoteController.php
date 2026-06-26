<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Note;
use App\Models\LeadActivity;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NoteController extends Controller
{
    /**
     * Store a newly created note
     */
    public function store(Request $request, Lead $lead)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|min:3|max:5000'
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
            // Create note
            $note = Note::create([
                'lead_id' => $lead->id,
                'author_id' => auth()->id(),
                'content' => $request->content
            ]);

            // Create lead activity
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'action' => 'note',
                'description' => 'Added a note'
            ]);

            // Log user activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'add_note',
                'description' => "Added note to lead: {$lead->name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'lead_id' => $lead->id
            ]);

            DB::commit();

            // Load author relationship
            $note->load('author:id,name,avatar_color');

            return response()->json([
                'success' => true,
                'data' => $note,
                'message' => 'Note added successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'error' => 'Failed to add note'
            ], 500);
        }
    }

    /**
     * Display the specified note
     */
    public function show(Request $request, Note $note)
    {
        $note->load(['author:id,name,avatar_color', 'lead:id,name']);

        return response()->json([
            'success' => true,
            'data' => $note
        ]);
    }

    /**
     * Update the specified note
     */
    public function update(Request $request, Note $note)
    {
        // Only note author or admin can update
        if ($note->author_id !== auth()->id() && !auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to update this note'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|min:3|max:5000'
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
            $note->update(['content' => $request->content]);

            // Log user activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'update_note',
                'description' => "Updated note on lead: {$note->lead->name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'lead_id' => $note->lead_id
            ]);

            DB::commit();

            $note->load('author:id,name,avatar_color');

            return response()->json([
                'success' => true,
                'data' => $note,
                'message' => 'Note updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'error' => 'Failed to update note'
            ], 500);
        }
    }

    /**
     * Remove the specified note
     */
    public function destroy(Request $request, Note $note)
    {
        // Only note author or admin can delete
        if ($note->author_id !== auth()->id() && !auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to delete this note'
            ], 403);
        }

        try {
            $leadName = $note->lead->name;
            $leadId = $note->lead_id;
            
            $note->delete();

            // Log user activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'delete_note',
                'description' => "Deleted note from lead: {$leadName}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'lead_id' => $leadId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Note deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete note'
            ], 500);
        }
    }
}
