<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Services\Dashboard\DashboardService;
use App\Models\User;

new #[Title('Dashboard')] class extends Component {
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
    @if (auth()->user()->hasRole('ceo'))
        <livewire:dashboard.ceo-view :data="$data" />
    @elseif(auth()->user()->hasRole('leader'))
        <livewire:dashboard.leader-view :data="$data" />
    @else
        <livewire:dashboard.user-view :data="$data" />
    @endif
</div>
