<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $lab->name }} — CLIAComplai</title>
    <link rel="icon" type="image/png" href="{{ asset('img/favicon-64.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <livewire:compliance-dashboard :lab="$lab" />
</body>
</html>
