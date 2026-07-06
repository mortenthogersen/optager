#!/usr/bin/env python3
"""
Dansk tale-til-tekst transskription med CoRal-project/roest-v3-whisper-1.5b.

Understøtter NVIDIA (CUDA), AMD (DirectML på Windows, ROCm på Linux),
Apple Silicon (MPS) og CPU-fallback.

Anvendelse:
    python transcribe.py --input lydfil.mp3 --output-json result.json --language da
"""

import argparse
import json
import sys
import time
import warnings


def main():
    parser = argparse.ArgumentParser(description="Transskribér en lydfil med dansk Whisper-model")
    parser.add_argument("--input", required=True, help="Sti til input lydfil (MP3)")
    parser.add_argument("--output-json", required=True, help="Sti til output JSON-fil")
    parser.add_argument("--language", default="da", help="Sprogkode (default: da)")
    parser.add_argument("--device", default=None, help="Device override: cuda, directml, mps, cpu (auto-detect)")
    args = parser.parse_args()

    result = {
        "status": "error",
        "text": "",
        "model": "CoRal-project/roest-v3-whisper-1.5b",
        "language": args.language,
        "device": "cpu",
        "runtime_ms": 0,
        "error": None,
    }

    start_time = time.time()

    try:
        import torch
        from transformers import pipeline

        warnings.filterwarnings("ignore")

        audio_array, sample_rate = load_audio_preprocessed(args.input)

        device, device_name = resolve_device(args.device)
        result["device"] = device_name

        pipe = pipeline(
            "automatic-speech-recognition",
            model="CoRal-project/roest-v3-whisper-1.5b",
            device=device,
            torch_dtype=torch.float16 if device_name == "directml" else None,
        )

        audio_duration = len(audio_array) / sample_rate

        if audio_duration > 30:
            text = transcribe_chunked(pipe, audio_array, sample_rate, args.language)
        else:
            output = pipe(
                {"array": audio_array, "sampling_rate": sample_rate},
                generate_kwargs={"language": args.language, "task": "transcribe"},
            )
            text = output.get("text", "").strip()

        result["status"] = "success"
        result["text"] = text
        result["runtime_ms"] = int((time.time() - start_time) * 1000)

    except Exception as e:
        result["status"] = "error"
        result["error"] = str(e)
        result["runtime_ms"] = int((time.time() - start_time) * 1000)

    with open(args.output_json, "w", encoding="utf-8") as f:
        json.dump(result, f, ensure_ascii=False, indent=2)

    if result["status"] == "success":
        print(json.dumps(result, ensure_ascii=False))
        sys.exit(0)
    else:
        print(json.dumps(result, ensure_ascii=False), file=sys.stderr)
        sys.exit(1)


def transcribe_chunked(pipe, audio_array, sample_rate, language):
    """Split audio into 30s chunks, transcribe each, concatenate results."""
    chunk_duration = 30
    overlap_duration = 1
    chunk_samples = chunk_duration * sample_rate
    overlap_samples = overlap_duration * sample_rate
    step = chunk_samples - overlap_samples

    texts = []
    start = 0

    while start < len(audio_array):
        end = min(start + chunk_samples, len(audio_array))
        chunk = audio_array[start:end]

        output = pipe(
            {"array": chunk, "sampling_rate": sample_rate},
            generate_kwargs={
                "language": language,
                "task": "transcribe",
                "num_beams": 1,
            },
        )

        chunk_text = output.get("text", "").strip()
        if chunk_text:
            texts.append(chunk_text)

        start += step

    return " ".join(texts)


def resolve_device(requested: str | None) -> tuple:
    """Detect best available GPU, return (device, name string)."""
    import torch

    if requested and requested not in ("auto", ""):
        return (requested, requested)

    if torch.cuda.is_available():
        return (0, "cuda")

    if hasattr(torch.backends, "mps") and torch.backends.mps.is_available():
        return ("mps", "mps")

    try:
        import torch_directml

        dml_device = torch_directml.device()
        _test = torch.zeros(1).to(dml_device)
        return (dml_device, "directml")
    except Exception:
        pass

    return (-1, "cpu")


def load_audio_preprocessed(file_path: str):
    """Indlæs og forbehandl lydfil til 16 kHz mono."""
    import librosa

    audio, sr = librosa.load(file_path, sr=16000, mono=True)

    return audio, sr


if __name__ == "__main__":
    main()
