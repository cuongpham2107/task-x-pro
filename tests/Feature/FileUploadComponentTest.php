<?php

use Livewire\Component;
use Livewire\Livewire;

it('renders file upload component when new files contain null entries', function () {
    Livewire::test(FileUploadTestComponent::class)
        ->assertSee('Đính kèm tệp');
});

class FileUploadTestComponent extends Component
{
    public array $files = [null];

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('tests.livewire.file-upload-host');
    }
}
