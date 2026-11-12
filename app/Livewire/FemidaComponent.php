<?php

namespace App\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component as LivewireComponent;

abstract class FemidaComponent extends LivewireComponent
{
    use AuthorizesRequests;
}
