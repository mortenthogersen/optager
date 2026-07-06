<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Mødeoptager — Optagelse modtaget</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-white min-h-dvh flex items-center justify-center p-4">

<div class="w-full max-w-md mx-auto text-center space-y-6">
    <div class="w-20 h-20 bg-green-600 rounded-full flex items-center justify-center mx-auto">
        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
    </div>

    <h1 class="text-2xl font-bold">Optagelse modtaget</h1>

    @if($recording->title)
        <p class="text-gray-400">"{{ $recording->title }}"</p>
    @endif

    <p class="text-gray-500 text-sm">
        Transskriberes nu — det tager typisk 10-30 sekunder.
    </p>

    <div class="flex gap-3 justify-center pt-4">
        <a href="{{ route('record.create') }}" class="px-6 py-3 bg-amber-600 hover:bg-amber-500 rounded-xl font-semibold transition-colors">
            Ny optagelse
        </a>
    </div>

    <p class="text-gray-600 text-xs pt-8">
        Optagelse ID: {{ $recording->uuid }}
    </p>
</div>

</body>
</html>
