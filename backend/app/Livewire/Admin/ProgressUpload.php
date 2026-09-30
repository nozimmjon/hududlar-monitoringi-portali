<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Reactive;
use Livewire\Component;

class ProgressUpload extends Component
{
    #[Reactive]
    public string $period = '';

    public function render()
    {
        return view('livewire.admin.progress-upload');
    }
}
