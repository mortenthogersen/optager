#!/usr/bin/env python3
"""
HTTP transskriptionsserver til Mac-miljø med MPS GPU-support.

Starter en lokal server der lytter på http://127.0.0.1:9137.
Modellen indlæses ved opstart og genbruges mellem requests.

Kør:   python python/server.py
"""

import json
import time
import warnings
from http.server import HTTPServer, BaseHTTPRequestHandler

import librosa
import torch
from transformers import pipeline

MODEL_NAME = "CoRal-project/roest-v3-whisper-1.5b"
PORT = 9137

pipe = None
device_name = "cpu"


def resolve_device():
    """Detect best available GPU: CUDA → MPS → DirectML → CPU."""
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


def load_pipeline():
    global pipe, device_name
    device, device_name = resolve_device()
    warnings.filterwarnings("ignore")

    kwargs = {
        "model": MODEL_NAME,
        "device": device,
    }

    if device_name == "directml":
        kwargs["torch_dtype"] = torch.float16

    pipe = pipeline("automatic-speech-recognition", **kwargs)
    print(f"Model loaded: {MODEL_NAME} on {device_name}")


class TranscribeHandler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        pass

    def _send_json(self, data, status=200):
        body = json.dumps(data, ensure_ascii=False)
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body.encode("utf-8"))))
        self.end_headers()
        self.wfile.write(body.encode("utf-8"))

    def do_GET(self):
        if self.path == "/health":
            self._send_json({"status": "ok", "device": device_name, "model": MODEL_NAME})
        else:
            self._send_json({"error": "Not found"}, 404)

    def do_POST(self):
        if self.path != "/transcribe":
            self._send_json({"error": "Not found"}, 404)
            return

        content_length = int(self.headers.get("Content-Length", 0))
        body = self.rfile.read(content_length)
        request = json.loads(body)

        audio_path = request.get("audio_path")
        language = request.get("language", "da")

        if not audio_path:
            self._send_json({"status": "error", "error": "Missing audio_path"}, 400)
            return

        start_time = time.time()

        try:
            audio, sr = librosa.load(audio_path, sr=16000, mono=True)

            output = pipe(
                {"array": audio, "sampling_rate": sr},
                generate_kwargs={"language": language, "task": "transcribe"},
                return_timestamps=True,
            )

            result = {
                "status": "success",
                "text": output.get("text", "").strip(),
                "model": MODEL_NAME,
                "language": language,
                "device": device_name,
                "runtime_ms": int((time.time() - start_time) * 1000),
                "error": None,
            }

            print(f"[{result['runtime_ms']}ms] {audio_path[:60]}... -> {len(result['text'])} chars")
            self._send_json(result)

        except Exception as e:
            result = {
                "status": "error",
                "text": "",
                "model": MODEL_NAME,
                "language": language,
                "device": device_name,
                "runtime_ms": int((time.time() - start_time) * 1000),
                "error": str(e),
            }
            self._send_json(result, 500)


if __name__ == "__main__":
    load_pipeline()

    print(f"Listening on http://127.0.0.1:{PORT}")
    server = HTTPServer(("127.0.0.1", PORT), TranscribeHandler)

    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nShutting down")
        server.shutdown()
