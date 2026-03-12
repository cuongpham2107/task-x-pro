<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('KPI')] class extends Component
{
};
?>

<div>
    @if(auth()->user()->hasRole('ceo'))
        <livewire:kpi.ceo />
    @elseif(auth()->user()->hasRole('leader'))
        <livewire:kpi.leader />
    @else
        <livewire:kpi.pic />
    @endif
</div>
