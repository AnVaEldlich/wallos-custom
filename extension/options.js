const urlInput = document.querySelector("#diamonUrl");
const keyInput = document.querySelector("#apiKey");
const status = document.querySelector("#status");

// "wallosUrl" is where versions released before the rename to Diamon kept the address.
const LEGACY_URL_KEY = "wallosUrl";

chrome.storage.local.get({ diamonUrl: "", [LEGACY_URL_KEY]: "", apiKey: "" }, (stored) => {
  urlInput.value = stored.diamonUrl || stored[LEGACY_URL_KEY];
  keyInput.value = stored.apiKey;
});

document.querySelector("#save").addEventListener("click", () => {
  chrome.storage.local.set({
    diamonUrl: urlInput.value.trim().replace(/\/$/, ""),
    apiKey: keyInput.value.trim(),
  }, () => {
    chrome.storage.local.remove(LEGACY_URL_KEY);
    status.textContent = "Saved";
  });
});
