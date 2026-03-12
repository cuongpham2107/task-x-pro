<?php

namespace Tests\Unit\Services\Documents;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Documents\DocumentQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DocumentQueryService;
    }

    public function test_paginate_for_index_returns_documents()
    {
        Document::factory()->count(5)->create();

        $result = $this->service->paginateForIndex([], 10);

        $this->assertEquals(5, $result->total());
    }

    public function test_paginate_for_index_filters_by_search()
    {
        Document::factory()->create(['name' => 'Target Document']);
        Document::factory()->create(['name' => 'Other Document']);

        $result = $this->service->paginateForIndex(['search' => 'Target'], 10);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('Target Document', $result->items()[0]->name);
    }

    public function test_find_document_for_edit_returns_document_with_relations()
    {
        $document = Document::factory()
            ->for(Project::factory())
            ->for(Task::factory())
            ->for(User::factory(), 'uploader')
            ->create();

        $result = $this->service->findDocumentForEdit($document->id);

        $this->assertTrue($result->relationLoaded('project'));
        $this->assertTrue($result->relationLoaded('task'));
        $this->assertTrue($result->relationLoaded('uploader'));
        $this->assertEquals($document->id, $result->id);
    }

    public function test_summary_stats_returns_correct_counts()
    {
        Document::factory()->count(2)->create();
        Document::factory()->create(['google_drive_url' => 'http://drive.com']);
        Document::factory()->create(['document_type' => DocumentType::Deliverable->value]);
        DocumentVersion::factory()->for(Document::factory())->create();

        $stats = $this->service->summaryStats();

        // Total 4 documents + 1 document from version factory = 5
        $this->assertEquals(5, $stats['total_documents']);
        // 1 version created explicitly
        $this->assertEquals(1, $stats['total_versions']);
        $this->assertEquals(1, $stats['drive_linked_documents']);
        $this->assertEquals(1, $stats['deliverable_documents']);
    }
}
