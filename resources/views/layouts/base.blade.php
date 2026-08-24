<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', config('app.name', 'Laravel'))</title>
        @vite('resources/css/app.css')
    </head>
    <body class="">
        @hasSection('header')
            @yield('header')
        @endif

        <main class="container py-4">
            @yield('content')
        </main>

        @vite('resources/js/app.js')
    </body>
</html>