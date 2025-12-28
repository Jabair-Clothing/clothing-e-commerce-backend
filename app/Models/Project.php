<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'image',
        'status',
        'longdescription'
    ];

    /**
     * Boot the model and automatically generate slug from title
     */
    protected static function boot()
    {
        parent::boot();

        // Generate slug when creating
        static::creating(function ($project) {
            if (empty($project->slug) && !empty($project->title)) {
                $project->slug = static::generateUniqueSlug($project->title);
            }
        });

        // Update slug when updating title
        static::updating(function ($project) {
            if ($project->isDirty('title')) {
                $project->slug = static::generateUniqueSlug($project->title, $project->id);
            }
        });
    }

    /**
     * Generate a unique slug from title
     */
    protected static function generateUniqueSlug($title, $id = null)
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        // Check if slug exists, if yes, append number
        while (static::where('slug', $slug)->where('id', '!=', $id)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    public function technologies()
    {
        return $this->hasMany(Technology::class);
    }
}
