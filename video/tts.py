"""Generates the English voice-over (one MP3 per scene) with Microsoft neural voices via edge-tts.
Pronunciation hints are applied only to the spoken text; subtitles keep the real spelling."""
import asyncio
import json
import pathlib
import subprocess

import edge_tts

HERE = pathlib.Path(__file__).parent
OUT = HERE / "build" / "voice"
VOICE = "en-US-AndrewNeural"
SAY = {"Veye,": "Vay-yeh,", "Veye ": "Vay-yeh ", "Veye.": "Vay-yeh.", "Veye's": "Vay-yeh's", "Kreyòl": "Kray-ol"}


def spoken(text: str) -> str:
    for k, v in SAY.items():
        text = text.replace(k, v)
    return text


async def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    scenes = json.loads((HERE / "narration.json").read_text(encoding="utf-8"))
    for s in scenes:
        mp3 = OUT / f"{s['id']}.mp3"
        for attempt in range(5):
            try:
                await edge_tts.Communicate(spoken(s["text"]), VOICE, rate="-4%").save(str(mp3))
                break
            except Exception as e:  # the service sometimes drops a request
                print(f"  retry {attempt + 1} for {s['id']}: {e}")
                await asyncio.sleep(3 + attempt * 2)
        else:
            raise SystemExit(f"could not synthesize {s['id']}")
        dur = subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", str(mp3)],
                             capture_output=True, text=True).stdout.strip()
        s["duration"] = round(float(dur), 2)
        print(f"{s['id']}: {s['duration']} s")
    (HERE / "build" / "timing.json").write_text(json.dumps(scenes, indent=1), encoding="utf-8")
    print("total", round(sum(s["duration"] for s in scenes), 1), "s")


asyncio.run(main())
