<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Mødeoptager — Ny optagelse</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes pulse-bar {
            0%, 100% { opacity: 0.4; transform: scaleY(0.3); }
            50% { opacity: 1; transform: scaleY(1); }
        }
        .pulse-bar {
            animation: pulse-bar 1s ease-in-out infinite;
            transform-origin: bottom;
        }
    </style>
</head>
<body class="bg-gray-950 text-white min-h-dvh flex items-center justify-center p-4">

<div class="w-full max-w-md mx-auto">
    <h1 class="text-3xl font-bold text-center mb-2">Mødeoptager</h1>
    <p class="text-gray-400 text-center mb-8 text-sm">Optag eller upload et møde</p>

    <!-- Recording state -->
    <div id="recording-ui" class="space-y-6">
        <!-- Record button -->
        <div id="record-area">
            <button
                id="record-btn"
                onclick="startRecording()"
                class="w-full aspect-square max-w-[200px] mx-auto rounded-full bg-red-600 hover:bg-red-500 active:scale-95 transition-all flex items-center justify-center shadow-lg shadow-red-600/30"
            >
                <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="6"/>
                </svg>
            </button>
            <p class="text-center text-gray-400 text-xs mt-2">Tryk for at optage</p>
        </div>

        <!-- Mic error -->
        <div id="mic-error" class="hidden bg-amber-900/30 border border-amber-600 rounded-2xl p-4 text-center">
            <p class="text-amber-400 font-medium">Mikrofon ikke tilgængelig</p>
            <p class="text-gray-400 text-sm mt-1">Brug fil-upload nedenfor i stedet</p>
        </div>

        <!-- Recording active state -->
        <div id="recording-active" class="hidden space-y-4">
            <button
                onclick="stopRecording()"
                class="w-full py-4 rounded-2xl bg-red-600/20 border-2 border-red-500 text-red-400 font-semibold text-lg active:scale-[0.98] transition-all"
            >
                Stop optagelse
            </button>

            <div class="text-center">
                <span id="timer" class="text-5xl font-mono font-bold tabular-nums text-red-400">00:00</span>
            </div>

            <!-- Audio visualizer -->
            <div id="visualizer" class="flex items-end justify-center gap-1 h-16">
                <div class="pulse-bar w-1.5 bg-red-400 rounded-full" style="animation-delay: 0s; height: 30%"></div>
                <div class="pulse-bar w-1.5 bg-red-400 rounded-full" style="animation-delay: 0.1s; height: 50%"></div>
                <div class="pulse-bar w-1.5 bg-red-400 rounded-full" style="animation-delay: 0.2s; height: 70%"></div>
                <div class="pulse-bar w-1.5 bg-red-400 rounded-full" style="animation-delay: 0.3s; height: 90%"></div>
                <div class="pulse-bar w-1.5 bg-red-400 rounded-full" style="animation-delay: 0.4s; height: 100%"></div>
                <div class="pulse-bar w-1.5 bg-red-400 rounded-full" style="animation-delay: 0.5s; height: 90%"></div>
                <div class="pulse-bar w-1.5 bg-red-400 rounded-full" style="animation-delay: 0.6s; height: 70%"></div>
                <div class="pulse-bar w-1.5 bg-red-400 rounded-full" style="animation-delay: 0.7s; height: 50%"></div>
                <div class="pulse-bar w-1.5 bg-red-400 rounded-full" style="animation-delay: 0.8s; height: 30%"></div>
            </div>
        </div>

        <!-- Divider -->
        <div class="flex items-center gap-3">
            <div class="flex-1 h-px bg-gray-800"></div>
            <span class="text-gray-600 text-xs uppercase">eller</span>
            <div class="flex-1 h-px bg-gray-800"></div>
        </div>

        <!-- Upload form -->
        <form id="upload-form" method="POST" enctype="multipart/form-data" action="{{ route('record.store') }}" class="space-y-4">
            @csrf
            <label class="block w-full p-4 bg-gray-900 border-2 border-dashed border-gray-700 rounded-2xl text-center cursor-pointer hover:border-gray-500 transition-colors">
                <svg class="w-8 h-8 text-gray-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <span class="text-gray-400 text-sm">Vælg MP3-fil</span>
                <input type="file" name="audio" accept="audio/mpeg,audio/mp3,audio/webm,audio/wav" class="hidden" onchange="document.getElementById('upload-form').submit()">
            </label>

            <input
                type="text"
                name="title"
                placeholder="Titel (valgfri)"
                class="w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:border-amber-500 focus:outline-none text-sm"
            >
        </form>
    </div>

    <!-- Upload progress -->
    <div id="upload-progress" class="hidden text-center py-8 space-y-3">
        <div class="animate-spin w-10 h-10 border-3 border-amber-500 border-t-transparent rounded-full mx-auto"></div>
        <p class="text-gray-400 text-sm">Uploader optagelse...</p>
    </div>
</div>

<script>
    let mediaRecorder;
    let chunks = [];
    let timerInterval;
    let startTime;
    let stream;

    async function startRecording() {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm;codecs=opus' });

            // Fallback to default codec if opus not supported
            if (!MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });
            }

            chunks = [];

            mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) chunks.push(e.data);
            };

            mediaRecorder.onstop = () => {
                stream.getTracks().forEach(t => t.stop());
                uploadBlob();
            };

            mediaRecorder.start(1000);

            // UI
            document.getElementById('record-area').classList.add('hidden');
            document.getElementById('recording-active').classList.remove('hidden');
            document.getElementById('timer').textContent = '00:00';

            startTime = Date.now();
            timerInterval = setInterval(updateTimer, 200);

        } catch (err) {
            document.getElementById('record-area').classList.add('hidden');
            document.getElementById('mic-error').classList.remove('hidden');
        }
    }

    function stopRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
        }
        clearInterval(timerInterval);
        document.getElementById('recording-active').classList.add('hidden');
        document.getElementById('recording-ui').classList.add('hidden');
        document.getElementById('upload-progress').classList.remove('hidden');
    }

    function updateTimer() {
        const elapsed = Math.floor((Date.now() - startTime) / 1000);
        const mins = Math.floor(elapsed / 60).toString().padStart(2, '0');
        const secs = (elapsed % 60).toString().padStart(2, '0');
        document.getElementById('timer').textContent = mins + ':' + secs;
    }

    function uploadBlob() {
        const reader = new FileReader();
        const blob = new Blob(chunks, { type: 'audio/webm' });

        reader.onload = function () {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('record.store') }}';
            form.style.display = 'none';

            addHidden(form, '_token', '{{ csrf_token() }}');
            addHidden(form, 'audio_data', reader.result);
            addHidden(form, 'audio_name', 'recording.webm');

            const title = document.querySelector('input[name="title"]').value;
            addHidden(form, 'title', title);

            document.body.appendChild(form);
            form.submit();
        };

        reader.readAsDataURL(blob);
    }

    function addHidden(form, name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    }
</script>

</body>
</html>
