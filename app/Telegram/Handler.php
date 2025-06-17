<?php

namespace App\Telegram;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use App\Models\TelegramUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use DefStudio\Telegraph\Models\TelegraphBot;

class Handler extends WebhookHandler
{
    public function start()
    {
        $name = '';

        if ($this->message->from()->firstName()) {
            $name .= $this->message->from()->firstName();
        }

        if ($this->message->from()->lastName()) {
            $name .= ' '.$this->message->from()->lastName();
        }

        $reply = 'Привет!';

        if ($name) {
            $reply .= ' '.$name;
        }

        TelegramUser::firstOrCreate(
            ['telegram_id' => $this->message->from()->id()],
            [
                'username'   => $this->message->from()->username(),
                'first_name' => $this->message->from()->firstName(),
                'last_name'  => $this->message->from()->lastName(),
            ]
        );

        $this->reply($reply);
    }

    public function help()
    {
        $this->reply(
    "Доступні команди:\n".
            "/start - Запуск бота та реєстрація користувача.\n".
            "/help - Вивід довідки по командам бота."
        );
    }

    public function task()
    {
        $text = $this->message->text();

        $payload = trim(str_replace('/task', '', $text));

        [$title, $description] = array_pad(explode('|', $payload, 2), 2, null);

        $title = trim($title);
        $description = trim($description ?? '');

        if (empty($title)) {
            $this->chat->message('Пожалуйста, укажи заголовок задачи. Пример: /task Купить хлеб | Не забудь взять сдачу')->send();
            return;
        }

        $telegramId = $this->message->from()->id();

        $user = TelegramUser::firstOrCreate(
            ['telegram_id' => $telegramId],
            [
                'username' => $this->message->from()->username(),
                'first_name' => $this->message->from()->firstName(),
                'last_name' => $this->message->from()->lastName(),
            ]
        );

        $task = $user->tasks()->create([
            'title' => $title,
            'description' => $description,
        ]);

        $this->chat->message("✅ Задача создана:\n*{$task->title}*\n{$task->description}")->send();
    }

    public function tasks(): void
    {
        $text = $this->message->text();

        $payload = trim(str_replace('/tasks', '', $text));

        [$q, $completed] = array_map('trim', array_pad(explode('|', $payload, 2), 2, null));

        $telegramId = $this->message->from()->id();

        $user = TelegramUser::where('telegram_id', $telegramId)->first();

        if (!$user) {
            $this->chat->message('Пользователь не найден')->send();
            return;
        }

        $tasksQuery = $user->tasks()->orderByDesc('created_at');

        if ($q && $q != 'all') {
            $tasksQuery->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($completed && $completed == 'completed') {
            $tasksQuery->where('completed', true);
        }

        if ($completed && $completed == 'uncompleted') {
            $tasksQuery->where('completed', false);
        }

        $tasks = $tasksQuery->get();

        if ($tasks->isEmpty()) {
            $this->chat->message('Список пуст')->send();
            return;
        }

        foreach ($tasks as $task) {
            $status = $task->completed ? '✅ Выполнена' : '⏳ В процессе';

            $text = "📌 *{$task->title}*\n📝 {$task->description}\nСтатус: $status";
            foreach ($task->attachments as $attachment) {
                $url = asset($attachment->file_path);
                $text .= "\n📎 [{$attachment->original_name}]($url)";
            }

            $this->chat->message($text)
                ->markdown()
                ->keyboard(Keyboard::make()->buttons([
                    Button::make("✅ Завершить")->action("completetask")->param('id', $task->id)->param('t_id', $telegramId),
                    Button::make("✏️ Редактировать")->action("edittask")->param('id', $task->id),
                    Button::make("📎 Добавить файл")->action("addfile")->param('id', $task->id),
                ]))
                ->send();
        }
    }

    public function completetask(string $id, string $t_id): void
    {
        $user = TelegramUser::where('telegram_id', $t_id)->first();
        if (!$user) {
            $this->chat->message('Пользователь не найден')->send();
            return;
        }

        $task = $user->tasks()->find($id);
        if (!$task) {
            $this->chat->message('Задача не найдена')->send();

            return;
        }

        $task->completed = true;
        $task->save();

        $this->chat->message("Задача *{$task->title}* отмечена как выполненная ✅")->markdown()->send();
    }

    public function edittask(string $id): void
    {
        $this->chat->message("✏️ Для редактирования задачи отправьте такую команду: */edit_task* $id Новый заголовок | Новое описание")->markdown()->send();
    }

    public function edit_task(): void
    {
        $text = $this->message->text();

        $payload = trim(str_replace('/edit_task', '', $text));

        [$id, $rest] = array_pad(explode(' ', $payload, 2), 2, null);

        if (!$id || !$rest) {
            $this->chat->message("Неверный формат.\nПример: /edit_task 42 Новый заголовок | Новый текст")->send();
            return;
        }

        [$title, $description] = array_map('trim', explode('|', $rest) + [null, null]);

        if (!$title || !$description) {
            $this->chat->message("Пожалуйста, укажите и заголовок, и описание задачи через `|`.")->send();
            return;
        }

        $telegramId = $this->message->from()->id();

        $user = TelegramUser::where('telegram_id', $telegramId)->first();
        if (!$user) {
            $this->chat->message("Пользователь не найден.")->send();
            return;
        }

        $task = $user->tasks()->find($id);
        if (!$task) {
            $this->chat->message("Задача с ID $id не найдена.")->send();
            return;
        }

        $task->title = $title;
        $task->description = $description;
        $task->save();

        $this->chat->message("✅ Задача обновлена:\n*{$task->title}*\n{$task->description}")
            ->markdown()
            ->send();
    }

    public function addfile(string $id): void
    {
        $this->chat->message("📎 Для добавления файлов отправьте такую команду: */add_file* $id")->markdown()->send();
    }

    public function add_file(string $taskId): void
    {
        $telegramId = $this->message->from()->id();
        $user = TelegramUser::where('telegram_id', $telegramId)->first();

        if (!$user) {
            $this->chat->message('Пользователь не найден')->send();
            return;
        }

        $task = $user->tasks()->find($taskId);

        if (!$task) {
            $this->chat->message("Задача #$taskId не найдена")->send();
            return;
        }

        $attachments = [];

        $document = $this->message->document();
        if ($document && $document->id()) {
            $fileId = $document->id();
            $originalName = $document->fileName();
            $filePath = $this->getFilePath($fileId);
            $bot = TelegraphBot::find(1);
            if ($filePath && $bot?->token) {
                $contents = Http::get("https://api.telegram.org/file/bot" . $bot?->token . "/$filePath")->body();
                $localPath = 'tasks/' . uniqid() . '_' . $originalName;

                Storage::disk('public')->put($localPath, $contents);

                $task->attachments()->create([
                    'file_id' => $fileId,
                    'file_path' => "storage/$localPath",
                    'original_name' => $originalName,
                ]);

                $attachments[] = $originalName;
            }
        }

        $photos = $this->message->photos();
        if ($photos) {
            $largest = collect($photos)->sortByDesc(fn($p) => $p->fileSize())->first();

            if ($largest && $largest->id()) {
                $fileId = $largest->id();
                $filePath = $this->getFilePath($fileId);
                $bot = TelegraphBot::find(1);
                if ($filePath && $bot?->token) {
                    $contents = Http::get("https://api.telegram.org/file/bot" . $bot?->token . "/$filePath")->body();
                    $fileName = 'photo_' . uniqid() . '.jpg';
                    $localPath = 'tasks/' . $fileName;

                    Storage::disk('public')->put($localPath, $contents);

                    $task->attachments()->create([
                        'file_id' => $fileId,
                        'file_path' => "storage/$localPath",
                        'original_name' => $fileName,
                    ]);

                    $attachments[] = $fileName;
                }
            }
        }

        if (count($attachments)) {
            $this->chat->message("Файл(ы) успешно прикреплены:\n- " . implode("\n- ", $attachments))->send();
        } else {
            $this->chat->message("Отправьте файл или фото в одном сообщении с командой /addfile {task_id}")->send();
        }
    }

    private function getFilePath(string $fileId): ?string
    {
        $bot = TelegraphBot::find(1);
        if (!$bot?->token) {
            return null;
        }
        $response = Http::get("https://api.telegram.org/bot" . $bot?->token . "/getFile", [
            'file_id' => $fileId,
        ]);

        if ($response->ok() && isset($response['result']['file_path'])) {
            return $response['result']['file_path'];
        }

        return null;
    }
}
