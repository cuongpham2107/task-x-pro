<?php

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders file upload component when new files contain null entries', function () {
    Livewire::test(FileUploadTestComponent::class)
        ->assertSee('Đính kèm tệp');
});

it('renders attachment link without throwing when media is present', function () {
    Livewire::test(FileUploadWithAttachmentTestComponent::class)
        ->assertSee('open_in_new');
});

class FileUploadTestComponent extends Component
{
    public array $files = [null];

    public array $existingAttachments = [];

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('tests.livewire.file-upload-host');
    }
}

class FileUploadWithAttachmentTestComponent extends Component
{
    public array $files = [];

    public array $existingAttachments = [];

    public function mount(): void
    {
        Storage::fake('public');

        $task = Task::factory()->create();
        $uploader = User::factory()->create();

        $attachment = TaskAttachment::query()->create([
            'task_id' => $task->id,
            'uploader_id' => $uploader->id,
            'original_name' => 'demo.pdf',
            'stored_path' => '',
            'disk' => 'public',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'version' => 1,
            'created_at' => now(),
        ]);

        $media = $attachment->addMedia(UploadedFile::fake()->create('demo.pdf', 1, 'application/pdf'))
            ->toMediaCollection('attachment');

        $attachment->forceFill([
            'stored_path' => $media->getPathRelativeToRoot(),
            'disk' => $media->disk,
            'mime_type' => $media->mime_type,
            'size_bytes' => $media->size,
        ])->save();

        $this->existingAttachments = [$attachment->load('uploader')];
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('tests.livewire.file-upload-host');
    }
}
