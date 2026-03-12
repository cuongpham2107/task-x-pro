<?php

namespace App\Services\Documents\Contracts;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

interface DocumentServiceInterface
{
    /**
     * Get list of documents for index screen with filtering and pagination.
     *
     * @param  User|null  $actor  The user performing the action (for authorization).
     * @param  array  $filters  Array of filters (search, document_type, permission, sort, dir).
     * @param  int  $perPage  Number of items per page.
     */
    public function paginateForIndex(?User $actor, array $filters = [], int $perPage = 10): LengthAwarePaginator;

    /**
     * Find a document for editing, ensuring the user has permission to view it.
     *
     * @param  User  $actor  The user performing the action.
     * @param  int  $documentId  The ID of the document.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findDocumentForEdit(User $actor, int $documentId): Document;

    /**
     * Find a document for deletion, ensuring the user has permission to delete it.
     *
     * @param  User  $actor  The user performing the action.
     * @param  int  $documentId  The ID of the document.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findDocumentForDelete(User $actor, int $documentId): Document;

    /**
     * Find a document version for editing, ensuring the user has permission to view the document.
     *
     * @param  User  $actor  The user performing the action.
     * @param  int  $versionId  The ID of the document version.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findVersionForEdit(User $actor, int $versionId): DocumentVersion;

    /**
     * Find a document version for deletion, ensuring the user has permission to delete the document.
     *
     * @param  User  $actor  The user performing the action.
     * @param  int  $versionId  The ID of the document version.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findVersionForDelete(User $actor, int $versionId): DocumentVersion;

    /**
     * Get form options (labels for dropdowns).
     *
     * @param  User|null  $actor  The user performing the action.
     * @return array{
     *     document_type_labels: array<string, string>,
     *     permission_labels: array<string, string>
     * }
     */
    public function formOptions(?User $actor): array;

    /**
     * Update a document's metadata.
     *
     * @param  User  $actor  The user performing the action.
     * @param  Document  $document  The document to update.
     * @param  array  $attributes  The attributes to update.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function updateDocument(User $actor, Document $document, array $attributes): Document;

    /**
     * Delete a document.
     *
     * @param  User  $actor  The user performing the action.
     * @param  Document  $document  The document to delete.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function deleteDocument(User $actor, Document $document): void;

    /**
     * Update a document version's metadata.
     *
     * @param  User  $actor  The user performing the action.
     * @param  DocumentVersion  $version  The version to update.
     * @param  array  $attributes  The attributes to update.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function updateVersion(User $actor, DocumentVersion $version, array $attributes): DocumentVersion;

    /**
     * Delete a document version.
     *
     * @param  User  $actor  The user performing the action.
     * @param  DocumentVersion  $version  The version to delete.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function deleteVersion(User $actor, DocumentVersion $version): void;

    /**
     * Create a new document and version from a task attachment.
     *
     * @param  User  $actor  The user performing the action.
     * @param  Task  $task  The task the attachment belongs to.
     * @param  TaskAttachment  $attachment  The task attachment record.
     * @param  Media  $attachmentMedia  The media object of the attachment.
     * @param  int|null  $projectId  The project ID.
     */
    public function createFromTaskAttachment(
        User $actor,
        Task $task,
        TaskAttachment $attachment,
        Media $attachmentMedia,
        ?int $projectId
    ): Document;
}
