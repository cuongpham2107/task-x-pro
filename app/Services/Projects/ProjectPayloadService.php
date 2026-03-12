<?php

namespace App\Services\Projects;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\UserStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ProjectPayloadService
{
    /**
     * Chuan hoa payload project de dam bao chi ghi cac field hop le.
     *
     * @return array<string, mixed>
     */
    public function normalizedProjectAttributes(array $attributes, int $actorId, bool $isUpdate): array
    {
        $allowedFields = [
            'name',
            'type',
            'status',
            'budget',
            'budget_spent',
            'objective',
            'start_date',
            'end_date',
        ];

        $payload = collect($attributes)
            ->only($allowedFields)
            ->toArray();

        if (! $isUpdate) {
            $requiredFields = ['name', 'type'];

            foreach ($requiredFields as $requiredField) {
                if (! array_key_exists($requiredField, $payload) || $payload[$requiredField] === null || $payload[$requiredField] === '') {
                    throw ValidationException::withMessages([
                        $requiredField => 'Truong nay la bat buoc khi tao project.',
                    ]);
                }
            }

            $payload['created_by'] = $actorId;
        }

        if (array_key_exists('type', $payload) && $payload['type'] !== null && ! in_array($payload['type'], ProjectType::values(), true)) {
            throw ValidationException::withMessages([
                'type' => 'Loai du an khong hop le.',
            ]);
        }

        if (array_key_exists('status', $payload) && $payload['status'] !== null && ! in_array($payload['status'], ProjectStatus::values(), true)) {
            throw ValidationException::withMessages([
                'status' => 'Trang thai du an khong hop le.',
            ]);
        }

        return $payload;
    }

    /**
     * Dong bo danh sach leader cua project vao pivot project_leaders.
     */
    public function syncLeaders(Project $project, array $leaderIds, int $assignedBy): void
    {
        $normalizedLeaderIds = collect($leaderIds)
            ->filter(function (mixed $leaderId): bool {
                return $leaderId !== null && $leaderId !== '';
            })
            ->map(function (mixed $leaderId): int {
                return (int) $leaderId;
            })
            ->unique()
            ->values();

        if ($normalizedLeaderIds->isEmpty()) {
            $project->leaders()->sync([]);

            return;
        }

        $validLeaderIds = User::query()
            ->role(['ceo', 'leader', 'super_admin'])
            ->where('status', UserStatus::Active->value)
            ->whereIn('id', $normalizedLeaderIds)
            ->pluck('id')
            ->map(function (mixed $leaderId): int {
                return (int) $leaderId;
            })
            ->values();

        if ($validLeaderIds->count() !== $normalizedLeaderIds->count()) {
            throw ValidationException::withMessages([
                'leader_ids' => 'Danh sach leader co user khong hop le hoac da ngung hoat dong.',
            ]);
        }

        $assignedAt = now();
        $syncPayload = $validLeaderIds
            ->mapWithKeys(function (int $leaderId) use ($assignedAt, $assignedBy): array {
                return [
                    $leaderId => [
                        'assigned_at' => $assignedAt,
                        'assigned_by' => $assignedBy,
                    ],
                ];
            })
            ->all();

        $project->leaders()->sync($syncPayload);
    }
}
