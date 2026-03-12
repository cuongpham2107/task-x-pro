<?php

namespace App\Policies;

use App\Models\PhaseTemplate;
use App\Models\User;

class PhaseTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('phase_template.view');
    }

    public function view(User $user, PhaseTemplate $phaseTemplate): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('phase_template.create');
    }

    public function update(User $user, PhaseTemplate $phaseTemplate): bool
    {
        return $user->can('phase_template.update');
    }

    public function delete(User $user, PhaseTemplate $phaseTemplate): bool
    {
        return $user->can('phase_template.delete');
    }
}
