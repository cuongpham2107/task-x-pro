<?php

use App\Models\SystemNotification;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public function viewDetails(int $notificationId): void
    {
        $notification = SystemNotification::where('user_id', Auth::id())->where('id', $notificationId)->first();

        if (!$notification) {
            return;
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
            $this->dispatch('notification-updated');
        }

        if (in_array($notification->type, [\App\Enums\SystemNotificationType::ApprovalRequestLeader->value, \App\Enums\SystemNotificationType::ApprovalRequestCeo->value])) {
            $task = Task::find($notification->notifiable_id);
            if ($task) {
                $this->redirect(
                    route('projects.phases.tasks.index', [
                        'project' => $task->phase->project_id,
                        'phase' => $task->phase_id,
                        'task' => $task->id,
                    ]),
                    navigate: true,
                );
                $this->dispatch('close-notifications');
            }
        }
    }

    public function markAsRead(int $notificationId): void
    {
        $notification = SystemNotification::where('user_id', Auth::id())->where('id', $notificationId)->first();

        if ($notification && $notification->read_at === null) {
            $notification->update(['read_at' => now()]);
            $this->dispatch('notification-updated');
        }
    }

    public function markAllAsRead(): void
    {
        SystemNotification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->dispatch('notification-updated');
    }

    public function with(): array
    {
        $notifications = SystemNotification::where('user_id', Auth::id())->latest()->paginate(10);

        $unreadCount = SystemNotification::where('user_id', Auth::id())->whereNull('read_at')->count();

        return [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ];
    }
}; ?>

<div x-data="notificationDrawer()" @open-notifications.window="open = true" @close-notifications.window="open = false"
    @keydown.escape.window="open = false" class="relative z-50" role="dialog" aria-modal="true" wire:ignore.self x-cloak>
    {{-- ── Overlay ── --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" @click="open = false"></div>

    {{-- ── Drawer ── --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-x-full opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
        x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0 opacity-100"
        x-transition:leave-end="translate-x-full opacity-0"
        class="fixed inset-y-0 right-0 flex w-full max-w-sm flex-col border-l border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-slate-200 p-6 dark:border-slate-800">
            <div class="flex items-center gap-2">
                <h2 class="text-xl font-bold text-slate-600 dark:text-slate-100">Thông báo</h2>
                @if ($unreadCount > 0)
                    <span
                        class="bg-primary text-2xs flex h-5 w-5 items-center justify-center rounded-full font-bold text-white">
                        {{ $unreadCount }}
                    </span>
                @endif
            </div>
            <div class="flex gap-2">
                <button wire:click="markAllAsRead"
                    class="hover:text-primary flex size-8 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                    title="Đánh dấu tất cả đã đọc">
                    <span class="material-symbols-outlined text-xl">done_all</span>
                </button>
                <button @click="open = false"
                    class="flex size-8 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    title="Đóng">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>

        {{-- Content --}}
        <div class="flex-1 overflow-y-auto">
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @php
                    $getIcon = function ($type) {
                        return match ($type) {
                            \App\Enums\SystemNotificationType::ApprovalRequestLeader->value,
                            \App\Enums\SystemNotificationType::ApprovalRequestCeo->value
                                => [
                                'bg' => 'bg-blue-100 dark:bg-blue-900/30',
                                'text' => 'text-blue-600',
                                'icon' => 'pending_actions',
                            ],
                            'task_overdue' => [
                                'bg' => 'bg-red-100 dark:bg-red-900/30',
                                'text' => 'text-red-600',
                                'icon' => 'event_busy',
                            ],
                            'kpi_update' => [
                                'bg' => 'bg-green-100 dark:bg-green-900/30',
                                'text' => 'text-green-600',
                                'icon' => 'insights',
                            ],
                            'mention' => [
                                'bg' => 'bg-purple-100 dark:bg-purple-900/30',
                                'text' => 'text-purple-600',
                                'icon' => 'alternate_email',
                            ],
                            \App\Enums\SystemNotificationType::TaskRejected->value => [
                                'bg' => 'bg-orange-100 dark:bg-orange-900/30',
                                'text' => 'text-orange-600',
                                'icon' => 'cancel',
                            ],
                            \App\Enums\SystemNotificationType::PicOverloadWarning->value => [
                                'bg' => 'bg-amber-100 dark:bg-amber-900/30',
                                'text' => 'text-amber-600',
                                'icon' => 'warning',
                            ],
                            default => [
                                'bg' => 'bg-slate-100 dark:bg-slate-800',
                                'text' => 'text-slate-600',
                                'icon' => 'notifications',
                            ],
                        };
                    };
                @endphp

                @forelse($notifications as $notification)
                    @php
                        $style = $getIcon($notification->type);
                    @endphp
                    <div wire:key="{{ $notification->id }}"
                        class="{{ $notification->read_at === null ? 'bg-primary/5 hover:bg-primary/10' : 'hover:bg-slate-50 dark:hover:bg-slate-800/50' }} p-6 transition-colors">
                        <div class="flex items-start gap-4">
                            <div
                                class="{{ $style['bg'] }} {{ $style['text'] }} mt-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                                <span class="material-symbols-outlined">{{ $style['icon'] }}</span>
                            </div>
                            <div class="flex-1">
                                <div class="mb-1 flex items-center justify-between">
                                    <h4 class="text-sm font-bold text-slate-600 dark:text-slate-100">
                                        {{ $notification->title }}</h4>
                                    <span
                                        class="text-[11px] text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="mb-4 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                                    {{ $notification->body }}
                                </p>
                                <div class="flex gap-2">
                                    @if ($notification->read_at === null)
                                        <button wire:click="markAsRead({{ $notification->id }})"
                                            class="flex-1 rounded-lg bg-slate-200 py-1.5 text-xs font-semibold text-slate-700 transition-all hover:bg-slate-300 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600">
                                            Đánh dấu đã đọc
                                        </button>
                                    @endif

                                    {{-- Custom actions based on type can be added here --}}
                                    @if(in_array($notification->type, [
                                        \App\Enums\SystemNotificationType::ApprovalRequestLeader->value,
                                        \App\Enums\SystemNotificationType::ApprovalRequestCeo->value,
                                    ]))
                                        <button wire:click="viewDetails({{ $notification->id }})"
                                            class="bg-primary hover:bg-primary/90 flex-1 rounded-lg py-1.5 text-xs font-semibold text-white transition-all">
                                            Xem chi tiết
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div
                        class="flex flex-col items-center justify-center p-8 text-center text-slate-500 dark:text-slate-400">
                        <span class="material-symbols-outlined mb-2 text-4xl opacity-50">notifications_off</span>
                        <p class="text-sm">Không có thông báo nào</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Footer --}}
        <div class="border-t border-slate-200 p-6 dark:border-slate-800">
            {{ $notifications->links() }}
        </div>
    </div>
</div>
