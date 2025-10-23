<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageReport extends Model
{
    protected $fillable = [
        'message_id',
        'reported_by',
        'reviewed_by',
        'reason',
        'description',
        'status',
        'moderator_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReviewed($query)
    {
        return $query->whereIn('status', ['reviewed', 'resolved', 'dismissed']);
    }
}