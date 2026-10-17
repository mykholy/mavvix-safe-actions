<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Safe Actions E2E</title>
    @filamentStyles
    @vite('resources/css/app.css')
</head>
<body>
    <main style="max-width: 1100px; padding: 2rem; margin: 0 auto">
        @livewire(\App\Livewire\SafeActionsDemo::class)
    </main>
    @livewire('notifications')
    @filamentScripts
    @vite('resources/js/app.js')
</body>
</html>
