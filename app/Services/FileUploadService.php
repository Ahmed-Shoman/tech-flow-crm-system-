<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    /**
     * Allowed file types
     */
    protected array $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    protected array $allowedDocumentTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    protected array $allowedSpreadsheetTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    
    /**
     * Maximum file size (in bytes)
     */
    protected int $maxFileSize = 10485760; // 10MB

    /**
     * Upload multiple attachments
     */
    public function uploadAttachments(array $files, Lead $lead, ?int $activityId = null, ?int $userId = null): array
    {
        $uploadedFiles = [];
        
        foreach ($files as $file) {
            if ($this->validateFile($file)) {
                $attachment = $this->uploadSingleFile($file, $lead, $activityId, $userId);
                if ($attachment) {
                    $uploadedFiles[] = $attachment;
                }
            }
        }
        
        return $uploadedFiles;
    }

    /**
     * Upload single file
     */
    public function uploadSingleFile(UploadedFile $file, Lead $lead, ?int $activityId = null, ?int $userId = null): ?Attachment
    {
        try {
            // Generate unique filename
            $filename = $this->generateUniqueFilename($file);
            
            // Store file
            $path = $file->storeAs(
                'attachments/' . date('Y/m'),
                $filename,
                'public'
            );

            if (!$path) {
                return null;
            }

            // Create attachment record
            $attachment = Attachment::create([
                'lead_id' => $lead->id,
                'activity_id' => $activityId,
                'user_id' => $userId ?? auth()->id(),
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType()
            ]);

            return $attachment;

        } catch (\Exception $e) {
            \Log::error('File upload failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate file
     */
    public function validateFile(UploadedFile $file): bool
    {
        // Check file size
        if ($file->getSize() > $this->maxFileSize) {
            return false;
        }

        // Check file type
        $mimeType = $file->getMimeType();
        $allowedTypes = array_merge(
            $this->allowedImageTypes,
            $this->allowedDocumentTypes,
            $this->allowedSpreadsheetTypes
        );

        return in_array($mimeType, $allowedTypes);
    }

    /**
     * Generate unique filename
     */
    protected function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        return Str::random(32) . '.' . $extension;
    }

    /**
     * Delete file
     */
    public function deleteFile(Attachment $attachment): bool
    {
        try {
            // Delete file from storage
            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            // Delete attachment record
            $attachment->delete();

            return true;

        } catch (\Exception $e) {
            \Log::error('File deletion failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get file URL
     */
    public function getFileUrl(Attachment $attachment): string
    {
        return Storage::url($attachment->file_path);
    }

    /**
     * Check if file is an image
     */
    public function isImage(Attachment $attachment): bool
    {
        return in_array($attachment->mime_type, $this->allowedImageTypes);
    }

    /**
     * Get file size in human-readable format
     */
    public function getFormattedFileSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return $bytes . ' byte';
        }
        
        return '0 bytes';
    }

    /**
     * Get allowed file types for validation messages
     */
    public function getAllowedFileTypes(): array
    {
        return [
            'images' => ['jpeg', 'jpg', 'png', 'gif', 'webp'],
            'documents' => ['pdf', 'doc', 'docx'],
            'spreadsheets' => ['xls', 'xlsx']
        ];
    }

    /**
     * Get max file size in MB
     */
    public function getMaxFileSizeMB(): int
    {
        return $this->maxFileSize / 1048576;
    }

    /**
     * Clean up old files (for maintenance)
     */
    public function cleanupOldFiles(int $days = 90): int
    {
        $oldAttachments = Attachment::where('created_at', '<', now()->subDays($days))->get();
        
        $deletedCount = 0;
        foreach ($oldAttachments as $attachment) {
            if ($this->deleteFile($attachment)) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }
}
