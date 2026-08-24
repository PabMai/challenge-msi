<?php

namespace App\View\Components\Atom;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Input extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $type = 'text',
        public ?string $id = null,
        public ?string $name = null,
        public ?string $value = null,
        public bool $plain = false,
    ) {
    }

    public function classes(): array
    {
        return $this->plain
            ? ['form-control', 'form-control-plaintext']
            : ['form-control'];
    }

    /**
     * Atributos del elemento; los nulos se omiten.
     */
    public function attrs(): array
    {
        return array_filter([
            'type' => $this->type,
            'id' => $this->id,
            'name' => $this->name,
            'value' => $this->value,
            'readonly' => $this->plain ?: null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.atom.input');
    }
}
