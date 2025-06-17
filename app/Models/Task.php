<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'title',
        'description',
    ];

    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class, 'task_id');
    }
}
