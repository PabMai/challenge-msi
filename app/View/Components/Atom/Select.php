<?php

namespace App\View\Components\Atom;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Select extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public bool $multiple = false,
        public ?int $size = null,
    ) {
    }

    public function classes(): array
    {
        return ['form-select'];
    }

    /**
     * Atributos del elemento; los nulos se omiten.
     */
    public function attrs(): array
    {
        return array_filter([
            'id' => $this->id,
            'name' => $this->name,
            'multiple' => $this->multiple ?: null,
            'size' => $this->size,
        ], fn ($v) => $v !== null);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.atom.select');
    }
}
