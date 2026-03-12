<?php

namespace Tests\Unit\Services\Documents;

use App\Models\Document;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Services\Documents\DocumentMutationService;
use App\Services\Documents\DocumentQueryService;
use App\Services\Documents\DocumentService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Mockery\MockInterface;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentService $service;

    private DocumentQueryService|MockInterface $queryService;

    private DocumentMutationService|MockInterface $mutationService;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queryService = Mockery::mock(DocumentQueryService::class);
        $this->mutationService = Mockery::mock(DocumentMutationService::class);
        $this->service = new DocumentService($this->queryService, $this->mutationService);

        $this->actor = User::factory()->make(['id' => 1]);
    }

    public function test_paginate_for_index_authorizes_and_calls_query_service()
    {
        Gate::shouldReceive('forUser')->with($this->actor)->once()->andReturnSelf();
        Gate::shouldReceive('authorize')->with('viewAny', Document::class)->once();

        $paginator = Mockery::mock(LengthAwarePaginator::class);
        $this->queryService->shouldReceive('paginateForIndex')->with([], 10)->once()->andReturn($paginator);

        $result = $this->service->paginateForIndex($this->actor, [], 10);

        $this->assertSame($paginator, $result);
    }

    public function test_find_document_for_edit_authorizes_and_calls_query_service()
    {
        $document = Document::factory()->make(['id' => 1]);

        $this->queryService->shouldReceive('findDocumentForEdit')->with(1)->once()->andReturn($document);

        Gate::shouldReceive('forUser')->with($this->actor)->once()->andReturnSelf();
        Gate::shouldReceive('authorize')->with('view', $document)->once();

        $result = $this->service->findDocumentForEdit($this->actor, 1);

        $this->assertSame($document, $result);
    }

    public function test_find_document_for_delete_authorizes_and_calls_query_service()
    {
        $document = Document::factory()->make(['id' => 1]);

        $this->queryService->shouldReceive('findDocumentForEdit')->with(1)->once()->andReturn($document);

        Gate::shouldReceive('forUser')->with($this->actor)->once()->andReturnSelf();
        Gate::shouldReceive('authorize')->with('delete', $document)->once();

        $result = $this->service->findDocumentForDelete($this->actor, 1);

        $this->assertSame($document, $result);
    }

    public function test_create_from_task_attachment_calls_mutation_service()
    {
        $task = Task::factory()->make();
        $attachment = TaskAttachment::factory()->make();
        $media = new Media;
        $projectId = 1;
        $document = Document::factory()->make();

        $this->mutationService->shouldReceive('createFromTaskAttachment')
            ->with($this->actor, $task, $attachment, $media, $projectId)
            ->once()
            ->andReturn($document);

        $result = $this->service->createFromTaskAttachment($this->actor, $task, $attachment, $media, $projectId);

        $this->assertSame($document, $result);
    }

    // Add more tests for other methods...
}
