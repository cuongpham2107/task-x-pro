<?php

use App\Models\SystemNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    // Listen for events that might change unread count
    public function getListeners()
    {
        return [
            "echo-private:users." . Auth::id() . ",.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated" => '$refresh',
            'notification-updated' => '$refresh', // Custom event
        ];
    }

    public function with(): array
    {
        $unreadCount = SystemNotification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->count();
            
        return [
            'unreadCount' => $unreadCount,
        ];
    }
}; ?>

<button
    type="button"
    onclick="window.dispatchEvent(new CustomEvent('open-notifications'))"
    aria-label="Open notifications"
    class="relative flex size-10 items-center justify-center rounded-lg bg-slate-100 text-slate-700 transition-colors hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
>
    <span class="material-symbols-outlined">notifications</span>
    @if($unreadCount > 0)
        <span class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white shadow-sm ring-2 ring-white dark:ring-slate-900">
            {{ $unreadCount > 9 ? '9+' : $unreadCount }}
        </span>
    @endif
</button>
