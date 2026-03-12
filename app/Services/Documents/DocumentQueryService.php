<?php

namespace App\Services\Documents;

use App\Enums\DocumentPermission;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentQueryService
{
    /**
     * Get a paginated list of documents for the index screen.
     *
     * @param  User  $user  The user to filter by.
     * @param  array  $filters  Associative array of filters:
     *                          - search: string (search by name, description, project, task)
     *                          - document_type: string (value of DocumentType enum)
     *                          - permission: string (value of DocumentPermission enum)
     *                          - sort: string (column to sort by)
     *                          - dir: string (asc or desc)
     * @param  int  $perPage  Number of items per page.
     */
    public function paginateForIndex(User $user, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Document::query()
            ->accessibleBy($user)
            ->with([
                'project:id,name',
                'task:id,name',
                'uploader:id,name,email,avatar',
                'versions' => function (HasMany $query): void {
                    $query
                        ->with([
                            'uploader:id,name,email,avatar',
                            'media',
                        ])
                        ->orderByDesc('version_number');
                },
            ])
            ->withCount('versions');

        $this->applyFilters($query, $filters);

        $sortBy = $filters['sort'] ?? 'created_at';
        $sortDir = $filters['dir'] ?? 'desc';
        $allowedSorts = ['name', 'document_type', 'permission', 'current_version', 'created_at'];
        $sortColumn = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $sortDirection = $sortDir === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Find a specific document for editing.
     * Eager loads necessary relationships for the edit form.
     *
     * @param  int  $documentId  The ID of the document.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findDocumentForEdit(int $documentId): Document
    {
        return Document::query()
            ->with([
                'project:id,name',
                'task:id,name',
                'uploader:id,name,email,avatar',
            ])
            ->findOrFail($documentId);
    }

    /**
     * Find a specific document version for editing.
     * Eager loads necessary relationships.
     *
     * @param  int  $versionId  The ID of the version.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findVersionForEdit(int $versionId): DocumentVersion
    {
        return DocumentVersion::query()
            ->with([
                'document:id,name,current_version,uploader_id',
                'uploader:id,name,email,avatar',
            ])
            ->findOrFail($versionId);
    }

    /**
     * Get options for document forms (select inputs).
     *
     * @return array{
     *     document_type_labels: array<string, string>,
     *     permission_labels: array<string, string>
     * }
     */
    public function formOptions(): array
    {
        return [
            'document_type_labels' => DocumentType::options(),
            'permission_labels' => DocumentPermission::options(),
        ];
    }

    /**
     * Apply search and filter conditions to the query builder.
     *
     * @param  Builder  $query  The Eloquent query builder.
     * @param  array  $filters  The filters array.
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('google_drive_id', 'like', "%{$search}%")
                    ->orWhereHas('project', function (Builder $projectQuery) use ($search): void {
                        $projectQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('task', function (Builder $taskQuery) use ($search): void {
                        $taskQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $documentType = trim((string) ($filters['document_type'] ?? ''));
        if ($documentType !== '') {
            $query->where('document_type', $documentType);
        }

        $permission = trim((string) ($filters['permission'] ?? ''));
        if ($permission !== '') {
            $query->where('permission', $permission);
        }
    }
}
