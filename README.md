# Mødeoptager — Dansk POC Backend

Proof-of-concept backend til en dansk mødeoptager med automatisk talegenkendelse (ASR) og AI-opsummering.

Bygget med Laravel 13, FilamentPHP v5, Python/Whisper og DeepSeek API.

## Arkitektur

```
[Filament Admin UI]  -->  [RecordingResource]  -->  [Recording Model]
                              │
    ┌─────────────────────────┼─────────────────────────┐
    ▼                         ▼                         ▼
Upload MP3          ProcessRecording          GenerateMeetingSummary
                    TranscriptionJob          Job
                         │                         │
                    Python (ASR)              DeepSeek API
```

## Krav

- PHP 8.3+
- SQLite (default) eller MySQL/PostgreSQL
- Python 3.10+ med CUDA-kompatibelt GPU (valgfrit til hurtig transskription)
- DeepSeek API-nøgle

## Docker (anbefalet til server)

Kræver Docker og NVIDIA Container Toolkit (til GPU):

```bash
# Klon og byg
git clone <repo-url> optager && cd optager

# Sæt dine API-nøgler
cp .env.example .env
# Redigér .env: DEEPSEEK_API_KEY=sk-din-nøgle, APP_KEY kan være tom (genereres ved opstart)

# Byg og start (med GPU)
docker compose up -d --build

# Opret admin-bruger
docker compose exec app php artisan tinker --execute '
    App\Models\User::factory()->create([
        "email" => "admin@example.com",
        "password" => "password"
    ]);
'
```

Uden GPU (CPU-fallback):
```bash
# Fjern deploy.resources.reservations.devices fra docker-compose.yml
# - eller brug:
docker compose up -d --build
# (virker uden GPU, bare langsommere transskription)
```

Admin-panelet er tilgængeligt på `http://<server-ip>:8080/admin`.

## Installation (lokal udvikling)

```bash
git clone <repo-url> optager
cd optager

# Installer PHP-afhængigheder
composer install

# Opsæt miljø
cp .env.example .env
php artisan key:generate

# Kør migreringer
php artisan migrate

# Opret en admin-bruger
php artisan tinker --execute '
    App\Models\User::factory()->create([
        "email" => "admin@example.com",
        "password" => "password"
    ]);
'

# Installer frontend
npm install
npm run build
```

## Konfiguration

Sæt følgende i `.env`:

```env
DEEPSEEK_API_KEY=sk-din-nøgle-her
DEEPSEEK_MODEL=deepseek-v4-flash
DEEPSEEK_BASE_URL=https://api.deepseek.com

TRANSCRIPTION_PYTHON_PATH=python3
TRANSCRIPTION_SCRIPT_PATH=python/transcribe.py
```

## Python-transskription

Installer Python-afhængigheder for ASR:

```bash
pip install -r python/requirements.txt
```

Modellen `CoRal-project/roest-v3-whisper-1.5b` (~4 GB) downloades automatisk ved første
kørsel. Du kan pre-downloade den for at undgå ventetid:

```bash
python -c "from transformers import pipeline; pipeline('automatic-speech-recognition', model='CoRal-project/roest-v3-whisper-1.5b'); print('Done')"
```

Tjek om modellen allerede er hentet:

```bash
python -c "import os; path = os.path.expanduser('~/.cache/huggingface/hub/models--CoRal-project--roest-v3-whisper-1.5b'); print('Installed' if os.path.isdir(path) else 'Not installed')"
```

Transskription er markant hurtigere med GPU (CUDA). CPU-fallback virker men er langsomt.

## Kørsel

```bash
# Lokal udvikling
composer run dev

# Docker
docker compose up -d
```

Admin-panelet findes på `/admin` — log ind med den oprettede bruger.

## Arbejdsgang

1. Upload en MP3-fil via adminpanelet (Optagelser → Opret)
2. Systemet dispatcher automatisk et transskriptionsjob til databasekøen
3. Python-scriptet transskriberer lyden med `CoRal-project/roest-v3-whisper-1.5b`
4. Efter transskription dispatcher systemet et opsummeringsjob
5. DeepSeek V4 genererer et struktureret møderesumé
6. Resultatet vises på optagelsens detaljeside i adminpanelet

## Statusflow

```
uploaded → queued_for_transcription → transcribing → transcribed
                                                       │
                                              queued_for_summary
                                                       │
                                                  summarizing
                                                       │
                                                   completed
failed (kan opstå i ethvert trin)
```

## Test

```bash
php artisan test --compact
```

## Projektstruktur

| Sti | Beskrivelse |
|-----|-------------|
| `app/Models/Recording.php` | Kerneentitet — optagelse med metadata, transskription og opsummering |
| `app/Models/RecordingJob.php` | Audit-tabel til joblogning og retry |
| `app/Jobs/ProcessRecordingTranscriptionJob.php` | Køjob der kalder Python ASR |
| `app/Jobs/GenerateMeetingSummaryJob.php` | Køjob der kalder DeepSeek for opsummering |
| `app/Services/Transcription/PythonRunner.php` | Symfony Process-wrapper til Python-script |
| `app/Services/Summarization/DeepSeekClient.php` | HTTP-klient til DeepSeek API |
| `app/Filament/Resources/Recordings/` | Filament admin UI (liste, opret, redigér, vis) |
| `app/Filament/Widgets/RecordingStatsOverview.php` | Dashboard widget med statistik |
| `python/transcribe.py` | Python-script til transskription |
| `routes/api.php` | Interne API-endpoints (stubbet til fremtidig ESP32-integration) |

## Fremtidige udvidelser

- ESP32 device-upload via interne API endpoints
- Per-device API keys og signed requests
- Webhook-notifikationer ved job completion
- Diarization / speaker separation
- Streaming / real-time transskription
