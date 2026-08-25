@extends('layouts.base')

@section('header')
    <x-organism.navbar brand="Challenge MSI">
        <x-slot:links>
            <li class="nav-item">
                <a
                    href="{{ route('home') }}"
                    @class(['btn', 'btn-outline-light', 'active' => request()->routeIs('home')])
                >Crear Reserva</a>
            </li>
            <li class="nav-item">
                <a
                    href="{{ route('agenda') }}"
                    @class(['btn', 'btn-outline-light', 'active' => request()->routeIs('agenda')])
                >Agenda</a>
            </li>
        </x-slot:links>
    </x-organism.navbar>
@endsection
