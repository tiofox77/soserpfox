<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ManagesFileUploads
{
    /**
     * Upload file with organized structure
     * 
     * @param $file UploadedFile
     * @param string $entityType (clients, suppliers, products, etc.)
     * @param int $entityId
     * @param string $fileType (logo, featured, gallery)
     * @param string $entityName
     * @return string
     */
    protected function uploadFile($file, string $entityType, int $entityId, string $fileType, string $entityName = null): string
    {
        $folder = $entityType . '/' . $entityId;
        
        if ($fileType === 'gallery') {
            $folder .= '/gallery';
        }
        
        $extension = $file->getClientOriginalExtension();
        
        if ($entityName && $fileType !== 'gallery') {
            $fileName = $fileType . '_' . Str::slug($entityName) . '.' . $extension;
        } else {
            $fileName = $fileType . '_' . time() . '.' . $extension;
        }
        
        return $file->storeAs($folder, $fileName, 'public');
    }
    
    /**
     * Delete old file if exists
     */
    protected function deleteOldFile(?string $filePath): void
    {
        if ($filePath && Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }
    }
    
    /**
     * Delete entire entity folder
     */
    protected function deleteEntityFolder(string $entityType, int $entityId): void
    {
        $folder = $entityType . '/' . $entityId;
        
        if (Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->deleteDirectory($folder);
        }
    }
    
    /**
     * Remove specific image from gallery
     */
    protected function removeFromGallery(array $gallery, string $imagePath): array
    {
        $gallery = array_filter($gallery, fn($path) => $path !== $imagePath);
        
        // Delete physical file
        $this->deleteOldFile($imagePath);
        
        return array_values($gallery); // Re-index array
    }
}
