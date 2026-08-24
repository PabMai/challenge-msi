<x-organism.card class="mb-4">
    <x-slot name="header">
        <h2 class="h5 mb-0">Formulario de reserva</h2>
    </x-slot>

    <x-molecule.card-body>
        <div>
            <x-organism.form method="POST" action="{{ route('reserve.store') }}" class="mb-4">
                @csrf

                <x-molecule.field>
                    <x-atom.label for="reservation_date" class="col-sm-3">Fecha de reserva</x-atom.label>
                    <div class="col-sm-9">
                        <x-atom.input type="date" id="reservation_date" name="reservation_date"
                            :value="old('reservation_date')" />
                        @error('reservation_date')
                            <x-atom.invalid-feedback>{{ $message }}</x-atom.invalid-feedback>
                        @enderror
                    </div>
                </x-molecule.field>

                <x-molecule.field>
                    <x-atom.label for="reservation_time" class="col-sm-3">Hora de reserva</x-atom.label>
                    <div class="col-sm-9">
                        <x-atom.input type="time" id="reservation_time" name="reservation_time"
                            :value="old('reservation_time')" />
                        @error('reservation_time')
                            <x-atom.invalid-feedback>{{ $message }}</x-atom.invalid-feedback>
                        @enderror
                    </div>
                </x-molecule.field>

                <x-molecule.field>
                    <x-atom.label for="reservation_people_count" class="col-sm-3">Cantidad de personas</x-atom.label>
                    <div class="col-sm-9">
                        <x-atom.input type="number" id="reservation_people_count" name="reservation_people_count"
                            min="1" :value="old('reservation_people_count')" />
                        @error('reservation_people_count')
                            <x-atom.invalid-feedback>{{ $message }}</x-atom.invalid-feedback>
                        @enderror
                    </div>
                </x-molecule.field>

                <x-molecule.field>
                    <x-atom.label for="reservation_location" class="col-sm-3">Ubicación</x-atom.label>
                    <div class="col-sm-9">
                        <x-atom.select id="reservation_location" name="reservation_location">
                            <option value="" @selected(old('reservation_location') === null)>Seleccionar ubicación</option>
                            <option value="salon" @selected(old('reservation_location') === 'salon')>Salón</option>
                            <option value="terraza" @selected(old('reservation_location') === 'terraza')>Terraza</option>
                        </x-atom.select>
                        @error('reservation_location')
                            <x-atom.invalid-feedback>{{ $message }}</x-atom.invalid-feedback>
                        @enderror
                    </div>
                </x-molecule.field>
            </x-organism.form>
        </div>
    </x-molecule.card-body>
    <x-molecule.card-footer>
        <div class="d-flex justify-content-end">
            <x-atom.button type="button" variant="primary">Reservar</x-atom.button>
        </div>
    </x-molecule.card-footer>
</x-organism.card>
