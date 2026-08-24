<?php

namespace App\View\Components\Atom;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

class Alert extends Component
{
    public const VARIANTS = [
        'primary',
        'secondary',
        'success',
        'danger',
        'warning',
        'info',
        'light',
        'dark',
    ];

    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $variant = 'primary',
    ) {
        if (! in_array($this->variant, self::VARIANTS, true)) {
            throw new InvalidArgumentException(
                sprintf('[%s] no es una variante válida. Variantes: %s', $this->variant, implode(', ', self::VARIANTS))
            );
        }
    }

    public function classes(): array
    {
        return ['alert', 'alert-'.$this->variant];
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.atom.alert');
    }
}
