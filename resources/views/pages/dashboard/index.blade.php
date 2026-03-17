<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Services\Dashboard\DashboardService;
use App\Models\User;

new #[Title('Dashboard')] class extends Component
{
    public array $data = [];

    public function mount(DashboardService $dashboardService)
    {
        /** @var User $user */
        $user = auth()->user();
        $this->data = $dashboardService->getIndexData($user);
    }
};
?>

<div>
    <div class="mb-2 flex flex-wrap items-center justify-between gap-4">
        <x-ui.heading title="Tổng quan" description="Thông tin tổng quan về công việc." class="mb-0" />

        
    </div>
    @if(auth()->user()->hasRole('ceo'))
        <livewire:dashboard.ceo-view :data="$data" />
    @elseif(auth()->user()->hasRole('leader'))
        <livewire:dashboard.leader-view :data="$data" />
    @else
        <livewire:dashboard.user-view :data="$data" />
    @endif
</div>