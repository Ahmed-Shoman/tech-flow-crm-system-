<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Lead;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    /**
     * Upload attachments
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|exists:leads,id',
            'activity_id' => 'nullable|exists:lead_activities,id',
            'files.*' => 'required|file|mimes:jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx|max:10240' // 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        if (!$request->hasFile('files')) {
            return response()->json([
                'success' => false,
                'error' => 'No files provided'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $lead = Lead::findOrFail($request->lead_id);
            $uploadedFiles = [];

            foreach ($request->file('files') as $file) {
                // Generate unique filename
                $filename = Str::random(32) . '.' . $file->getClientOriginalExtension();
                
                // Store file
                $path = $file->storeAs('attachments/' . date('Y/m'), $filename, 'public');
                
                // Create attachment record
                $attachment = Attachment::create([
                    'lead_id' => $lead->id,
                    'activity_id' => $request->activity_id,
                    'user_id' => auth()->id(),
                    'filename' => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType()
                ]);

                $attachment->load('user:id,name');
                $uploadedFiles[] = $attachment;
            }

            // Log user activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'upload_file',
                'description' => "Uploaded " . count($uploadedFiles) . " file(s) to lead: {$lead->name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'lead_id' => $lead->id,
                'metadata' => ['files_count' => count($uploadedFiles)]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $uploadedFiles,
                'message' => count($uploadedFiles) . ' file(s) uploaded successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'error' => 'Failed to upload files'
            ], 500);
        }
    }

    /**
     * Display the specified attachment
     */
    public function show(Request $request, Attachment $attachment)
    {
        try {
            // Check if file exists
            if (!Storage::disk('public')->exists($attachment->file_path)) {
                return response()->json([
                    'success' => false,
                    'error' => 'File not found'
                ], 404);
            }

            // Log download activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'download_file',
                'description' => "Downloaded file: {$attachment->original_name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'lead_id' => $attachment->lead_id,
                'metadata' => ['attachment_id' => $attachment->id]
            ]);

            // Return file for download
            return Storage::disk('public')->download(
                $attachment->file_path, 
                $attachment->original_name
            );

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to download file'
            ], 500);
        }
    }

    /**
     * Remove the specified attachment
     */
    public function destroy(Request $request, Attachment $attachment)
    {
        // Only uploader or admin can delete
        if ($attachment->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to delete this file'
            ], 403);
        }

        try {
            $fileName = $attachment->original_name;
            $leadId = $attachment->lead_id;
            
            // Delete file from storage
            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            // Delete record
            $attachment->delete();

            // Log user activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'delete_file',
                'description' => "Deleted file: {$fileName}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'lead_id' => $leadId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete file'
            ], 500);
        }
    }

    /**
     * Get attachments for a lead
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|exists:leads,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        $attachments = Attachment::where('lead_id', $request->lead_id)
                                 ->with('user:id,name,avatar_color')
                                 ->orderBy('created_at', 'desc')
                                 ->get();

        return response()->json([
            'success' => true,
            'data' => $attachments
        ]);
    }
}
