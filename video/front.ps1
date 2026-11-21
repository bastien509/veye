# Brings the Chrome window whose title contains "Veye" to the front (for screen recording).
Add-Type @"
using System;
using System.Runtime.InteropServices;
public class W {
  [DllImport("user32.dll")] public static extern bool SetForegroundWindow(IntPtr h);
  [DllImport("user32.dll")] public static extern bool ShowWindow(IntPtr h, int n);
  [DllImport("user32.dll")] public static extern void keybd_event(byte k, byte s, uint f, UIntPtr e);
}
"@
$p = Get-Process chrome -ErrorAction SilentlyContinue | Where-Object { $_.MainWindowTitle -like "*Veye*" } | Select-Object -First 1
if (-not $p) { "no Kaye window"; exit 1 }
[W]::keybd_event(0x12, 0, 0, [UIntPtr]::Zero)      # Alt down: lets SetForegroundWindow succeed
[W]::ShowWindow($p.MainWindowHandle, 3) | Out-Null  # maximized
[W]::SetForegroundWindow($p.MainWindowHandle) | Out-Null
[W]::keybd_event(0x12, 0, 2, [UIntPtr]::Zero)      # Alt up
"front: " + $p.MainWindowTitle
