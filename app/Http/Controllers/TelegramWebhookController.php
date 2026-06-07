<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = config('services.telegram-bot-api.token');
        if ($token === null || $token === '') {
            return response()->json(['ok' => false, 'error' => 'Bot token not configured'], 500);
        }

        $update = $request->all();

        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query'], $token);
        } elseif (isset($update['message'])) {
            $this->handleMessage($update['message'], $token);
        }

        return response()->json(['ok' => true]);
    }

    private function handleMessage(array $message, string $token): void
    {
        $chatId = $message['chat']['id'];
        $text = trim($message['text'] ?? '');

        if ($text === '/start' || $text === 'Kiểm tra tiến độ dự án') {
            $this->sendProjectList($chatId, $token);
        }
    }

    private function handleCallbackQuery(array $callback, string $token): void
    {
        $chatId = $callback['message']['chat']['id'];
        $data = $callback['data'] ?? '';

        if (str_starts_with($data, 'project_')) {
            $projectId = (int) str_replace('project_', '', $data);
            $this->sendProjectProgress($chatId, $projectId, $token);
        }

        try {
            Http::post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
                'callback_query_id' => $callback['id'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to answer callback query: '.$e->getMessage());
        }
    }

    private function sendProjectList(int $chatId, string $token): void
    {
        $projects = Project::query()
            ->whereNotIn('status', [
                ProjectStatus::Completed,
                ProjectStatus::Cancelled,
            ])
            ->get(['id', 'name']);

        if ($projects->isEmpty()) {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => 'Hiện không có dự án nào đang chạy.',
            ]);

            return;
        }

        $keyboard = [];
        foreach ($projects as $project) {
            $keyboard[] = [
                ['text' => $project->name, 'callback_data' => "project_{$project->id}"],
            ];
        }

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => 'Vui lòng chọn dự án cần kiểm tra:',
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard,
            ]),
        ]);
    }

    private function sendProjectProgress(int $chatId, int $projectId, string $token): void
    {
        $project = Project::with(['phases.tasks'])->find($projectId);
        if ($project === null) {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => 'Không tìm thấy dự án.',
            ]);

            return;
        }

        $projectProgress = $project->progress ?? 0;
        $deadlineText = $project->end_date?->format('d/m/Y') ?? 'N/A';

        $content = "🔎 BÁO CÁO TIẾN ĐỘ DỰ ÁN: {$project->name}\n";
        $content .= "📊 Tiến độ tổng thể: {$projectProgress}% | 🗓️ Deadline: {$deadlineText}\n";

        foreach ($project->phases as $phase) {
            $tasks = $phase->tasks ?? collect();
            $total = $tasks->count();
            $completed = $tasks->filter(fn ($t) => $t->status === TaskStatus::Completed)->count();
            $inProgress = $tasks->filter(fn ($t) => $t->status === TaskStatus::InProgress || $t->status === TaskStatus::WaitingApproval)->count();
            $todo = $tasks->filter(fn ($t) => $t->status === TaskStatus::Pending || $t->status === TaskStatus::Init)->count();
            $late = $tasks->filter(fn ($t) => $t->status === TaskStatus::Late)->count();
            $phaseProgress = $total > 0 ? round(($completed / $total) * 100) : 0;

            $content .= "\n🔖 Giai đoạn \"{$phase->name}\" — đạt {$phaseProgress}%\n";
            $content .= "   ✅ Hoàn thành: {$completed} | ⏳ Đang chạy: {$inProgress} | ⬜ Chưa làm: {$todo} | ❌ Trễ hạn: {$late}\n";

            foreach ($tasks as $task) {
                $taskDeadline = $task->deadline?->format('d/m/Y') ?? 'N/A';
                $content .= "   • \"{$task->name}\" — {$task->status->label()} — {$taskDeadline}\n";
            }
        }

        if (mb_strlen($content) > 4000) {
            $content = mb_substr($content, 0, 3997).'...';
        }

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $content,
        ]);
    }
}
