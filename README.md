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

- Docker + Docker Compose
- DeepSeek API-nøgle (til opsummering)
- OpenRouter API-nøgle (til transskription)
- NVIDIA GPU + Container Toolkit (valgfrit — giver hurtigere lokal transskription)

## Docker (Proxmox / server)

### 1. Forbered Proxmox-containeren

```bash
# Inde i din LXC/VM på Proxmox:
apt update && apt install -y docker.io docker-compose-v2 git curl
```

Har du et NVIDIA GPU i maskinen, installer også:
```bash
# Installer NVIDIA-driver og Container Toolkit (på Proxmox host)
apt install -y nvidia-driver-535 nvidia-container-toolkit
systemctl restart docker
```

### 2. Klon og start

```bash
git clone https://github.com/mortenthogersen/optager.git && cd optager

# Sæt API-nøgler
cp .env.example .env
nano .env  # Udfyld: DEEPSEEK_API_KEY + OPENROUTER_API_KEY
```

Sørg for at `.env` indeholder:
```env
TRANSCRIPTION_RUNNER=openrouter
OPENROUTER_API_KEY=sk-or-v1-din-openrouter-nøgle
OPENROUTER_STT_MODEL=nvidia/parakeet-tdt-0.6b-v3
DEEPSEEK_API_KEY=sk-din-deepseek-nøgle
DEEPSEEK_MODEL=deepseek-v4-flash
APP_KEY=
```

### 3. Byg og start

```bash
# GPU-maskine (NVIDIA):
docker compose up -d --build

# CPU-only (ingen GPU):
# Redigér docker-compose.yml: fjern 'deploy.resources.reservations.devices' blokken
docker compose up -d --build
```

Første build tager ~10 minutter (downloader PyTorch + Whisper model). Efterfølgende builds er hurtige.

### 4. Opret admin-bruger

```bash
docker compose exec app php artisan tinker --execute '
    App\Models\User::factory()->create([
        "email" => "admin@example.com",
        "password" => "password"
    ]);
'
```

### 5. Tilgå fra telefon

Appen er tilgængelig på dit lokale netværk:

| Side | URL |
|------|-----|
| **Optagelse (telefon)** | `http://<proxmox-ip>:8080/record` |
| **Admin-panel** | `http://<proxmox-ip>:8080/admin` |

Åbn optagelsessiden på din telefons browser, tryk på den røde knap og optag.

### Sådan virker det

```
Telefon → http://server:8080/record → Laravel i Docker
                                          │
                                    OpenRouter API (NVIDIA Parakeet STT)
                                          │  ~0,03 kr. / 3 min møde
                                          ▼
                                    DeepSeek API (møderesumé)
                                          │
                                          ▼
                                    Admin-panel på /admin
```

## Lokal udvikling

### Docker på Mac (Metal GPU)

Docker på Mac kan ikke tilgå Metal GPU direkte. I stedet kører Python på macOS host med MPS-acceleration og Laravel i Docker:

```bash
# 1. Opret et Python virtual environment (Homebrew Python kræver dette)
python3 -m venv venv
source venv/bin/activate

# 2. Installer afhængigheder
pip install -r python/requirements.txt

# 3. Start transskriptionsserveren (behold dette terminavindue åbent)
python python/server.py
# Lytter på http://127.0.0.1:9137 — bruger MPS GPU automatisk

# 4. Start Laravel i Docker (i et nyt terminalvindue)
docker compose -f docker-compose.mac.yml up -d --build

# 5. Opret admin
docker compose exec app php artisan tinker --execute '
    App\Models\User::factory()->create([
        "email" => "admin@example.com",
        "password" => "password"
    ]);
'
```

Herefter kør transskription på Mac'ens MPS GPU (~30-60 sek for 1,5 minuts lyd), mens Laravel kører i Docker. Husk at aktivere `venv` med `source venv/bin/activate` hver gang du starter serveren.

### Lokalt på Mac med Herd (anbefalet til udvikling)

Hvis du har [Herd](https://herd.laravel.com) installeret, kører alt nativt — ingen Docker nødvendig:

```bash
# 1. Klon projektet ind i en Herd-mappe
cd ~/Herd
git clone https://github.com/mortenthogersen/optager.git
cd optager

# 2. Opsæt Laravel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build

# 3. Opret admin-bruger
php artisan tinker --execute '
    App\Models\User::factory()->create([
        "email" => "admin@example.com",
        "password" => "password"
    ]);
'

# 4. Opsæt Python venv (Homebrew Python kræver dette)
python3 -m venv venv
source venv/bin/activate
pip install -r python/requirements.txt

# 5. Start transskriptionsserveren (Terminal 1)
python python/server.py

# 6. Start queue worker (Terminal 2)
php artisan queue:work --tries=1 --timeout=3600
```

Åbn `https://optager.test/admin` i browseren. Sæt `TRANSCRIPTION_RUNNER=http` i `.env` hvis du bruger Python-serveren; med `TRANSCRIPTION_RUNNER=process` kalder Laravel Python direkte via CLI (kræver at venv er aktiveret i queue worker-terminalen).

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
