<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Document;
use App\Models\KpiScore;
use App\Models\Phase;
use App\Models\PhaseTemplate;
use App\Models\Project;
use App\Models\SlaConfig;
use App\Models\SystemNotification;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Policies\ActivityLogPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\KpiScorePolicy;
use App\Policies\PermissionPolicy;
use App\Policies\PhasePolicy;
use App\Policies\PhaseTemplatePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\RolePolicy;
use App\Policies\SlaConfigPolicy;
use App\Policies\SystemNotificationPolicy;
use App\Policies\TaskAttachmentPolicy;
use App\Policies\TaskPolicy;
use App\Policies\UserPolicy;
use App\Services\Documents\Contracts\DocumentServiceInterface;
use App\Services\Documents\DocumentService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Blaze\Blaze;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DocumentServiceInterface::class, DocumentService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blaze::optimize()->in(resource_path('views/components/ui'));

        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Phase::class, PhasePolicy::class);
        Gate::policy(PhaseTemplate::class, PhaseTemplatePolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(TaskAttachment::class, TaskAttachmentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ActivityLog::class, ActivityLogPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(SlaConfig::class, SlaConfigPolicy::class);
        Gate::policy(KpiScore::class, KpiScorePolicy::class);
        Gate::policy(SystemNotification::class, SystemNotificationPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

        Gate::before(function (User $user, string $ability): ?bool {
            if (
                $user->hasRole('super_admin')
                || $user->getAllPermissions()->contains('name', 'super_admin')
            ) {
                return true;
            }

            return null;
        });

        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('telegram', \SocialiteProviders\Telegram\Provider::class);
        });
    }
}
