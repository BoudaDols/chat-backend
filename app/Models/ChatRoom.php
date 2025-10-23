<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatRoom extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'type',
        'avatar_url',
        'description',
        'created_by',
        'settings',
    ];

    protected $with = ['participants', 'lastMessage'];

    protected $casts = [
        'settings' => 'array',
    ];

    public function participants()
    {
        return $this->belongsToMany(User::class, 'chat_room_user')
            ->withPivot(['role', 'is_muted', 'muted_until'])
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latest();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function admins()
    {
        return $this->belongsToMany(User::class, 'chat_room_user')
            ->wherePivot('role', 'admin')
            ->orWherePivot('role', 'owner');
    }

    public function isAdmin($userId)
    {
        // amazonq-ignore-next-line
        return $this->participants()
            ->where('user_id', $userId)
            ->whereIn('role', ['admin', 'owner'])
            ->exists();
    }

    public function isOwner($userId)
    {
        // amazonq-ignore-next-line
        return $this->participants()
            ->where('user_id', $userId)
            ->where('role', 'owner')
            ->exists();
    }
}