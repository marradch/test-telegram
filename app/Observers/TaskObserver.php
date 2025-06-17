<?php

namespace App\Observers;

use App\Models\Task;
use DefStudio\Telegraph\Facades\Telegraph;

class TaskObserver
{
    public function updated(Task $task): void
    {
        if ($task->isDirty('completed')) {
            $old = $task->getOriginal('completed');
            $new = $task->completed;

            Telegraph::chat(config('telegraph.group_chat_id'))
                ->message("Статус задачи *{$task->title}* изменился с *" . ($old ? 'completed' : 'uncompleted') . "* на *" . ($new ? 'completed' : 'uncompleted') . '*')
                ->markdown()
                ->send();
        }
    }
}
