# Veye demo video script (target 2:50, hard limit 3:00)

**Format:** screen recording at 1080p plus voice-over in English. Add English subtitles (YouTube auto-captions, then fix them).

**Before you record:**
- Use your own Chrome (139+), with the Gemini Nano flag on and the model already downloaded. Do one check first so the download is done.
- Sign in as `demo`. Install the extension. Close other tabs. Use light mode for the web app; it reads better on video.
- Open these tabs in order:
  1. petiht.com/veye
  2. petiht.com/veye/app.php
  3. A fake scam page (see below)
  4. petiht.com/veye/community.php
  5. github.com/bastien509/veye

The timestamps are a guide; the total must stay under 3:00.

---

### 0:00–0:20 · The problem (hook)
**Screen:** a phone mock-up or the landing page hero showing the "remittance on hold" SMS.

**Voice:**
> "My family sends money home to Haiti every month. Texts like this one arrive every week: 'Your transfer is on hold. Send the 6-digit code in 30 minutes.' The people who get them are our parents and grandparents. They need a second opinion fast, but the messages they worry about are the most private ones they have."

### 0:20–0:35 · The idea
**Screen:** the landing page "Three layers, one answer" section.

**Voice:**
> "Veye, which means 'watch out' in Haitian Kreyòl, is a scam checker that thinks on your device first. It's built on Firebase AI Logic hybrid inference: Gemini Nano runs on your device when it's there, and Gemini in the cloud answers when it isn't."

### 0:35–1:15 · On-device check (the key moment)
**Screen:** app.php. Point the cursor at the status line ("Gemini Nano is ready on this device"). Click the **Remittance "on hold"** sample, keep **Smart** mode and English, and click **Check it**.

**Voice:**
> "Here's that text. Smart mode, English. Veye runs Gemini Nano right here in Chrome."
> (result appears) "Scam, with the red flags in plain words: it asks for a code, it uses a fake Western Union address, it tells you not to call your bank. And look at this badge: 'On this device, zero bytes sent.'"

**Screen:** open "How Veye decided where to run" and point at the lines.

> "Veye shows why it ran where it did. The model was ready, so the prompt never left the laptop."

**Optional (5 s):** turn off Wi-Fi, check again, and show it still works: "It works offline too."

### 1:15–1:45 · Hybrid routing: Kreyòl goes to the cloud
**Screen:** click the **Kreyòl lottery** sample. Set "Answer in" to Kreyòl and click Check.

**Voice:**
> "Now a Kreyòl scam. Gemini Nano doesn't answer in Kreyòl yet, so Veye's router switches to the cloud through the same SDK call. But first it removes phone numbers, codes, cards and emails on the device. The answer comes back in Kreyòl, and the badge says 'Cloud, redacted'."

**Screen:** point at the trace line: "Gemini Nano does not answer in Haitian Creole yet…"

### 1:45–2:00 · Privacy modes
**Screen:** hover over the three choice cards.

**Voice:**
> "You choose how private each check is. Private never uses the cloud. Smart goes to the device first. Deep check uses the cloud for long pages, and screenshots are only sent if you say yes."

### 2:00–2:30 · Chrome extension
**Screen:** open a fake scam page (save one locally with a look-alike "westernunion-release" title and a card form). The toolbar badge shows a red "!". Open the side panel and click **Use this page's text**, then **Check it**.

**Voice:**
> "The Chrome extension checks every page you open, but only with local rules, so nothing is sent. Here it flagged a look-alike page. One click and Gemini Nano reads the whole page on my computer. Reading every page is only acceptable because it happens on the device."

**Optional (5 s):** select text on a page, right-click, and choose "Check with Veye".

### 2:30–2:50 · Under the hood + impact
**Screen:** github.com/bastien509/veye, scrolled to `engine.js`, showing `getGenerativeModel` with `InferenceMode`. Then the admin Stats tab ("on_device" vs "in_cloud").

**Voice:**
> "Under the hood: one prompt and one JSON schema for both Gemini Nano and the cloud. App Check uses a custom provider so it works inside the extension, and Remote Config lets us add new scam patterns without a new build. Veye never stores what you check."

### 2:50–3:00 · Close
**Screen:** landing page with the logo.

**Voice:**
> "Veye. Ask your own device first. Try it at petiht.com/veye."

---

## Shot checklist
- [ ] The status line shows Gemini Nano is ready (proves the device path)
- [ ] An "On this device · 0 bytes sent" badge
- [ ] A "Cloud · redacted" badge (Kreyòl)
- [ ] The routing trace, opened
- [ ] The extension badge plus a side-panel check
- [ ] `getGenerativeModel` + `InferenceMode` code on screen
- [ ] The Apache-2.0 license visible on the GitHub repo
- [ ] The total is under 3:00 and English subtitles are on
