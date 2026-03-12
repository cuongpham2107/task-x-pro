<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Projects\ProjectQueryService;
use App\Services\Tasks\TaskQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __construct(
        protected ProjectQueryService $projectQueryService,
        protected TaskQueryService $taskQueryService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim($request->get('q', ''));
        $actor = auth()->user();

        if (empty($q) || ! $actor) {
            return response()->json([
                'projects' => [],
                'tasks' => [],
            ]);
        }

        // Search Projects
        $projectQuery = Project::query();
        $this->projectQueryService->scopeVisibility($projectQuery, $actor);
        $projects = $projectQuery->where(function ($query) use ($q) {
            $query->where('name', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%");
        })->limit(5)->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'code' => $p->code,
            'status' => (string) ($p->status->label() ?? $p->status),
            'url' => route('projects.phases.index', $p->id),
        ]);

        // Search Tasks
        $taskQuery = $this->taskQueryService->taskScopeForActor($actor);
        $tasks = $taskQuery->where('name', 'like', "%{$q}%")
            ->with(['phase', 'phase.project'])
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->name,
                'project' => $t->phase?->project?->name,
                'status' => (string) ($t->status->label() ?? $t->status),
                'url' => route('projects.phases.tasks.index', [$t->phase->project_id, $t->phase_id]),
            ]);

        return response()->json([
            'projects' => $projects,
            'tasks' => $tasks,
        ]);
    }
}
