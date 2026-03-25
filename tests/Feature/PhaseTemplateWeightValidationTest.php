<?php

use App\Models\PhaseTemplate;
use App\Models\User;
use App\Services\PhaseTemplates\PhaseTemplateMutationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actor = User::factory()->create();
    Gate::define('phase_template.create', fn () => true);
    Gate::define('phase_template.update', fn () => true);
});

it('prevents creating a phase template that exceeds 100% total weight', function () {
    $service = app(PhaseTemplateMutationService::class);

    // Create first template with 60%
    PhaseTemplate::factory()->create([
        'project_type' => 'warehouse',
        'default_weight' => 60,
        'is_active' => true,
    ]);

    // Attempt to create second template with 50% (Total 110%)
    expect(fn () => $service->create($this->actor, [
        'project_type' => 'warehouse',
        'phase_name' => 'Overweight Phase',
        'default_weight' => 50,
        'is_active' => true,
    ]))->toThrow(\Exception::class, 'Tổng trọng số mặc định cho loại dự án này đã vượt quá 100%');
});

it('allows creating a phase template that reaches exactly 100% total weight', function () {
    $service = app(PhaseTemplateMutationService::class);

    // Create first template with 60%
    PhaseTemplate::factory()->create([
        'project_type' => 'warehouse',
        'default_weight' => 60,
        'is_active' => true,
    ]);

    // Create second template with 40% (Total 100%)
    $template = $service->create($this->actor, [
        'project_type' => 'warehouse',
        'phase_name' => 'Full Weight Phase',
        'default_weight' => 40,
        'is_active' => true,
    ]);

    expect($template->default_weight)->toBe(40);
    expect(PhaseTemplate::where('project_type', 'warehouse')->sum('default_weight'))->toEqual(100);
});

it('prevents updating a phase template to a weight that exceeds 100% total', function () {
    $service = app(PhaseTemplateMutationService::class);

    // Create two templates totaling 90%
    PhaseTemplate::factory()->create([
        'project_type' => 'warehouse',
        'default_weight' => 50,
        'is_active' => true,
    ]);

    $templateToUpdate = PhaseTemplate::factory()->create([
        'project_type' => 'warehouse',
        'default_weight' => 40,
        'is_active' => true,
    ]);

    // Attempt to update second template to 60% (Total 110%)
    expect(fn () => $service->update($this->actor, $templateToUpdate, [
        'project_type' => 'warehouse',
        'phase_name' => 'Updated Phase',
        'default_weight' => 60,
        'is_active' => true,
    ]))->toThrow(\Exception::class, 'Tổng trọng số mặc định cho loại dự án này đã vượt quá 100%');
});

it('allows updating a phase template within the 100% limit', function () {
    $service = app(PhaseTemplateMutationService::class);

    // Create two templates totaling 90%
    PhaseTemplate::factory()->create([
        'project_type' => 'warehouse',
        'default_weight' => 50,
        'is_active' => true,
    ]);

    $templateToUpdate = PhaseTemplate::factory()->create([
        'project_type' => 'warehouse',
        'default_weight' => 40,
        'is_active' => true,
    ]);

    // Update second template to 50% (Total 100%)
    $updated = $service->update($this->actor, $templateToUpdate, [
        'project_type' => 'warehouse',
        'phase_name' => 'Updated Phase',
        'default_weight' => 50,
        'is_active' => true,
    ]);

    expect($updated->default_weight)->toBe(50);
    expect(PhaseTemplate::where('project_type', 'warehouse')->sum('default_weight'))->toEqual(100);
});

it('ignores inactive templates when calculating total weight', function () {
    $service = app(PhaseTemplateMutationService::class);

    // Create an inactive template with 80%
    PhaseTemplate::factory()->create([
        'project_type' => 'warehouse',
        'default_weight' => 80,
        'is_active' => false,
    ]);

    // Create an active template with 60% (Should succeed since 80% is inactive)
    $template = $service->create($this->actor, [
        'project_type' => 'warehouse',
        'phase_name' => 'Active Phase',
        'default_weight' => 60,
        'is_active' => true,
    ]);

    expect($template->default_weight)->toBe(60);
});

it('scopes total weight calculation by project type', function () {
    $service = app(PhaseTemplateMutationService::class);

    // Create 80% for warehouse
    PhaseTemplate::factory()->create([
        'project_type' => 'warehouse',
        'default_weight' => 80,
        'is_active' => true,
    ]);

    // Create 60% for customs (Should succeed as it is a different project type)
    $template = $service->create($this->actor, [
        'project_type' => 'customs',
        'phase_name' => 'Customs Phase',
        'default_weight' => 60,
        'is_active' => true,
    ]);

    expect($template->default_weight)->toBe(60);
});
