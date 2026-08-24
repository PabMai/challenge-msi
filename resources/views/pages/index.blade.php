@extends('layouts.app')

@section('title', 'Challenge MSI')

@section('content')
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                @include('partials.reservation-form')
            </div>
        </div>
    </div>
    <x-organism.spinner data-reservation-spinner />
@endsection