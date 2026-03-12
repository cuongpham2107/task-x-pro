<?php

use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Filament\Schemas\Schema;
use Livewire\Component;

new class extends Component implements HasSchemas
{
     use InteractsWithSchemas;
    
    public ?array $data = [];
    
    public function mount(): void
    {
        $this->form->fill();
    }
    
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                MarkdownEditor::make('content'),
                // ...
            ])
            ->statePath('data');
    }
    
    public function create(): void
    {
        dd($this->form->getState());
    }
};
?>

<div>
    <livewire:ui.heading title="Create post" description="Create a new post." />
    
    <form wire:submit="create">
        {{ $this->form }}

        <x-filament::button type="submit">
            New user
        </x-filament::button>
    </form>
    
    <x-filament-actions::modals />
</div>