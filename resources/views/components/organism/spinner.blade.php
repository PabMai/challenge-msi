{{-- Overlay de carga centrado en pantalla; oculto por defecto: mostrarlo quitando `d-none` desde JS. --}}
<div {{ $attributes->merge(['class' => 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center d-none'])
    ->style('background-color: rgba(0, 0, 0, .35); z-index: 999;') }}>
    <x-organism.card class="shadow text-center w-25">
        <x-molecule.card-body>
            <div class="spinner-border m-5" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mb-2">Procesando...</p>
        </x-molecule.card-body>
    </x-organism.card>
</div>
