@extends('layouts.app')

@section('title', 'Agenda')

@section('content')
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">Agenda de reservas</h2>
                        <span class="badge text-bg-secondary">{{ $total }}</span>
                    </div>
                    <div class="card-body">
                        @if (count($reservations) === 0)
                            <x-atom.alert variant="info" class="mb-0">
                                No hay reservas registradas.
                            </x-atom.alert>
                        @else
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Fecha</th>
                                            <th scope="col">Hora</th>
                                            <th scope="col">Personas</th>
                                            <th scope="col">Ubicación</th>
                                            <th scope="col">Sección</th>
                                            <th scope="col">Mesas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($reservations as $reservation)
                                            <tr>
                                                <td>{{ $reservation['business_date'] }}</td>
                                                <td>{{ $reservation['start_time'] }}–{{ $reservation['end_time'] }}</td>
                                                <td>{{ $reservation['people_count'] }}</td>
                                                <td>{{ $reservation['location_name'] }}</td>
                                                <td>{{ $reservation['section_name'] }}</td>
                                                <td>{{ $reservation['table_codes'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
