<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DocumentMutationService
{
    /**
     * Update an existing document's metadata.
     *
     * @param  User  $actor  The user performing the update.
     * @param  Document  $document  The document model instance.
     * @param  array  $attributes  The new metadata attributes.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function updateDocument(User $actor, Document $document, array $attributes): Document
    {
        Gate::forUser($actor)->authorize('update', $document);

        $document->update($this->mapDocumentAttributes($attributes));

        return $document->refresh();
    }

    /**
     * Soft delete a document.
     *
     * @param  User  $actor  The user performing the deletion.
     * @param  Document  $document  The document model instance.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function deleteDocument(User $actor, Document $document): void
    {
        Gate::forUser($actor)->authorize('delete', $document);

        $document->delete();
    }

    /**
     * Update an existing document version's metadata.
     * Automatically syncs the document's current_version if needed.
     *
     * @param  User  $actor  The user performing the update.
     * @param  DocumentVersion  $version  The document version instance.
     * @param  array  $attributes  The new metadata attributes.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function updateVersion(User $actor, DocumentVersion $version, array $attributes): DocumentVersion
    {
        Gate::forUser($actor)->authorize('update', $version->document()->firstOrFail());

        DB::transaction(function () use ($version, $attributes): void {
            $version->update($attributes);

            // Update document's current_version to the latest version number
            $latestVersionNumber = $version->document->versions()->max('version_number');
            $version->document->forceFill(['current_version' => $latestVersionNumber])->save();
        });

        return $version->refresh();
    }

    /**
     * Permanently delete a document version.
     *
     * @param  User  $actor  The user performing the deletion.
     * @param  DocumentVersion  $version  The document version instance.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function deleteVersion(User $actor, DocumentVersion $version): void
    {
        Gate::forUser($actor)->authorize('delete', $version->document()->firstOrFail());

        DB::transaction(function () use ($version): void {
            $document = $version->document;
            $version->delete();

            // Update document's current_version after deletion
            $latestVersionNumber = $document->versions()->max('version_number') ?? 0;
            $document->forceFill(['current_version' => $latestVersionNumber])->save();
        });
    }

    /**
     * Create a new document and its first version from a task attachment.
     * This is used for automated synchronization from task workflows.
     *
     * @param  User  $actor  The user triggering the sync.
     * @param  Task  $task  The task associated with the attachment.
     * @param  TaskAttachment  $attachment  The task attachment record.
     * @param  Media  $attachmentMedia  The media object of the attachment.
     * @param  int|null  $projectId  Optional project ID to link.
     */
    public function createFromTaskAttachment(
        User $actor,
        Task $task,
        TaskAttachment $attachment,
        Media $attachmentMedia,
        ?int $projectId
    ): Document {
        // No explicit permission check here as it's an automated background sync
        // triggered by an authorized task attachment upload.

        return DB::transaction(function () use ($actor, $task, $attachment, $attachmentMedia, $projectId) {
            $document = Document::query()->firstOrCreate(
                [
                    'task_id' => $task->id,
                    'name' => $attachment->original_name,
                ],
                [
                    'project_id' => $projectId,
                    'uploader_id' => $actor->id,
                    'document_type' => \App\Enums\DocumentType::Other->value,
                    'description' => 'Tai lieu dong bo tu tep dinh kem task #'.$task->id,
                    'permission' => \App\Enums\DocumentPermission::View->value,
                    'current_version' => 1,
                ],
            );

            if ($document->project_id === null && $projectId !== null) {
                $document->forceFill(['project_id' => $projectId])->save();
            }

            $nextVersionNumber = (int) ($document->versions()->max('version_number') ?? 0) + 1;

            $documentVersion = DocumentVersion::query()->create([
                'document_id' => $document->id,
                'version_number' => $nextVersionNumber,
                'uploader_id' => $actor->id,
                'stored_path' => $attachmentMedia->getPathRelativeToRoot(),
                'change_summary' => 'Dong bo tu file upload trong task',
                'file_size_bytes' => $attachmentMedia->size,
            ]);

            $versionMedia = $documentVersion->addMediaFromDisk(
                $attachmentMedia->getPathRelativeToRoot(),
                $attachmentMedia->disk,
            )
                ->preservingOriginal()
                ->usingFileName($attachmentMedia->file_name)
                ->toMediaCollection('version_file');

            $documentVersion->forceFill([
                'stored_path' => $versionMedia->getPathRelativeToRoot(),
                'file_size_bytes' => $versionMedia->size,
            ])->save();

            $document->forceFill([
                'current_version' => $nextVersionNumber,
            ])->save();

            return $document;
        });
    }

    /**
     * Map raw input attributes to model-friendly fields.
     *
     * @param  array  $attributes  Input attributes.
     * @return array Mapped attributes.
     */
    private function mapDocumentAttributes(array $attributes): array
    {
        return [
            'name' => $attributes['name'] ?? null,
            'description' => $attributes['description'] ?? null,
            'document_type' => $attributes['document_type'] ?? null,
            'permission' => $attributes['permission'] ?? null,
            'google_drive_url' => $attributes['google_drive_url'] ?? null,
            'google_drive_id' => $attributes['google_drive_id'] ?? null,
        ];
    }
}
