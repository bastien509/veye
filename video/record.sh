#!/bin/bash
# Opens a demo scene in the user's Chrome (new tab, no extension attached) and records the page area.
# usage: record.sh <name> <seconds> <url>
cd "$(dirname "$0")"
mkdir -p build/raw
powershell -NoProfile -Command "Start-Process 'C:\Program Files\Google\Chrome\Application\chrome.exe' '$3'" >/dev/null
sleep 1
powershell -NoProfile -ExecutionPolicy Bypass -File front.ps1 >/dev/null
ffmpeg -hide_banner -loglevel error -y -f gdigrab -draw_mouse 0 -framerate 30 \
  -offset_x 0 -offset_y 121 -video_size 1904x910 -i desktop -t "$2" \
  -c:v libx264 -preset ultrafast -crf 18 -pix_fmt yuv420p "build/raw/$1.mp4"
[ -s "build/raw/$1.mp4" ] && echo "recorded $1 ($2 s)"
