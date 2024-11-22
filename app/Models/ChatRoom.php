<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatRoom extends Model
{
    protected $fillable = [
        'name',
        'type',
    ];

    protected $with = ['participants', 'lastMessage'];

    public function participants()
    {
        return $this->belongsToMany(User::class, 'chat_room_user')
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
}