<?php

namespace App\Services\Tasks;

use App\Models\Task;

class TaskMutationResult
{
    /**
     * Dong goi ket qua ghi task de UI co the xu ly du lieu va canh bao cung luc.
     */
    public function __construct(
        public Task $task,
        public ?string $overloadWarning = null,
        public ?\Illuminate\Support\Collection $attachments = null,
    ) {}
}
