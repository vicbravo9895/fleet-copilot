<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'thread_id', 'user_id', 'title', 'meta'
    ];
    
    protected $casts = [
        'meta' => 'array'
    ];
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'thread_id', 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
