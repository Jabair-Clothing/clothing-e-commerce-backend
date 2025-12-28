<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    /**
     * Upload single file
     */
    public static function upload($file, $folder = 'uploads', $prefix = 'zantech')
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();

        $filename = Str::slug($originalName, '_') . "_{$prefix}_" . time() . '.' . $extension;

        Storage::disk('media')->putFileAs($folder, $file, $filename);

        return $folder . '/' . $filename;
    }

    /**
     * Delete file if exists
     */
    public static function delete($path)
    {
        if ($path && Storage::disk('media')->exists($path)) {
            Storage::disk('media')->delete($path);
        }
    }

    /**
     * Upload multiple files
     */
    public static function uploadMultiple($files, $folder = 'uploads', $prefix = 'zantech', $name = null)
    {
        $paths = [];

        foreach ($files as $file) {
            $cleanName = $name ? Str::slug($name, '_') : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            $filename = $cleanName . "_{$prefix}_" . time() . '.' . $extension;

            Storage::disk('media')->putFileAs($folder, $file, $filename);
            $paths[] = $folder . '/' . $filename;
        }

        return $paths;
    }


    /**
     * Get full URL for a single file
     */
    public static function getUrl($path)
    {
        return $path ? Storage::disk('media')->url($path) : null;
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
            return Storage::disk('media')->url($path);
        }, $paths);
    }
}
