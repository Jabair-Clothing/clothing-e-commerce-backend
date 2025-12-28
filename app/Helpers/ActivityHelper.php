<?php

namespace App\Helpers;

use App\Models\Activity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ActivityHelper
{
    public static function logActivity($relatableId, $type, $description)
    {
        try {
            Activity::create([
                'relatable_id' => $relatableId,
                'type' => $type,
                'user_id' => Auth::id(),
                'description' => $description,
            ]);
        } catch (\Exception $e) {
            // Don’t break the main function, just log error
            Log::error("Activity logging failed: " . $e->getMessage());
        }
    }
}
