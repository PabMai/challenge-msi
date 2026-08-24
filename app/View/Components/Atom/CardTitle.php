<?php

namespace App\View\Components\Atom;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

class CardTitle extends Component
{
    public const TAGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $as = 'h5',
    ) {
        if (! in_array($this->as, self::TAGS, true)) {
            throw new InvalidArgumentException(
                sprintf('[%s] no es un tag válido. Tags: %s', $this->as, implode(', ', self::TAGS))
            );
        }
    }

    public function classes(): array
    {
        return ['card-title'];
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.atom.card-title');
    }
}
