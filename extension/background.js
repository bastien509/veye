// Veye service worker: toolbar badge from local rules, side panel, context menu.
// It never sends page content anywhere.

chrome.runtime.onInstalled.addListener(() => {
  chrome.sidePanel.setPanelBehavior({ openPanelOnActionClick: true }).catch(() => {});
  chrome.contextMenus.create({ id: 'veye-check', title: 'Check with Veye', contexts: ['selection'] });
});

const BADGE = {
  scam: { text: '!', color: '#D21034' },
  suspicious: { text: '?', color: '#b26a00' },
};

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  if (msg?.type === 'veye-rules' && sender.tab?.id !== undefined) {
    const b = BADGE[msg.verdict];
    const tabId = sender.tab.id;
    chrome.action.setBadgeText({ tabId, text: b ? b.text : '' });
    if (b) chrome.action.setBadgeBackgroundColor({ tabId, color: b.color });
    chrome.action.setTitle({
      tabId,
      title: b ? `Veye: ${msg.verdict} (${msg.score}) – ${msg.flags.slice(0, 2).join('; ')}` : 'Veye – check this page',
    });
    chrome.storage.session.set({ [`rules-${tabId}`]: msg }).catch(() => {});
  }
  return false;
});

chrome.contextMenus.onClicked.addListener(async (info, tab) => {
  if (info.menuItemId !== 'veye-check' || !tab?.id) return;
  // Opening the panel must happen inside this click.
  chrome.sidePanel.open({ tabId: tab.id }).catch(() => {});
  await chrome.storage.session.set({ pending: { text: String(info.selectionText || '').slice(0, 20000), source: tab.url || '', at: Date.now() } });
});

chrome.tabs.onRemoved.addListener((tabId) => {
  chrome.storage.session.remove(`rules-${tabId}`).catch(() => {});
});
