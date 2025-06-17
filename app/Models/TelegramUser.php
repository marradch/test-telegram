<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramUser extends Model
{
    protected $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'last_name'
    ];

    public function tasks()
    {
        return $this->hasMany(Task::class, 'telegram_user_id');
    }
}
