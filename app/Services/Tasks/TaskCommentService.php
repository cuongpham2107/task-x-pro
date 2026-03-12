<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TaskCommentService
{
    /**
     * Lay danh sach comment cua task theo thu tu moi nhat truoc.
     *
     * @return Collection<int, TaskComment>
     */
    public function getForTask(User $actor, Task $task): Collection
    {
        Gate::forUser($actor)->authorize('view', $task);

        return $task->comments()
            ->with(['user:id,name,email,avatar', 'user.roles:id,name,guard_name'])
            ->get();
    }

    /**
     * Tao comment moi cho task neu user duoc phep tham gia trao doi.
     */
    public function create(User $actor, Task $task, string $content): TaskComment
    {
        Gate::forUser($actor)->authorize('comment', $task);

        $normalizedContent = trim($content);
        if ($normalizedContent === '') {
            throw ValidationException::withMessages([
                'newComment' => 'Noi dung binh luan khong duoc de trong.',
            ]);
        }

        if (mb_strlen($normalizedContent) > 5000) {
            throw ValidationException::withMessages([
                'newComment' => 'Noi dung binh luan khong duoc vuot qua 5000 ky tu.',
            ]);
        }

        return TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'content' => $normalizedContent,
        ])->load(['user:id,name,email,avatar', 'user.roles:id,name,guard_name']);
    }
}
