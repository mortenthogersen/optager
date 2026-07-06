#!/usr/bin/env python3
"""
Dansk tale-til-tekst transskription med CoRal-project/roest-v3-whisper-1.5b.

Understøtter NVIDIA (CUDA), AMD (DirectML på Windows, ROCm på Linux),
Apple Silicon (MPS) og CPU-fallback.

Anvendelse:
    python transcribe.py --input lydfil.mp3 --output-json result.json --language da

Valgfri GPU:
    python transcribe.py --input lydfil.mp3 --output-json result.json --device cuda

Output JSON format:
{
    "status": "success" | "error",
    "text": "transskriberet tekst...",
    "model": "CoRal-project/roest-v3-whisper-1.5b",
    "language": "da",
    "device": "cuda",
    "runtime_ms": 12345,
    "error": null | "fejlbesked..."
}
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
    parser.add_argument("--device", default=None, help="Device override: cuda, directml, mps, cpu (auto-detect hvis udeladt)")
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

        device = resolve_device(args.device)
        result["device"] = device

        pipe = pipeline(
            "automatic-speech-recognition",
            model="CoRal-project/roest-v3-whisper-1.5b",
            device=device,
        )

        output = pipe(
            {"array": audio_array, "sampling_rate": sample_rate},
            generate_kwargs={"language": args.language, "task": "transcribe"},
            return_timestamps=True,
        )

        result["status"] = "success"
        result["text"] = output.get("text", "").strip()
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


def resolve_device(requested: str | None) -> str:
    """Detect best available GPU, or use the one explicitly requested."""
    if requested and requested != "auto":
        return requested

    import torch

    if torch.cuda.is_available():
        return "cuda"

    if hasattr(torch.backends, "mps") and torch.backends.mps.is_available():
        return "mps"

    try:
        import torch_directml

        device = torch_directml.device()
        _test = torch.zeros(1).to(device)
        return "directml"
    except Exception:
        pass

    return "cpu"


def load_audio_preprocessed(file_path: str):
    """Indlæs og forbehandl lydfil til 16 kHz mono."""
    import librosa

    audio, sr = librosa.load(file_path, sr=16000, mono=True)

    return audio, sr


if __name__ == "__main__":
    main()
