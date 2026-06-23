<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Compliance Form — Rightsize CLIA Compliance</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    @if (($component ?? 'form-wizard') === 'log-form')
        <livewire:log-form :lab="$lab" :code="$code" />
    @else
        <livewire:form-wizard :lab="$lab" :code="$code" />
    @endif
</body>
</html>
