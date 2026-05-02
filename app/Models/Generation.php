<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Generation extends Model
{
    use HasFactory, SoftDeletes;

    const TYPE_IMAGE = 'image';
    const TYPE_VIDEO = 'video';
    const TYPE_TEXT = 'text';
    const TYPE_BODY = 'body';

    protected $fillable = [
        'user_id',
        'type',
        'model_used',
        'prompt',
        'negative_prompt',
        'parameters',
        'status',
        'output_url',
        'output_files',
        'processing_time',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'parameters' => 'array',
        'output_files' => 'array',
        'metadata' => 'array',
    ];

    protected $user;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }
}
