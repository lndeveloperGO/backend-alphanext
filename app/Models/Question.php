<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Question extends Model
{
    protected $fillable = ['category_id', 'question', 'image', 'question_type', 'explanation'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function options()
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
