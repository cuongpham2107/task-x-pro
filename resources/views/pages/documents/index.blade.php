<?php

use App\Enums\DocumentPermission;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Services\Documents\Contracts\DocumentServiceInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Tai lieu')] class extends Component {
    use WithPagination;

    protected DocumentServiceInterface $documentService;

    #[Url(as: 'q', except: '')]
    public ?string $filterSearch = null;

    #[Url(as: 'type', except: '')]
    public ?string $filterDocumentType = null;

    #[Url(as: 'perm', except: '')]
    public ?string $filterPermission = null;

    #[Url(as: 'sort', except: 'created_at')]
    public string $sortBy = 'created_at';

    #[Url(as: 'dir', except: 'desc')]
    public string $sortDir = 'desc';

    public array $expandedNodes = [];

    public bool $showDocumentFormModal = false;

    public ?int $editingDocumentId = null;

    public string $documentName = '';

    public string $documentType = DocumentType::Other->value;

    public string $documentDescription = '';

    public string $documentPermission = DocumentPermission::View->value;

    public string $googleDriveId = '';

    public string $googleDriveUrl = '';

    public int $currentVersion = 1;

    public bool $showVersionFormModal = false;

    public ?int $editingVersionId = null;

    public ?int $editingVersionDocumentId = null;

    public int $versionNumber = 1;

    public string $storedPath = '';

    public string $googleDriveRevisionId = '';

    public string $changeSummary = '';

    public ?string $fileSizeBytes = null;

    public bool $showDeleteDocumentModal = false;

    public ?int $pendingDeleteDocumentId = null;

    public string $pendingDeleteDocumentName = '';

    public bool $showDeleteVersionModal = false;

    public ?int $pendingDeleteVersionId = null;

    public string $pendingDeleteVersionName = '';

    /** @var array<string, string> */
    public array $documentTypeLabels = [];

    /** @var array<string, string> */
    public array $permissionLabels = [];

    public function boot(DocumentServiceInterface $documentService): void
    {
        $this->documentService = $documentService;
    }

    public function mount(): void
    {
        $options = $this->documentService->formOptions(auth()->user());
        $this->documentTypeLabels = $options['document_type_labels'];
        $this->permissionLabels = $options['permission_labels'];
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDocumentType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPermission(): void
    {
        $this->resetPage();
    }

    public function toggleNode(string $nodeKey): void
    {
        if (in_array($nodeKey, $this->expandedNodes)) {
            $this->expandedNodes = array_diff($this->expandedNodes, [$nodeKey]);
        } else {
            $this->expandedNodes[] = $nodeKey;
        }
    }

    public function isNodeExpanded(string $nodeKey): bool
    {
        return in_array($nodeKey, $this->expandedNodes);
    }

    /**
     * @return array<string, array<int|string, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    protected function documentRules(): array
    {
        return [
            'documentName' => ['required', 'string', 'max:500'],
            'documentType' => ['required', Rule::in(DocumentType::values())],
            'documentDescription' => ['nullable', 'string'],
            'documentPermission' => ['required', Rule::in(DocumentPermission::values())],
            'googleDriveId' => ['nullable', 'string', 'max:255'],
            'googleDriveUrl' => ['nullable', 'string', 'max:1000'],
            'currentVersion' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }

    /**
     * @return array<string, array<int|string, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    protected function versionRules(): array
    {
        return [
            'versionNumber' => ['required', 'integer', 'min:1', 'max:9999', Rule::unique('document_versions', 'version_number')->where(fn($query) => $query->where('document_id', $this->editingVersionDocumentId))->ignore($this->editingVersionId)],
            'storedPath' => ['required', 'string', 'max:1000'],
            'googleDriveRevisionId' => ['nullable', 'string', 'max:255'],
            'changeSummary' => ['nullable', 'string'],
            'fileSizeBytes' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function documentMessages(): array
    {
        return [
            'documentName.required' => 'Ten tai lieu la bat buoc.',
            'documentName.max' => 'Ten tai lieu khong duoc vuot qua 500 ky tu.',
            'documentType.required' => 'Loai tai lieu la bat buoc.',
            'documentType.in' => 'Loai tai lieu khong hop le.',
            'documentPermission.required' => 'Quyen tai lieu la bat buoc.',
            'documentPermission.in' => 'Quyen tai lieu khong hop le.',
            'googleDriveId.max' => 'Google Drive ID khong duoc vuot qua 255 ky tu.',
            'googleDriveUrl.max' => 'Google Drive URL khong duoc vuot qua 1000 ky tu.',
            'currentVersion.required' => 'Current version la bat buoc.',
            'currentVersion.integer' => 'Current version phai la so nguyen.',
            'currentVersion.min' => 'Current version toi thieu la 1.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function versionMessages(): array
    {
        return [
            'versionNumber.required' => 'So phien ban la bat buoc.',
            'versionNumber.integer' => 'So phien ban phai la so nguyen.',
            'versionNumber.unique' => 'So phien ban da ton tai trong tai lieu nay.',
            'storedPath.required' => 'Duong dan luu tru la bat buoc.',
            'storedPath.max' => 'Duong dan luu tru khong duoc vuot qua 1000 ky tu.',
            'googleDriveRevisionId.max' => 'Revision ID khong duoc vuot qua 255 ky tu.',
            'fileSizeBytes.integer' => 'Kich thuoc file phai la so nguyen.',
            'fileSizeBytes.min' => 'Kich thuoc file khong hop le.',
        ];
    }

    public function openEditDocumentModal(int $documentId): void
    {
        $this->resetDocumentFormModal();

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        $document = $this->documentService->findDocumentForEdit($actor, $documentId);
        Gate::forUser($actor)->authorize('update', $document);

        $this->editingDocumentId = $document->id;
        $this->documentName = (string) $document->name;
        $this->documentType = $document->document_type instanceof \BackedEnum ? (string) $document->document_type->value : (string) $document->document_type;
        $this->documentDescription = (string) ($document->description ?? '');
        $this->documentPermission = $document->permission instanceof \BackedEnum ? (string) $document->permission->value : (string) $document->permission;
        $this->googleDriveId = (string) ($document->google_drive_id ?? '');
        $this->googleDriveUrl = (string) ($document->google_drive_url ?? '');
        $this->currentVersion = (int) $document->current_version;
        $this->showDocumentFormModal = true;
    }

    public function closeDocumentFormModal(): void
    {
        $this->showDocumentFormModal = false;
        $this->resetDocumentFormModal();
    }

    public function resetDocumentFormModal(): void
    {
        $this->reset(['editingDocumentId', 'documentName', 'documentDescription', 'googleDriveId', 'googleDriveUrl']);

        $this->documentType = DocumentType::Other->value;
        $this->documentPermission = DocumentPermission::View->value;
        $this->currentVersion = 1;
        $this->resetValidation();
    }

    public function saveDocument(): void
    {
        $this->validate($this->documentRules(), $this->documentMessages());

        if ($this->editingDocumentId === null) {
            return;
        }

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        $payload = [
            'name' => $this->documentName,
            'document_type' => $this->documentType,
            'description' => $this->documentDescription,
            'permission' => $this->documentPermission,
            'google_drive_id' => $this->googleDriveId,
            'google_drive_url' => $this->googleDriveUrl,
            'current_version' => $this->currentVersion,
        ];

        try {
            $document = $this->documentService->findDocumentForEdit($actor, $this->editingDocumentId);
            $this->documentService->updateDocument($actor, $document, $payload);

            $this->closeDocumentFormModal();
            unset($this->projects); // Refresh data

            $message = 'Cap nhat tai lieu thanh cong!';
            session()->flash('success', $message);
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        } catch (\Exception $e) {
            $message = 'Khong the cap nhat tai lieu: ' . $e->getMessage();
            session()->flash('error', $message);
            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    public function openEditVersionModal(int $versionId): void
    {
        $this->resetVersionFormModal();

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        $version = $this->documentService->findVersionForEdit($actor, $versionId);
        Gate::forUser($actor)->authorize('update', $version->document);

        $this->editingVersionId = $version->id;
        $this->editingVersionDocumentId = (int) $version->document_id;
        $this->versionNumber = (int) $version->version_number;
        $this->storedPath = (string) $version->stored_path;
        $this->googleDriveRevisionId = (string) ($version->google_drive_revision_id ?? '');
        $this->changeSummary = (string) ($version->change_summary ?? '');
        $this->fileSizeBytes = $version->file_size_bytes !== null ? (string) $version->file_size_bytes : null;
        $this->showVersionFormModal = true;
    }

    public function closeVersionFormModal(): void
    {
        $this->showVersionFormModal = false;
        $this->resetVersionFormModal();
    }

    public function resetVersionFormModal(): void
    {
        $this->reset(['editingVersionId', 'editingVersionDocumentId', 'storedPath', 'googleDriveRevisionId', 'changeSummary', 'fileSizeBytes']);

        $this->versionNumber = 1;
        $this->resetValidation();
    }

    public function saveVersion(): void
    {
        $this->validate($this->versionRules(), $this->versionMessages());

        if ($this->editingVersionId === null) {
            return;
        }

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        $payload = [
            'version_number' => $this->versionNumber,
            'stored_path' => $this->storedPath,
            'google_drive_revision_id' => $this->googleDriveRevisionId,
            'change_summary' => $this->changeSummary,
            'file_size_bytes' => $this->fileSizeBytes,
        ];

        try {
            $version = $this->documentService->findVersionForEdit($actor, $this->editingVersionId);
            $this->documentService->updateVersion($actor, $version, $payload);

            $this->closeVersionFormModal();
            unset($this->projects); // Refresh data

            $message = 'Cap nhat phien ban thanh cong!';
            session()->flash('success', $message);
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        } catch (\Exception $e) {
            $message = 'Khong the cap nhat phien ban: ' . $e->getMessage();
            session()->flash('error', $message);
            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    public function confirmDeleteDocument(int $documentId): void
    {
        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        $document = $this->documentService->findDocumentForDelete($actor, $documentId);

        $this->pendingDeleteDocumentId = $document->id;
        $this->pendingDeleteDocumentName = $document->name;
        $this->showDeleteDocumentModal = true;
    }

    public function closeDeleteDocumentModal(): void
    {
        $this->showDeleteDocumentModal = false;
        $this->pendingDeleteDocumentId = null;
        $this->pendingDeleteDocumentName = '';
    }

    public function deleteDocument(): void
    {
        if ($this->pendingDeleteDocumentId === null) {
            return;
        }

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        try {
            $document = $this->documentService->findDocumentForEdit($actor, $this->pendingDeleteDocumentId);
            $this->documentService->deleteDocument($actor, $document);

            $this->closeDeleteDocumentModal();
            unset($this->projects); // Refresh data

            $message = 'Xoa tai lieu thanh cong!';
            session()->flash('success', $message);
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (\Exception $e) {
            $message = 'Khong the xoa tai lieu: ' . $e->getMessage();
            session()->flash('error', $message);
            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    public function confirmDeleteVersion(int $versionId): void
    {
        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        $version = $this->documentService->findVersionForDelete($actor, $versionId);

        $this->pendingDeleteVersionId = $version->id;
        $this->pendingDeleteVersionName = $version->document->name . ' - v' . $version->version_number;
        $this->showDeleteVersionModal = true;
    }

    public function closeDeleteVersionModal(): void
    {
        $this->showDeleteVersionModal = false;
        $this->pendingDeleteVersionId = null;
        $this->pendingDeleteVersionName = '';
    }

    public function download(int $documentId)
    {
        $actor = auth()->user();
        if ($actor === null) {
            return null;
        }

        $document = Document::query()
            ->with([
                'versions' => function ($query) {
                    $query->orderByDesc('version_number');
                },
            ])
            ->findOrFail($documentId);

        Gate::forUser($actor)->authorize('view', $document);

        $latestVersion = $document->versions->first();

        if (!$latestVersion) {
            $this->dispatch('toast', message: 'Tai lieu chua co phien ban nao', type: 'error');
            return null;
        }

        $media = $latestVersion->getFirstMedia('version_file');

        if (!$media) {
            $this->dispatch('toast', message: 'Khong tim thay tep tin', type: 'error');
            return null;
        }

        return response()->download($media->getPath(), $media->file_name);
    }

    public function deleteVersion(): void
    {
        if ($this->pendingDeleteVersionId === null) {
            return;
        }

        $actor = auth()->user();
        if ($actor === null) {
            return;
        }

        try {
            $version = $this->documentService->findVersionForEdit($actor, $this->pendingDeleteVersionId);
            $this->documentService->deleteVersion($actor, $version);

            $this->closeDeleteVersionModal();
            unset($this->projects); // Refresh data

            $message = 'Xoa phien ban thanh cong!';
            session()->flash('success', $message);
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (\Exception $e) {
            $message = 'Khong the xoa phien ban: ' . $e->getMessage();
            session()->flash('error', $message);
            $this->dispatch('toast', message: $message, type: 'error');
        }
    }

    public function formatFileSize(?int $bytes): string
    {
        if ($bytes === null) {
            return '--';
        }

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2) . ' MB';
        }

        return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }

    public function versionFileUrl(DocumentVersion $version): ?string
    {
        $media = $version->getFirstMedia('version_file');
        if ($media !== null) {
            return $media->getFullUrl();
        }

        $storedPath = trim((string) $version->stored_path);
        if ($storedPath === '') {
            return null;
        }

        $disk = config('media-library.disk_name');
        $diskInstance = Storage::disk($disk);

        if (!$diskInstance->exists($storedPath)) {
            return null;
        }

        try {
            if (method_exists($diskInstance, 'url')) {
                return $diskInstance->url($storedPath);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public function versionFileName(DocumentVersion $version): string
    {
        $media = $version->getFirstMedia('version_file');
        if ($media !== null && trim((string) $media->file_name) !== '') {
            return (string) $media->file_name;
        }

        $storedPath = trim((string) $version->stored_path);
        if ($storedPath !== '') {
            return basename($storedPath);
        }

        return 'tep-dinh-kem';
    }

    public function versionFileMimeType(DocumentVersion $version): ?string
    {
        $media = $version->getFirstMedia('version_file');
        if ($media !== null && trim((string) $media->mime_type) !== '') {
            return (string) $media->mime_type;
        }

        return null;
    }

    public function versionFileIcon(DocumentVersion $version): string
    {
        $fileName = strtolower($this->versionFileName($version));
        $mimeType = strtolower((string) $this->versionFileMimeType($version));
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($mimeType !== '' && str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if ($extension === 'pdf') {
            return 'picture_as_pdf';
        }

        if (in_array($extension, ['doc', 'docx', 'txt', 'rtf'], true)) {
            return 'description';
        }

        if (in_array($extension, ['xls', 'xlsx', 'csv'], true)) {
            return 'table';
        }

        if (in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz'], true)) {
            return 'folder_zip';
        }

        return 'draft';
    }

    #[Computed]
    public function projects()
    {
        $actor = auth()->user();
        $query = Project::query()->with([
            'phases' => function ($q) {
                $q->orderBy('order_index');
            },
            'phases.tasks' => function ($q) {
                $q->orderBy('created_at');
            },
            'phases.tasks.documents' => function ($q) use ($actor) {
                $q->accessibleBy($actor)->orderBy('created_at', 'desc')->with('versions');
            },
        ]);

        // Scope Visibility Logic
        if ($actor && !$actor->hasAnyRole(['super_admin', 'ceo'])) {
            $query->where(function (Builder $builder) use ($actor) {
                $builder
                    ->where('created_by', $actor->id)
                    ->orWhereHas('leaders', function (Builder $q) use ($actor) {
                        $q->where('users.id', $actor->id);
                    })
                    ->orWhereHas('tasks', function (Builder $q) use ($actor) {
                        $q->where('pic_id', $actor->id)
                            ->orWhere('created_by', $actor->id)
                            ->orWhereHas('coPics', function (Builder $coPicQuery) use ($actor) {
                                $coPicQuery->where('users.id', $actor->id);
                            });
                    });
            });
        }

        // Apply filters
        if ($this->filterSearch) {
            $query->where('name', 'like', '%' . $this->filterSearch . '%');
        }

        return $query->orderBy('created_at', 'desc')->paginate(10);
    }
};
?>

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <x-ui.heading title="Tài liệu dự án" description="Quản lý tài liệu theo cấu trúc Dự án -> Giai đoạn -> Nhiệm vụ."
            class="mb-0" />
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.filter-search model="filterSearch" placeholder="Tìm kiếm dự án..." width="w-80" />
        </div>
    </div>

    <x-ui.table :paginator="$this->projects" paginator-label="dự án">
        <x-ui.table.head>
            <x-ui.table.column width="min-w-100" class="px-6">Tên tài liệu / Thư mục</x-ui.table.column>
            <x-ui.table.column width="min-w-40" class="px-6">Loại</x-ui.table.column>
            <x-ui.table.column width="min-w-40" align="center" class="px-6">Phiên bản</x-ui.table.column>
            <x-ui.table.column width="min-w-50" class="px-6">Quyền hạn</x-ui.table.column>
            <x-ui.table.column width="min-w-50" align="right" class="px-6">Trạng
                thái</x-ui.table.column>
        </x-ui.table.head>
        <x-ui.table.body>
            @forelse ($this->projects as $project)
                @php
                    $isProjectExpanded = $this->isNodeExpanded('project-' . $project->id);
                @endphp
                <!-- Project Row -->
                <x-ui.table.row
                    class="{{ $isProjectExpanded ? 'bg-slate-50/30 dark:bg-slate-800/20' : '' }} group cursor-pointer"
                    wire:click="toggleNode('project-{{ $project->id }}')">
                    <x-ui.table.cell class="px-6 py-4">
                        <div class="flex items-center gap-4">
                            <div class="relative shrink-0">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-600 transition-colors group-hover:bg-amber-200 dark:bg-amber-900/30 dark:text-amber-500 dark:group-hover:bg-amber-900/50">
                                    <span
                                        class="material-symbols-outlined">{{ $isProjectExpanded ? 'folder_open' : 'folder' }}</span>
                                </div>
                                @if ($isProjectExpanded)
                                    <div
                                        class="absolute -bottom-6 left-1/2 h-6 w-px -translate-x-1/2 bg-slate-200 dark:bg-slate-700">
                                    </div>
                                @endif
                            </div>
                            <div>
                                <h3
                                    class="group-hover:text-primary text-sm font-bold text-slate-900 transition-colors dark:text-white">
                                    {{ $project->name }}</h3>
                                <p class="mt-0.5 text-[10px] text-slate-500">Dự án</p>
                            </div>
                        </div>
                    </x-ui.table.cell>
                    <x-ui.table.cell class="px-6 py-4">
                        <span
                            class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-800 dark:bg-slate-800 dark:text-slate-200">
                            Project
                        </span>
                    </x-ui.table.cell>
                    <x-ui.table.cell class="px-6 py-4 text-slate-400" align="center">-</x-ui.table.cell>
                    <x-ui.table.cell class="px-6 py-4 text-slate-400">-</x-ui.table.cell>
                    <x-ui.table.cell class="px-6 py-4" align="right">
                        <span
                            class="{{ match ($project->status->value) {'completed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400','cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400','paused' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',default => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'} }} inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                            {{ $project->status->label() }}
                        </span>
                    </x-ui.table.cell>
                </x-ui.table.row>

                @if ($isProjectExpanded)
                    @foreach ($project->phases as $phase)
                        @php
                            $isPhaseExpanded = $this->isNodeExpanded('phase-' . $phase->id);
                            $isLastPhase = $loop->last;
                        @endphp
                        <!-- Phase Row -->
                        <x-ui.table.row class="group cursor-pointer"
                            wire:click.stop="toggleNode('phase-{{ $phase->id }}')">
                            <x-ui.table.cell class="relative px-6 py-3">
                                <!-- Tree Connector -->
                                <div class="absolute left-[2.72rem] top-0 h-full w-px bg-slate-200 dark:bg-slate-700">
                                </div>
                                <div class="absolute left-[2.72rem] top-1/2 h-px w-8 bg-slate-200 dark:bg-slate-700">
                                </div>

                                <div class="relative z-10 flex items-center gap-4 pl-12">
                                    <div class="relative shrink-0">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-500 transition-colors group-hover:bg-blue-100 dark:bg-blue-900/20 dark:text-blue-400 dark:group-hover:bg-blue-900/40">
                                            <span
                                                class="material-symbols-outlined text-lg">{{ $isPhaseExpanded ? 'folder_open' : 'folder' }}</span>
                                        </div>
                                        @if ($isPhaseExpanded)
                                            <div
                                                class="absolute -bottom-5 left-1/2 h-5 w-px -translate-x-1/2 bg-slate-200 dark:bg-slate-700">
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <h4
                                            class="group-hover:text-primary text-sm font-semibold text-slate-700 transition-colors dark:text-slate-300">
                                            {{ $phase->name }}</h4>
                                        <p class="mt-0.5 text-[10px] text-slate-500">Giai đoạn</p>
                                    </div>
                                </div>
                            </x-ui.table.cell>
                            <x-ui.table.cell class="px-6 py-3">
                                <span
                                    class="inline-flex items-center rounded-full bg-slate-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                                    Phase
                                </span>
                            </x-ui.table.cell>
                            <x-ui.table.cell class="px-6 py-3 text-slate-400" align="center">-</x-ui.table.cell>
                            <x-ui.table.cell class="px-6 py-3 text-slate-400">-</x-ui.table.cell>
                            <x-ui.table.cell class="px-6 py-3" align="right">
                                <div class="flex items-center justify-end gap-2">
                                    <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                        <div class="h-full bg-blue-500" style="width: {{ $phase->progress }}%"></div>
                                    </div>
                                    <span
                                        class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ $phase->progress }}%</span>
                                </div>
                            </x-ui.table.cell>
                        </x-ui.table.row>

                        @if ($isPhaseExpanded)
                            @foreach ($phase->tasks as $task)
                                @php
                                    $isTaskExpanded = $this->isNodeExpanded('task-' . $task->id);
                                    $hasDocuments = $task->documents->isNotEmpty();
                                    $isLastTask = $loop->last;
                                @endphp
                                <!-- Task Row -->
                                <x-ui.table.row class="group cursor-pointer"
                                    wire:click.stop="toggleNode('task-{{ $task->id }}')">
                                    <x-ui.table.cell class="relative px-6 py-3">
                                        <!-- Tree Connector -->
                                        <div
                                            class="absolute left-[2.78rem] top-0 h-full w-px bg-slate-200 dark:bg-slate-700">
                                        </div>
                                        <div
                                            class="absolute left-[5.48rem] top-0 h-full w-px bg-slate-200 dark:bg-slate-700">
                                        </div>
                                        <div
                                            class="absolute left-[5.48rem] top-1/2 h-px w-8 bg-slate-200 dark:bg-slate-700">
                                        </div>

                                        <div class="relative z-10 flex items-center gap-4 pl-24">
                                            <div class="relative shrink-0">
                                                <div
                                                    class="{{ $hasDocuments ? 'bg-indigo-50 text-indigo-500 dark:bg-indigo-900/20 dark:text-indigo-400' : 'bg-slate-50 text-slate-400 dark:bg-slate-800 dark:text-slate-500' }} flex h-8 w-8 items-center justify-center rounded-lg transition-colors group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/40">
                                                    <span
                                                        class="material-symbols-outlined text-lg">{{ $isTaskExpanded ? 'folder_open' : 'folder' }}</span>
                                                </div>
                                                @if ($isTaskExpanded && $hasDocuments)
                                                    <div
                                                        class="absolute -bottom-5 left-1/2 h-5 w-px -translate-x-1/2 bg-slate-200 dark:bg-slate-700">
                                                    </div>
                                                @endif
                                            </div>
                                            <div>
                                                <h5 class="group-hover:text-primary line-clamp-1 text-sm font-medium text-slate-700 transition-colors dark:text-slate-300"
                                                    title="{{ $task->name }}">{{ $task->name }}</h5>
                                                <p class="mt-0.5 text-[10px] text-slate-500">Nhiệm vụ</p>
                                            </div>
                                        </div>
                                    </x-ui.table.cell>
                                    <x-ui.table.cell class="px-6 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full bg-slate-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                                            Task
                                        </span>
                                    </x-ui.table.cell>
                                    <x-ui.table.cell class="px-6 py-3 text-slate-400" align="center">-</x-ui.table.cell>
                                    <x-ui.table.cell class="px-6 py-3 text-slate-400">-</x-ui.table.cell>
                                    <x-ui.table.cell class="px-6 py-3" align="right">
                                        @if ($task->pic)
                                            <div class="flex items-center justify-end gap-2">
                                                <span
                                                    class="text-xs text-slate-600 dark:text-slate-400">{{ $task->pic->name }}</span>
                                                @if ($task->pic->avatar)
                                                    <img class="h-6 w-6 rounded-full border border-slate-200 dark:border-slate-700"
                                                        src="{{ $task->pic->avatar }}" alt="{{ $task->pic->name }}">
                                                @else
                                                    <div
                                                        class="bg-primary/10 text-primary border-primary/20 flex h-6 w-6 items-center justify-center rounded-full border text-[10px] font-bold">
                                                        {{ substr($task->pic->name, 0, 1) }}
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-xs text-slate-400">--</span>
                                        @endif
                                    </x-ui.table.cell>
                                </x-ui.table.row>

                                @if ($isTaskExpanded)
                                    @foreach ($task->documents as $document)
                                        @php
                                            $latestVersion = $document->versions->first();
                                            $fileIcon = $latestVersion
                                                ? $this->versionFileIcon($latestVersion)
                                                : 'draft';
                                            $permissionValue =
                                                $document->permission instanceof \BackedEnum
                                                    ? $document->permission->value
                                                    : $document->permission;
                                            $permissionLabel = $permissionLabels[$permissionValue] ?? $permissionValue;
                                            $typeValue =
                                                $document->document_type instanceof \BackedEnum
                                                    ? $document->document_type->value
                                                    : $document->document_type;
                                            $typeLabel = $documentTypeLabels[$typeValue] ?? $typeValue;
                                            $typeColor = match ($typeValue) {
                                                'contract'
                                                    => 'text-emerald-700 bg-emerald-50 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20',
                                                'sop'
                                                    => 'text-blue-700 bg-blue-50 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/20',
                                                'form'
                                                    => 'text-purple-700 bg-purple-50 ring-purple-600/20 dark:bg-purple-500/10 dark:text-purple-400 dark:ring-purple-500/20',
                                                default
                                                    => 'text-slate-600 bg-slate-50 ring-slate-500/10 dark:bg-slate-400/10 dark:text-slate-400 dark:ring-slate-400/20',
                                            };
                                            $isLastDoc = $loop->last;
                                        @endphp
                                        <!-- Document Row -->
                                        <x-ui.table.row class="group">
                                            <x-ui.table.cell class="relative px-6 py-3">
                                                <!-- Tree Connector -->
                                                <div
                                                    class="absolute left-[2.78rem] top-0 h-full w-px bg-slate-200 dark:bg-slate-700">
                                                </div>
                                                <div
                                                    class="absolute left-[5.52rem] top-0 h-full w-px bg-slate-200 dark:bg-slate-700">
                                                </div>
                                                <div
                                                    class="{{ $isLastDoc ? 'h-1/2' : 'h-full' }} absolute left-[8.48rem] top-0 w-px bg-slate-200 dark:bg-slate-700">
                                                </div>
                                                <div
                                                    class="absolute left-[8.48rem] top-1/2 h-px w-8 bg-slate-200 dark:bg-slate-700">
                                                </div>

                                                <div class="relative z-10 flex items-center gap-4 pl-36">
                                                    <div
                                                        class="group-hover:border-primary/50 group-hover:text-primary flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition-colors dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                                                        <span
                                                            class="material-symbols-outlined text-lg">{{ $fileIcon }}</span>
                                                    </div>
                                                    <div>
                                                        @can('update', $document)
                                                            <button
                                                                class="hover:text-primary text-left text-sm font-medium text-slate-600 hover:underline dark:text-slate-300"
                                                                wire:click="openEditDocumentModal({{ $document->id }})">
                                                                {{ $document->name }}
                                                            </button>
                                                        @else
                                                            <span
                                                                class="text-left text-sm font-medium text-slate-600 dark:text-slate-300">
                                                                {{ $document->name }}
                                                            </span>
                                                        @endcan
                                                        <p class="mt-0.5 text-[10px] text-slate-400">Cập nhật
                                                            {{ $document->updated_at->diffForHumans() }}</p>
                                                    </div>
                                                </div>
                                            </x-ui.table.cell>
                                            <x-ui.table.cell class="px-6 py-3">
                                                <span
                                                    class="{{ $typeColor }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset">
                                                    {{ $typeLabel }}
                                                </span>
                                            </x-ui.table.cell>
                                            <x-ui.table.cell class="px-6 py-3" align="center">
                                                <span
                                                    class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-400/20">
                                                    v{{ $document->current_version }}
                                                </span>
                                            </x-ui.table.cell>
                                            <x-ui.table.cell class="px-6 py-3">
                                                <div
                                                    class="flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-400">
                                                    <span
                                                        class="material-symbols-outlined {{ $permissionValue === 'view' ? 'text-slate-400' : ($permissionValue === 'edit' ? 'text-amber-500' : 'text-emerald-500') }} text-[16px]">
                                                        {{ $permissionValue === 'view' ? 'visibility' : ($permissionValue === 'edit' ? 'edit' : 'admin_panel_settings') }}
                                                    </span>
                                                    {{ $permissionLabel }}
                                                </div>
                                            </x-ui.table.cell>
                                            <x-ui.table.cell class="px-6 py-3" align="right">
                                                <button wire:click="download({{ $document->id }})"
                                                    class="rounded p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                                                    title="Tải xuống">
                                                    <span class="material-symbols-outlined text-lg">download</span>
                                                </button>
                                            </x-ui.table.cell>
                                        </x-ui.table.row>
                                    @endforeach
                                    @if ($task->documents->isEmpty())
                                        <x-ui.table.row>
                                            <x-ui.table.cell colspan="5" class="relative px-6 py-3">
                                                <div
                                                    class="absolute left-11 top-0 h-full w-px bg-slate-200 dark:bg-slate-700">
                                                </div>
                                                <div
                                                    class="left-22 absolute top-0 h-full w-px bg-slate-200 dark:bg-slate-700">
                                                </div>
                                                <div
                                                    class="left-33 absolute top-0 h-1/2 w-px bg-slate-200 dark:bg-slate-700">
                                                </div>
                                                <div
                                                    class="left-33 absolute top-1/2 h-px w-8 bg-slate-200 dark:bg-slate-700">
                                                </div>

                                                <div
                                                    class="flex items-center gap-2 py-1 pl-36 text-xs italic text-slate-400">
                                                    <span class="material-symbols-outlined text-sm">info</span>
                                                    Chưa có tài liệu nào.
                                                </div>
                                            </x-ui.table.cell>
                                        </x-ui.table.row>
                                    @endif
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                @endif
            @empty
                <x-ui.table.empty colspan="5" icon="folder_off"
                    message="Không tìm thấy dự án hoặc tài liệu nào phù hợp." />
            @endforelse
        </x-ui.table.body>
    </x-ui.table>



    <!-- Document Modal -->
    <x-ui.slide-panel wire:model="showDocumentFormModal" maxWidth="3xl">
        <x-slot name="header">
            <x-ui.form.heading icon="edit" title="Cập nhật tài liệu"
                description="Chỉnh sửa thông tin metadata của tài liệu." />
        </x-slot>

        <form id="document-form" wire:submit="saveDocument" class="space-y-8">
            <!-- General Info Section -->
            <div class="space-y-4">
                <div class="flex items-center gap-2 border-b border-slate-100 pb-2 dark:border-slate-800">
                    <span class="material-symbols-outlined text-primary">info</span>
                    <h4 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">Thông tin
                        chung</h4>
                </div>

                <div class="grid grid-cols-1 gap-5">
                    <x-ui.input label="Tên tài liệu" name="documentName" wire:model="documentName" required
                        placeholder="Nhập tên tài liệu..." />

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                Loại tài liệu <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select wire:model="documentType"
                                    class="focus:border-primary focus:ring-primary/20 w-full appearance-none rounded-xl border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-900 shadow-sm transition-colors placeholder:text-slate-400 focus:ring-2 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                    @foreach ($documentTypeLabels as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div
                                    class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500">
                                    <span class="material-symbols-outlined text-base">expand_more</span>
                                </div>
                            </div>
                            <x-ui.field-error field="documentType" />
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                Quyền truy cập <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select wire:model="documentPermission"
                                    class="focus:border-primary focus:ring-primary/20 w-full appearance-none rounded-xl border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-900 shadow-sm transition-colors placeholder:text-slate-400 focus:ring-2 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                    @foreach ($permissionLabels as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div
                                    class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500">
                                    <span class="material-symbols-outlined text-base">expand_more</span>
                                </div>
                            </div>
                            <x-ui.field-error field="documentPermission" />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Mô
                            tả</label>
                        <textarea rows="3" wire:model="documentDescription"
                            class="focus:border-primary focus:ring-primary/20 w-full rounded-xl border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-900 shadow-sm transition-colors placeholder:text-slate-400 focus:ring-2 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            placeholder="Mô tả chi tiết về tài liệu..."></textarea>
                        <x-ui.field-error field="documentDescription" />
                    </div>
                </div>
            </div>

            <!-- Integration Section -->
            <div class="space-y-4">
                <div class="flex items-center gap-2 border-b border-slate-100 pb-2 dark:border-slate-800">
                    <span class="material-symbols-outlined text-blue-500">cloud_sync</span>
                    <h4 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">Liên kết &
                        Phiên bản</h4>
                </div>

                <div class="grid grid-cols-1 gap-5">
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <x-ui.input label="Phiên bản hiện tại" name="currentVersion" type="number" min="1"
                            wire:model="currentVersion" required placeholder="1" />

                        <x-ui.input label="Google Drive ID" name="googleDriveId" wire:model="googleDriveId"
                            placeholder="File ID trên Drive" />
                    </div>

                    <x-ui.input label="Google Drive URL" name="googleDriveUrl" wire:model="googleDriveUrl"
                        placeholder="https://drive.google.com/..." />
                </div>
            </div>
        </form>

        <x-slot name="footer">
            <x-ui.button variant="secondary" wire:click="closeDocumentFormModal">
                Hủy
            </x-ui.button>
            <x-ui.button type="submit" form="document-form">
                Lưu thay đổi
            </x-ui.button>
        </x-slot>
    </x-ui.slide-panel>
</div>
