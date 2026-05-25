<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('KPI')] class extends Component
{
    public string $activeTab = 'personal';
};
?>

<div>
    @if(auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('ceo'))
        <livewire:kpi.leader />
    @elseif(auth()->user()->hasRole('leader'))
        <div class="mb-6">
            <nav class="flex gap-6 border-b border-slate-200 dark:border-slate-700">
                <button wire:click="$set('activeTab', 'personal')"
                        class="flex items-center gap-2 pb-3 text-sm font-semibold transition-colors border-b-2
                               {{ $activeTab === 'personal'
                                   ? 'border-primary text-primary'
                                   : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
                    <span class="material-symbols-outlined text-lg">person</span>
                    Cá nhân
                </button>
                <button wire:click="$set('activeTab', 'team')"
                        class="flex items-center gap-2 pb-3 text-sm font-semibold transition-colors border-b-2
                               {{ $activeTab === 'team'
                                   ? 'border-primary text-primary'
                                   : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
                    <span class="material-symbols-outlined text-lg">groups</span>
                    Quản lý phòng ban
                </button>
            </nav>
        </div>

        @if($activeTab === 'personal')
            <livewire:kpi.pic wire:key="kpi-personal" />
        @else
            <livewire:kpi.leader wire:key="kpi-team" />
        @endif
    @else
        <livewire:kpi.pic />
    @endif
</div>
