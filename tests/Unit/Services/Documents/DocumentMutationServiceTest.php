<?php

namespace Tests\Unit\Services\Documents;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\Documents\DocumentMutationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DocumentMutationServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentMutationService $service;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DocumentMutationService;
        $this->actor = User::factory()->create();
    }

    public function test_update_document_updates_attributes()
    {
        $document = Document::factory()->create([
            'document_type' => DocumentType::Other->value,
        ]);
        Gate::shouldReceive('forUser')->with($this->actor)->andReturnSelf();
        Gate::shouldReceive('authorize')->with('update', $document)->once();

        $result = $this->service->updateDocument($this->actor, $document, [
            'name' => 'New Name',
            'document_type' => DocumentType::Sop->value,
            'description' => 'New Description',
        ]);

        $this->assertEquals('New Name', $result->name);
        $this->assertEquals(DocumentType::Sop, $result->document_type);
        $this->assertEquals('New Description', $result->description);
    }

    public function test_delete_document_deletes_record()
    {
        $document = Document::factory()->create();
        Gate::shouldReceive('forUser')->with($this->actor)->andReturnSelf();
        Gate::shouldReceive('authorize')->with('delete', $document)->once();

        $this->service->deleteDocument($this->actor, $document);

        $this->assertSoftDeleted($document);
    }

    public function test_update_version_updates_attributes()
    {
        $document = Document::factory()->create();
        $version = DocumentVersion::factory()->create(['document_id' => $document->id]);

        Gate::shouldReceive('forUser')->with($this->actor)->andReturnSelf();

        // Mock the authorize call. The problem is that $version->document() creates a NEW instance of Document model when accessed via relationship if not eager loaded or cached.
        // Even with firstOrFail, it's a new instance. Mockery expects the EXACT object instance or we need to use Mockery::on or simply check the arguments loosely.
        // However, standard Gate facade mocking checks for arguments equality.
        // A workaround is to use Mockery::any() for the second argument or ensure we pass the same instance.
        // But the service method fetches the document itself: $document = $version->document()->firstOrFail();
        // So we can't inject the document instance into the service easily.
        // We should use `Mockery::type(Document::class)` or check ID.

        Gate::shouldReceive('authorize')
            ->with('update', \Mockery::on(fn ($arg) => $arg instanceof Document && $arg->id === $document->id))
            ->once();

        $result = $this->service->updateVersion($this->actor, $version, [
            'version_number' => 2,
            'change_summary' => 'Updated',
        ]);

        $this->assertEquals(2, $result->version_number);
        $this->assertEquals('Updated', $result->change_summary);
        $this->assertEquals(2, $document->refresh()->current_version);
    }

    public function test_delete_version_deletes_record()
    {
        $document = Document::factory()->create();
        $version = DocumentVersion::factory()->create(['document_id' => $document->id]);

        Gate::shouldReceive('forUser')->with($this->actor)->andReturnSelf();
        Gate::shouldReceive('authorize')
            ->with('delete', \Mockery::on(fn ($arg) => $arg instanceof Document && $arg->id === $document->id))
            ->once();

        $this->service->deleteVersion($this->actor, $version);

        $this->assertDatabaseMissing('document_versions', ['id' => $version->id]);
    }
}
