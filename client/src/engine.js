// Veye hybrid engine: local rules → Gemini Nano on device → Gemini in the cloud,
// all through the Firebase AI Logic hybrid inference SDK. Shared by the web app
// and the Chrome extension side panel.
import { initializeApp, getApps } from 'firebase/app';
import { initializeAppCheck, CustomProvider, ReCaptchaEnterpriseProvider } from 'firebase/app-check';
import { getAI, getGenerativeModel, GoogleAIBackend, InferenceMode, InferenceSource } from 'firebase/ai';
import { getRemoteConfig, fetchAndActivate, getString } from 'firebase/remote-config';
import { scoreRules, guessScamType } from './rules.js';
import { redact } from './redact.js';
import { buildPrompt, parseAnswer, DEVICE_SCHEMA, CLOUD_SCHEMA, LANG_NAMES } from './prompt.js';

const DEVICE_TEXT_LIMIT = 6000;   // keeps the on-device prompt well under Gemini Nano's 9216-token window
const CLOUD_TEXT_LIMIT = 20000;
const AI_TIMEOUT_MS = 90000;

const MODE_LABEL = { private: 'Private', smart: 'Smart', deep: 'Deep check' };

function withTimeout(promise, ms, what) {
  let t;
  return Promise.race([
    promise.finally(() => clearTimeout(t)),
    new Promise((_, rej) => { t = setTimeout(() => rej(new Error(`${what} took too long`)), ms); }),
  ]);
}

/**
 * @param {object} opts
 * @param {object} opts.config        public config from /api/config.php
 * @param {Function} opts.getAppCheckToken  async () => ({token, expireTimeMillis}) for the custom provider
 */
export function createEngine({ config, getAppCheckToken }) {
  const settings = {
    cloudModel: config.cloudModel || 'gemini-3.5-flash-lite',
    deviceLanguages: config.deviceLanguages?.length ? config.deviceLanguages : ['en', 'es'],
    extraRules: Array.isArray(config.extraRules) ? config.extraRules : [],
  };
  let ai = null;
  let initError = '';
  const models = new Map();

  if (config.firebase) {
    try {
      const app = getApps().find((a) => a.name === 'veye') || initializeApp(config.firebase, 'veye');
      if (config.appCheck === 'custom' && getAppCheckToken) {
        initializeAppCheck(app, { provider: new CustomProvider({ getToken: getAppCheckToken }), isTokenAutoRefreshEnabled: true });
      } else if (config.appCheck === 'recaptcha' && config.recaptchaSiteKey) {
        initializeAppCheck(app, { provider: new ReCaptchaEnterpriseProvider(config.recaptchaSiteKey), isTokenAutoRefreshEnabled: true });
      }
      ai = getAI(app, { backend: new GoogleAIBackend() });

      // Remote Config lets us change routing and add scam patterns without shipping a new build.
      try {
        const rc = getRemoteConfig(app);
        rc.settings.minimumFetchIntervalMillis = 60 * 60 * 1000;
        rc.defaultConfig = {
          veye_cloud_model: settings.cloudModel,
          veye_device_languages: settings.deviceLanguages.join(','),
          veye_extra_rules: JSON.stringify(settings.extraRules),
        };
        fetchAndActivate(rc).then(() => {
          settings.cloudModel = getString(rc, 'veye_cloud_model') || settings.cloudModel;
          const langs = getString(rc, 'veye_device_languages').split(',').map((s) => s.trim()).filter(Boolean);
          if (langs.length) settings.deviceLanguages = langs;
          try {
            const extra = JSON.parse(getString(rc, 'veye_extra_rules') || '[]');
            if (Array.isArray(extra)) settings.extraRules = extra;
          } catch { /* keep admin rules */ }
          models.clear();
        }).catch(() => { /* offline or not set up: defaults stay */ });
      } catch { /* Remote Config unavailable in this context */ }
    } catch (e) {
      initError = e?.message || String(e);
    }
  }

  const hasPromptApi = () => typeof globalThis.LanguageModel !== 'undefined';
  const deviceLang = (lang) => (settings.deviceLanguages.includes(lang) ? lang : 'en');

  /** 'available' | 'downloadable' | 'downloading' | 'unavailable' | 'unsupported' */
  async function deviceStatus(lang = 'en', withImage = false) {
    if (!hasPromptApi()) return 'unsupported';
    try {
      const expectedInputs = [{ type: 'text', languages: ['en', deviceLang(lang)] }];
      if (withImage) expectedInputs.push({ type: 'image' });
      return await globalThis.LanguageModel.availability({
        expectedInputs,
        expectedOutputs: [{ type: 'text', languages: [deviceLang(lang)] }],
      });
    } catch {
      return 'unavailable';
    }
  }

  function model(mode, lang) {
    const key = `${mode}|${lang}|${settings.cloudModel}`;
    if (!models.has(key)) {
      const outLang = deviceLang(lang);
      models.set(key, getGenerativeModel(ai, {
        mode,
        inCloudParams: {
          model: settings.cloudModel,
          generationConfig: { responseMimeType: 'application/json', responseSchema: CLOUD_SCHEMA, temperature: 0.2, maxOutputTokens: 900 },
        },
        onDeviceParams: {
          createOptions: {
            expectedInputs: [{ type: 'text', languages: [...new Set(['en', outLang])] }, { type: 'image' }],
            expectedOutputs: [{ type: 'text', languages: [outLang] }],
          },
          promptOptions: { responseConstraint: DEVICE_SCHEMA },
        },
      }));
    }
    return models.get(key);
  }

  /** Must run from a click: it may start the Gemini Nano download. */
  async function prepareDevice(lang, onProgress) {
    if (!ai || !hasPromptApi()) return false;
    try {
      await model(InferenceMode.PREFER_ON_DEVICE, lang).initializeDeviceModel(onProgress);
      return true;
    } catch {
      return false;
    }
  }

  /**
   * Checks one message. Returns the verdict, where the AI ran and the routing trace.
   * confirmCloud(reason) is asked before an unredactable screenshot may leave the device.
   */
  async function analyze({ text = '', source = '', image = null, mode = 'smart', lang = 'en', confirmCloud = async () => false }) {
    const started = performance.now();
    const trace = [];
    const rawText = String(text).trim();
    const rules = scoreRules(`${rawText}\n${source}`, /^https?:/i.test(source) ? source : '', settings.extraRules);
    trace.push(`Local rules: ${rules.hits.length} warning sign(s), score ${rules.score}. Ran on this device.`);

    const rulesOnly = (why) => {
      trace.push(why);
      return finish({
        verdict: rules.verdict, risk: rules.score, scamType: guessScamType(rules.hits),
        summary: rules.hits.length ? 'Veye found warning signs with its offline rules. Treat this message with care.' : 'Veye\'s offline rules found no common warning signs. That does not prove it is safe.',
        redFlags: [], nextSteps: rules.verdict === 'safe' ? [] : ['Do not reply or click links.', 'Contact the company with a number you already trust.'],
      }, 'rules_only', 0, image);
    };

    const finish = (ans, src, redactedCount, img, sentBytes = 0) => {
      // The AI decides; the rules can raise the alarm but never hide a warning.
      // When the AI already explains its own flags, the rule flags stay in the trace instead of repeating them.
      const ruleFlags = src !== 'rules_only' && ans.redFlags.length >= 2
        ? []
        : rules.hits.map((h) => h.flag).filter((f) => !ans.redFlags.some((a) => a.toLowerCase() === f.toLowerCase()));
      if (!ruleFlags.length && rules.hits.length) trace.push(`Rule flags: ${rules.hits.map((h) => h.flag).join('; ')}.`);
      let verdict = ans.verdict;
      if (verdict === 'safe' && rules.score >= 55) {
        verdict = 'suspicious';
        trace.push('The AI said safe, but the rules found strong warning signs, so Veye shows "suspicious".');
      }
      return {
        ...ans,
        verdict,
        risk: Math.max(ans.risk, Math.round(rules.score * 0.85)),
        scamType: ans.scamType === 'none' && verdict !== 'safe' ? guessScamType(rules.hits) : ans.scamType,
        ruleFlags,
        source: src,
        mode,
        lang,
        ms: Math.round(performance.now() - started),
        redactedCount,
        hadImage: !!img,
        sentBytes,
        trace,
        hosts: rules.hosts,
      };
    };

    if (!rawText && !image) throw new Error('Paste a message or add a screenshot first.');
    if (!ai) return rulesOnly(initError ? `AI is unavailable (${initError}).` : 'AI is not set up yet on this server (Firebase keys missing), so only the rules ran.');

    const online = navigator.onLine !== false;
    const status = await deviceStatus(lang, !!image);
    const devReady = status === 'available' || status === 'downloadable' || status === 'downloading';
    trace.push(`Gemini Nano on this device: ${status}. Network: ${online ? 'online' : 'offline'}.`);

    let inferMode;
    let sendRedacted = true;
    let img = image;
    const langOnDevice = settings.deviceLanguages.includes(lang);

    if (mode === 'private') {
      if (!devReady) return rulesOnly('Private mode never uses the cloud, and this device has no local model, so only the rules ran.');
      inferMode = InferenceMode.ONLY_ON_DEVICE;
      sendRedacted = false;
      trace.push('Private mode: ONLY_ON_DEVICE. The original text stays on this device.');
      if (!langOnDevice) trace.push(`Gemini Nano does not answer in ${LANG_NAMES[lang]} yet, so the answer is in English.`);
    } else if (mode === 'deep') {
      inferMode = InferenceMode.PREFER_IN_CLOUD;
      trace.push(`Deep check: PREFER_IN_CLOUD with ${settings.cloudModel}. Falls back to Gemini Nano when offline.`);
    } else if (!langOnDevice) {
      inferMode = InferenceMode.PREFER_IN_CLOUD;
      trace.push(`Gemini Nano does not answer in ${LANG_NAMES[lang]} yet, so Veye asks the cloud, with redacted text.`);
    } else if (rawText.length > DEVICE_TEXT_LIMIT) {
      inferMode = InferenceMode.PREFER_IN_CLOUD;
      trace.push(`Long content (${rawText.length} characters) is beyond what Gemini Nano reads well, so Veye asks the cloud, with redacted text.`);
    } else {
      inferMode = InferenceMode.PREFER_ON_DEVICE;
      trace.push(devReady
        ? 'Smart mode: PREFER_ON_DEVICE. Gemini Nano answers here; the cloud is the fallback.'
        : 'Smart mode: no local model on this device, so the SDK falls back to the cloud, with redacted text.');
    }

    const goesToCloud = inferMode === InferenceMode.PREFER_IN_CLOUD || (inferMode === InferenceMode.PREFER_ON_DEVICE && !devReady);
    if (goesToCloud && !online) {
      if (devReady) {
        inferMode = InferenceMode.ONLY_ON_DEVICE;
        trace.push('Offline: switched to Gemini Nano on this device.');
      } else {
        return rulesOnly('Offline and no local model: only the rules ran.');
      }
    }
    if (img && goesToCloud && online && inferMode !== InferenceMode.ONLY_ON_DEVICE) {
      const ok = await confirmCloud('The screenshot cannot be redacted. Send it to the cloud for this check?');
      if (!ok) {
        img = null;
        trace.push('You kept the screenshot on this device. Only redacted text was checked.');
        if (!rawText) return rulesOnly('Nothing left to check without the screenshot.');
      } else {
        trace.push('You allowed the screenshot to go to the cloud for this check.');
      }
    }

    let promptText = rawText;
    let redactedCount = 0;
    if (sendRedacted) {
      const r = redact(rawText);
      promptText = r.text;
      redactedCount = r.count;
      trace.push(`Redacted ${r.count} personal item(s) on this device before any cloud call.`);
    }
    promptText = promptText.slice(0, inferMode === InferenceMode.ONLY_ON_DEVICE || inferMode === InferenceMode.PREFER_ON_DEVICE ? DEVICE_TEXT_LIMIT : CLOUD_TEXT_LIMIT);
    const safeSource = sendRedacted ? redact(source).text : source;

    const outLang = inferMode === InferenceMode.PREFER_IN_CLOUD ? lang : (langOnDevice ? lang : 'en');
    const prompt = buildPrompt({ text: promptText, source: safeSource, lang: outLang, ruleHits: rules.hits, hasImage: !!img, redacted: sendRedacted && redactedCount > 0 });
    const parts = img ? [prompt, { inlineData: { mimeType: img.mimeType, data: img.data } }] : prompt;

    const run = async (m) => {
      const res = await withTimeout(model(m, outLang).generateContent(parts), AI_TIMEOUT_MS, 'The AI');
      return { ans: parseAnswer(res.response.text()), src: res.response.inferenceSource === InferenceSource.ON_DEVICE ? 'on_device' : 'in_cloud' };
    };

    let out;
    try {
      out = await run(inferMode);
    } catch (e) {
      trace.push(`First try failed: ${e?.message || e}.`);
      if (sendRedacted && online && inferMode !== InferenceMode.ONLY_IN_CLOUD) {
        try {
          out = await run(InferenceMode.ONLY_IN_CLOUD);
          trace.push('Retried in the cloud with the same redacted text.');
        } catch (e2) {
          return rulesOnly(`The cloud did not answer either (${e2?.message || e2}). Only the rules ran.`);
        }
      } else {
        return rulesOnly('Only the rules ran.');
      }
    }
    const sentBytes = out.src === 'in_cloud' ? new Blob([prompt]).size + (img ? Math.round(img.data.length * 0.75) : 0) : 0;
    trace.push(out.src === 'on_device'
      ? 'Answered by Gemini Nano on this device. 0 bytes of your content were sent.'
      : `Answered by ${settings.cloudModel} in the cloud via Firebase AI Logic (about ${Math.max(1, Math.round(sentBytes / 1024))} KB sent).`);
    return finish(out.ans, out.src, redactedCount, img, sentBytes);
  }

  return { analyze, deviceStatus, prepareDevice, settings, ready: () => !!ai, MODE_LABEL };
}

/** Reads a PNG or JPEG File into the inline-data shape the SDK expects. */
export async function fileToImage(file) {
  if (!file || !/^image\/(png|jpeg)$/.test(file.type)) throw new Error('Use a PNG or JPEG screenshot.');
  if (file.size > 6 * 1024 * 1024) throw new Error('That image is over 6 MB.');
  const buf = new Uint8Array(await file.arrayBuffer());
  let bin = '';
  for (let i = 0; i < buf.length; i += 0x8000) bin += String.fromCharCode(...buf.subarray(i, i + 0x8000));
  return { mimeType: file.type, data: btoa(bin) };
}
