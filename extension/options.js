const urlInput = document.querySelector("#wallosUrl");
const keyInput = document.querySelector("#apiKey");
const status = document.querySelector("#status");

chrome.storage.local.get({ wallosUrl: "", apiKey: "" }, (stored) => {
  urlInput.value = stored.wallosUrl;
  keyInput.value = stored.apiKey;
});

document.querySelector("#save").addEventListener("click", () => {
  chrome.storage.local.set({
    wallosUrl: urlInput.value.trim().replace(/\/$/, ""),
    apiKey: keyInput.value.trim(),
  }, () => {
    status.textContent = "Saved";
  });
});
