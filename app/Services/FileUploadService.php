<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    /**
     * Upload single file
     */
    public static function upload($file, $folder = 'uploads', $prefix = 'clothing')
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();

        $filename = Str::slug($originalName, '_') . "_{$prefix}_" . time() . '.' . $extension;

        Storage::disk('public')->putFileAs($folder, $file, $filename);

        return $folder . '/' . $filename;
    }

    /**
     * Delete file if exists
     */
    public static function delete($path)
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Upload multiple files
     */
    public static function uploadMultiple($files, $folder = 'uploads', $prefix = 'clothing', $name = null)
    {
        $paths = [];

        foreach ($files as $file) {
            $cleanName = $name
                ? Str::slug($name, '_')
                : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            $extension = $file->getClientOriginalExtension();
            $filename = $cleanName . "_{$prefix}_" . time() . '.' . $extension;

            Storage::disk('public')->putFileAs($folder, $file, $filename);
            $paths[] = $folder . '/' . $filename;
        }

        return $paths;
    }

    /**
     * Get full URL for a single file
     */
    public static function getUrl($path)
    {
        return $path ? asset('storage/' . $path) : null;
    }

    /**
     * Get full URLs for multiple files
     */
    public static function getUrls($paths)
    {
        if (!$paths || !is_array($paths)) {
            return [];
        }

        return array_map(function ($path) {
            return asset('storage/' . $path);
        }, $paths);
    }
}
