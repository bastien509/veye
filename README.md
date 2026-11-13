# Veye — a scam shield that thinks on your device

**Veye** ("watch out" in Haitian Kreyòl) checks suspicious messages, web pages and screenshots for scams. It uses the **Firebase AI Logic hybrid inference SDK**: Gemini Nano runs the check **on the device** when Chrome has it, and Gemini in the cloud answers when it doesn't. Before anything can reach the cloud, Veye removes emails, phone numbers, card numbers and codes on the device.

Built for **#BuildHybridAI 2026** (Web / Chrome platform).

- Live app: https://petiht.com/veye
- Demo account for judges: `demo` / `VeyeDemo509`
- Chrome extension: https://petiht.com/veye/extension.php

## The problem

Fake "your transfer is on hold" texts, "grandson in jail" calls, job offers with a fee, and look-alike bank pages hit people who send money home, older adults, and people new to online banking. A tool that reads your messages and every page you open is only acceptable if what it reads stays with you. That is what on-device AI makes possible.

## How the hybrid routing works

Every check runs through three layers:

1. **Local rules** (`client/src/rules.js`): instant, offline red-flag patterns in English, Kreyòl, French and Spanish, plus look-alike domain checks. They never call an AI or the network. The extension uses only this layer for the toolbar badge on every page.
2. **Gemini Nano on device**, through `getGenerativeModel(ai, { mode: InferenceMode.PREFER_ON_DEVICE | ONLY_ON_DEVICE, onDeviceParams })` with a JSON `responseConstraint`.
3. **Gemini in the cloud** through the same SDK call (`inCloudParams` with a `responseSchema`), used only when it is needed.

Veye's router (`client/src/engine.js`) picks the mode for each check and shows its reasoning to the user:

| Situation | Mode | What leaves the device |
|---|---|---|
| **Private** mode | `ONLY_ON_DEVICE` | Nothing. With no local model, only the rules run. |
| **Smart** mode, Gemini Nano available | `PREFER_ON_DEVICE` | Nothing |
| **Smart** mode, no Gemini Nano | `PREFER_ON_DEVICE` (SDK falls back) | Redacted text only |
| Answer language Nano doesn't support yet (Kreyòl, French) | `PREFER_IN_CLOUD` | Redacted text only |
| Long page (over 6,000 characters) | `PREFER_IN_CLOUD` | Redacted text only |
| **Deep check** mode | `PREFER_IN_CLOUD` | Redacted text. A screenshot only after the user agrees. |
| Offline | `ONLY_ON_DEVICE`, or rules only | Nothing |

Each result shows where the AI ran (`response.inferenceSource`), how long it took, and roughly how many bytes were sent.

## Firebase products used

- **Firebase AI Logic** (hybrid inference, Gemini Developer API backend)
- **App Check**: a custom provider. Signed-in clients get short-lived tokens from `web/api/appcheck.php`, which signs them with the project's service account. This also covers the Chrome extension, where reCAPTCHA cannot run. reCAPTCHA Enterprise is optional for the website.
- **Remote Config**: changes the cloud model, the languages Nano answers in, and extra scam patterns without a new build.

## Privacy

- Veye stores **no message text and no screenshots**. A check logs only its verdict, where it ran, its duration and how many items were redacted.
- Community warnings keep a redacted snippet (redacted again on the server) and the domain, and are reviewed before they are published.
- Secrets (database password, Firebase service account) live only in the server config. The service account is encrypted at rest (AES-256-GCM).

## Repository layout

```
client/      Shared engine (rules, redaction, prompt, hybrid router) + esbuild build
  src/engine.js      Firebase AI Logic hybrid inference + routing
  src/rules.js       Offline red-flag rules
  src/redact.js      On-device redaction
  src/prompt.js      One prompt + JSON schema for device and cloud
  src/web.js         Web app UI
  src/ext-panel.js   Chrome extension side panel
  src/ext-content.js Chrome extension content script (rules only)
extension/   Chrome MV3 extension (side panel, context menu, badge)
web/         PHP 8 + MySQL site: landing, checker, community, admin, JSON API
```

## Run it yourself

1. Create a Firebase project and a Web app. Enable **Firebase AI Logic** with the Gemini Developer API.
2. Copy `web/inc/config.sample.php` to `web/inc/config.php`. Fill in the MySQL credentials, a random `secret_key` and `password_hash()` values for the seed accounts.
3. Build the client: `cd client && npm install && node build.mjs`. Set `VEYE_BASE=http://localhost:8130` to point the extension at a local server.
4. Serve `web/` with PHP 8.1+ (local: `php -S localhost:8130 -t web web/router.php`).
5. Sign in as admin, open **Admin → Keys & AI** and paste:
   - the Firebase web config
   - the service account JSON (for App Check)
6. For on-device AI, use Chrome 139+ and enable `chrome://flags/#prompt-api-for-gemini-nano-multimodal-input`.
7. Load the extension: open `chrome://extensions`, turn on Developer mode, choose **Load unpacked** and select the `extension/` folder.

## License

Apache License 2.0. See [LICENSE](LICENSE).
