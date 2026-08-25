<x-organism.form method="POST" action="{{ route('reserve.store') }}" class="mb-4" data-reservation-form>
    @csrf

    <x-organism.card class="mb-4">
        <x-slot name="header">
            <h2 class="h5 mb-0">Formulario de reserva</h2>
        </x-slot>

        <div data-reservation-result class="mb-3 d-none"></div>
        <x-molecule.card-body>
                <x-atom.alert variant="secondary" class="mb-3">
                    <p class="mb-0">Las secciones dependen de la ubicación elegida.</p>
                </x-atom.alert>

            <x-molecule.field>
                <x-atom.label for="reservation_date" class="col-sm-3">Fecha de reserva</x-atom.label>
                <div class="col-sm-9">
                    <x-atom.input type="date" id="reservation_date" name="reservation_date"
                        :value="old('reservation_date')" />
                    <x-atom.invalid-feedback data-reservation-error="reservation_date"></x-atom.invalid-feedback>
                </div>
            </x-molecule.field>

            <x-molecule.field>
                <x-atom.label for="reservation_time" class="col-sm-3">Hora de reserva</x-atom.label>
                <div class="col-sm-9">
                    <x-atom.input type="time" id="reservation_time" name="reservation_time"
                        :value="old('reservation_time')" />
                    <x-atom.invalid-feedback data-reservation-error="reservation_time"></x-atom.invalid-feedback>
                </div>
            </x-molecule.field>

            <x-molecule.field>
                <x-atom.label for="reservation_people_count" class="col-sm-3">Cantidad de personas</x-atom.label>
                <div class="col-sm-9">
                    <x-atom.input type="number" id="reservation_people_count" name="reservation_people_count" min="1"
                        :value="old('reservation_people_count')" />
                    <x-atom.invalid-feedback data-reservation-error="reservation_people_count">
                    </x-atom.invalid-feedback>
                </div>
            </x-molecule.field>

                <x-molecule.field>
                    <x-atom.label for="reservation_location" class="col-sm-3">Ubicación</x-atom.label>
                    <div class="col-sm-9">
                        <x-atom.select id="reservation_location" name="reservation_location"
                            data-reservation-location-select>
                            <option value="" @selected(old('reservation_location') === null)>Seleccionar ubicación</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}"
                                    @selected((string) old('reservation_location') === (string) $location->id)>
                                    {{ $location->name }}
                                </option>
                            @endforeach
                        </x-atom.select>
                        <x-atom.invalid-feedback data-reservation-error="reservation_location"></x-atom.invalid-feedback>
                    </div>
                </x-molecule.field>

                <x-molecule.field>
                    <x-atom.label for="reservation_section" class="col-sm-3">Sección</x-atom.label>
                    <div class="col-sm-9">
                        <x-atom.select id="reservation_section" name="reservation_section" disabled
                            data-reservation-section-select>
                            <option value="">Seleccionar sección</option>
                            @foreach ($locations as $location)
                                @foreach ($location->sections as $section)
                                    <option value="{{ $section->id }}" data-location-id="{{ $location->id }}"
                                        @selected((string) old('reservation_section') === (string) $section->id)>
                                        {{ $section->name }}
                                    </option>
                                @endforeach
                            @endforeach
                        </x-atom.select>
                        <x-atom.invalid-feedback data-reservation-error="reservation_section"></x-atom.invalid-feedback>
                    </div>
                </x-molecule.field>
        </x-molecule.card-body>
        <x-molecule.card-footer>
            <div class="d-flex justify-content-end">
                <x-atom.button type="submit" variant="primary" data-reservation-submit>Reservar</x-atom.button>
            </div>
        </x-molecule.card-footer>
    </x-organism.card>
</x-organism.form>

@include('partials.reservation-modal')