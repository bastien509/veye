# Devpost submission: Veye

Paste each section into the matching Devpost field. Before you submit, replace or delete the parts marked **[CHECK]**.

---

## Project name
Veye: the scam shield that thinks on your device

## Elevator pitch (200 characters max)
Is this a scam? Veye asks Gemini Nano on your own device first, so your bank pages and messages never leave it. No local model? It falls back to the cloud with personal data removed first.

## Platform
Web (Chrome): web app + Chrome extension

## Links
- Live app: https://petiht.com/veye
- Demo login: `demo` / `VeyeDemo509`
- Chrome extension: https://petiht.com/veye/extension.php
- Code (Apache-2.0): https://github.com/bastien509/veye
- Video: **[CHECK: YouTube link]**

---

## About the project

### Inspiration
In our community, the scam texts sound like family business. "Your $480 transfer to Haiti is on hold. Send the 6-digit code." "Grandma, it's me, I need bail money, pay with gift cards." "Ou genyen gwo lo Digicel la, peye 1500 goud pou debloke l." They target people who send money home, older parents, and people who just started banking on a phone.

The best person to ask "is this a scam?" is an AI that reads the whole message and explains it calmly. But the messages people worry about are the most private ones: bank pages, codes, family chats. Sending all of that to a server is part of the problem. On-device AI changes that. A scam checker can read what you read without ever uploading it.

### What it does
Veye checks a message, a web page or a screenshot and answers in plain words:
- a verdict (**safe / suspicious / scam**) with a risk score and the type of scam
- the red flags in *this* message
- what to do next
- **where the AI ran**: "On this device · 0 bytes sent" or "Cloud · 3 items redacted"

Every check goes through three layers:
1. **Local rules**: instant, offline red-flag patterns in English, Haitian Kreyòl, French and Spanish, plus look-alike domain detection ("westernunion-release.help" is not Western Union).
2. **Gemini Nano on the device** through Firebase AI Logic: reads the whole message and returns structured JSON. Nothing is uploaded.
3. **Gemini in the cloud** through the same Firebase AI Logic call, only when it is needed. Before anything leaves the device, Veye removes emails, phone numbers, card numbers, ID numbers and codes, and cuts links down to their domain.

The user chooses how private each check is:
- **Private**: device only (`ONLY_ON_DEVICE`). With no local model, only the rules run; nothing is ever sent.
- **Smart** (default): device first (`PREFER_ON_DEVICE`); the cloud gets redacted text only.
- **Deep check**: cloud first (`PREFER_IN_CLOUD`), for long pages and screenshots. A screenshot can't be redacted, so Veye asks before sending one.

The **Chrome extension** puts this in a side panel. A badge on every page comes from the local rules only: no AI call, no network. "Check this page" or right-click → "Check with Veye" runs Gemini Nano on the page text, on your computer.

A **community page** shows the scam types people ran into this month, plus warnings people chose to share. Shared warnings keep only redacted text and the domain, and are reviewed before they are published.

### How we built it with Firebase AI Logic
One prompt and one output schema run on both sides of the hybrid SDK:

```js
getGenerativeModel(ai, {
  mode: InferenceMode.PREFER_ON_DEVICE, // or ONLY_ON_DEVICE / PREFER_IN_CLOUD
  inCloudParams: { model: 'gemini-3.5-flash-lite',
    generationConfig: { responseMimeType: 'application/json', responseSchema } },
  onDeviceParams: {
    createOptions: { expectedInputs: [{ type: 'text', languages: ['en', lang] }, { type: 'image' }],
                     expectedOutputs: [{ type: 'text', languages: [lang] }] },
    promptOptions: { responseConstraint: jsonSchema },
  },
});
```

The interesting part is **Veye's router**, which picks the inference mode for every check and shows its reasoning to the user:
- **Language-aware routing.** Gemini Nano doesn't answer in Haitian Kreyòl yet. When the user wants a Kreyòl answer, Veye switches to `PREFER_IN_CLOUD`, and only redacted text leaves the device. English and Spanish answers stay on the device.
- **Length-aware routing.** Content over about 6,000 characters goes to the cloud. It stays well under Nano's token window, and the text is redacted first.
- **Offline-aware.** With no network, Veye forces `ONLY_ON_DEVICE`. With no network and no model, only the rules run.
- **Consent for images.** Screenshots (PNG/JPEG work on the device) never go to the cloud without a yes.
- `response.inferenceSource` drives the "where it ran" badge. The router's decisions appear in a "How Veye decided where to run" list on every result.
- The model download (`initializeDeviceModel`) starts on the user's first click and shows progress.

**Other Firebase products**
- **App Check (custom provider).** Chrome extension pages can't run reCAPTCHA. Instead, signed-in clients get short-lived App Check tokens from Veye's server, which signs them with the Firebase service account. The key is stored encrypted on the server. The same provider covers the website and the extension.
- **Remote Config.** Changes the cloud model, the languages Nano answers in, and extra scam patterns without shipping a new build.

**Stack:** vanilla JS bundled with esbuild (Firebase JS SDK 13), Chrome MV3 extension (side panel, context menu, content script), PHP 8 + MySQL backend on Hostinger. The backend handles accounts, App Check tokens, rate limits, check counts and community reports, and stores no message content.

### Challenges we ran into
- **App Check in a Chrome extension.** reCAPTCHA can't run on `chrome-extension://` pages. We built a custom App Check provider: the server signs a custom token with the service account and exchanges it with the App Check REST API, only for signed-in users and with rate limits.
- **Keeping the cloud fallback private.** With `PREFER_ON_DEVICE` the SDK decides the fallback on its own, so the router redacts *before* every call that could reach the cloud, and sends the original text only in Private mode.
- **False alarms.** "We will never ask for a one-time code" is a safety notice, not a red flag. The offline rules check for negations so real bank notices don't get flagged.
- **A strict CSP blocked on-device images.** Firebase AI Logic hands a screenshot to Gemini Nano by converting it with `fetch("data:...")`; our Content-Security-Policy blocked that ("Failed to fetch"). Adding `data:` to `connect-src` fixed it.
- **Small-model echoes.** Nano sometimes returned a redaction tag such as `[PHONE]` as a red flag. Tags are now filtered out, and the sender field is redacted and counted like the message text.
- **One schema, two engines.** Chrome's Prompt API takes a JSON Schema `responseConstraint`, while the cloud takes a Firebase `Schema`. We keep both in step and parse the results the same way.

### Accomplishments that we're proud of
- A real scam check, end to end, in **Haitian Kreyòl**, a language most AI products ignore, while English and Spanish checks never leave the device.
- Veye stores **no message text and no screenshots**, ever.
- One engine shared by the web app and the Chrome extension.
- **Verified on-device, nothing sent:** in Chrome on a CPU-only laptop (no graphics card), Gemini Nano judged the remittance scam text **Scam, 90%** with its red flags in 61–78 s, and read a phishing **screenshot** on-device in about 39 s, both with "On this device · 0 bytes sent". Laptops with a GPU are much faster.

### What we learned
Hybrid inference is more than a fallback switch. It changes what an app is allowed to do. A page-reading extension is only acceptable when the reading happens locally. The cloud then becomes a deliberate, visible choice: for a language, for a long page, or when the user asks.

### What's next for Veye
- **Android**: a share-sheet target ("Share → Veye") for SMS and WhatsApp screenshots, using Gemini Nano on Android through the same Firebase AI Logic hybrid SDK.
- Kreyòl answers on the device as soon as Nano supports it. Remote Config flips it on with no new build.
- Work with remittance agents and churches in Haitian communities to share warnings about the newest scams.

---

## Built with
firebase-ai-logic, gemini-nano, gemini, firebase-app-check, firebase-remote-config, chrome-prompt-api, chrome-extension, javascript, esbuild, php, mysql, hostinger

## Testing instructions for judges
1. Open https://petiht.com/veye and sign in with `demo` / `VeyeDemo509`.
2. **Cloud path (any browser):** pick a sample, such as "Kreyòl lottery", and click **Check it**. The badge reads "Cloud · … redacted".
3. **On-device path:**
   - Use a recent Chrome on a desktop (Gemini Nano needs about 22 GB of free disk and a GPU with more than 4 GB of VRAM, or 16 GB of RAM). For screenshots on older Chrome versions, enable `chrome://flags/#prompt-api-for-gemini-nano-multimodal-input` and restart Chrome.
   - Reload the page; the status line should say Gemini Nano is ready or can be downloaded. The first check starts the download.
   - Choose **Smart** or **Private** with English or Spanish. The badge reads "On this device · 0 bytes sent". On a computer without a GPU, an on-device check can take about a minute.
4. Open **"How Veye decided where to run"** under any result to see the routing.
5. **Extension:** download the zip from https://petiht.com/veye/extension.php. Unzip it, then in `chrome://extensions` turn on Developer mode, choose **Load unpacked** and pick the folder. Sign in with the same demo account.
