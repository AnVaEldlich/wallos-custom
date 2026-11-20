(function () {
  const root = document.getElementById("diamon-minibot");
  if (!root) {
    return;
  }

  const fab = document.getElementById("diamon-minibot-fab");
  const panel = document.getElementById("diamon-minibot-panel");
  const closeButton = document.getElementById("diamon-minibot-close");
  const clearButton = document.getElementById("diamon-minibot-clear");
  const messages = document.getElementById("diamon-minibot-messages");
  const form = document.getElementById("diamon-minibot-form");
  const input = document.getElementById("diamon-minibot-input");
  const greeting = document.getElementById("diamon-minibot-greeting");
  const avatarTemplate = document.getElementById("diamon-minibot-avatar-template");
  const aiReady = root.dataset.aiReady === "1";
  const userKey = root.dataset.userId || "0";
  const storageKey = "diamon-minibot-" + userKey;
  const greetedKey = "diamon-minibot-greeted-" + userKey;
  const maxStored = 12;
  const reducedMotion = window.matchMedia
    ? window.matchMedia("(prefers-reduced-motion: reduce)")
    : { matches: false };

  // Dia's mood: every mascot marked .dia-mood (the button and the panel
  // header) shows it. "idle" is the resting mood; others can be temporary.
  const restingMood = aiReady ? "idle" : "sleeping";
  let moodTimer = null;
  let busy = false;

  function setMood(mood, durationMs) {
    clearTimeout(moodTimer);
    root.querySelectorAll(".dia-mood").forEach((mascot) => {
      mascot.dataset.diaState = mood;
    });
    if (durationMs) {
      moodTimer = setTimeout(() => setMood(currentRestingMood()), durationMs);
    }
  }

  function currentRestingMood() {
    if (busy) {
      return "thinking";
    }
    if (input && document.activeElement === input && input.value.trim() !== "") {
      return "listening";
    }
    return restingMood;
  }

  function loadHistory() {
    try {
      const raw = sessionStorage.getItem(storageKey);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed.slice(-maxStored) : [];
    } catch (error) {
      return [];
    }
  }

  function saveHistory(history) {
    try {
      sessionStorage.setItem(storageKey, JSON.stringify(history.slice(-maxStored)));
    } catch (error) {
      // Ignore quota errors.
    }
  }

  function makeAvatar(mood) {
    if (!avatarTemplate || !avatarTemplate.content.firstElementChild) {
      return null;
    }
    const avatar = avatarTemplate.content.firstElementChild.cloneNode(true);
    avatar.dataset.diaState = mood || "idle";
    return avatar;
  }

  // Assistant messages sit beside a small Dia; user messages stand alone.
  function appendBubble(role, text, extraClass, mood) {
    const bubble = document.createElement("div");
    bubble.className = "diamon-minibot-bubble " + role + (extraClass ? " " + extraClass : "");
    bubble.textContent = text;

    let node = bubble;
    if (role === "assistant") {
      node = document.createElement("div");
      node.className = "diamon-minibot-row";
      const avatar = makeAvatar(mood);
      if (avatar) {
        node.appendChild(avatar);
      }
      node.appendChild(bubble);
    }

    messages.appendChild(node);
    messages.scrollTop = messages.scrollHeight;
    return node;
  }

  // A fresh reply's avatar smiles for a moment, then settles and stops moving.
  function settleAvatar(row, mood, delayMs) {
    const avatar = row && row.querySelector(".dia-mascot");
    if (!avatar) {
      return;
    }
    avatar.dataset.diaState = mood;
    avatar.classList.remove("dia-static");
    setTimeout(() => {
      avatar.dataset.diaState = "idle";
      avatar.classList.add("dia-static");
    }, delayMs);
  }

  function renderHistory() {
    messages.innerHTML = "";
    const history = loadHistory();
    if (history.length === 0) {
      // With AI off, the panel already explains why below the messages.
      appendBubble("assistant", root.dataset.welcome || "", "", restingMood);
      return;
    }
    history.forEach((turn) => {
      appendBubble(turn.role === "assistant" ? "assistant" : "user", turn.content);
    });
  }

  function hideGreeting() {
    if (greeting) {
      greeting.hidden = true;
    }
  }

  function setOpen(open) {
    root.classList.toggle("is-open", open);
    fab.setAttribute("aria-expanded", open ? "true" : "false");
    fab.setAttribute("aria-label", open ? fab.dataset.labelClose : fab.dataset.labelOpen);
    hideGreeting();
    resetLook();
    if (open) {
      panel.hidden = false;
      renderHistory();
      if (input && !input.disabled) {
        input.focus();
      }
    } else {
      panel.hidden = true;
    }
  }

  fab.addEventListener("click", () => {
    setOpen(panel.hidden);
  });

  closeButton.addEventListener("click", () => {
    setOpen(false);
    fab.focus();
  });

  if (clearButton) {
    clearButton.addEventListener("click", () => {
      saveHistory([]);
      renderHistory();
      if (input) {
        input.value = "";
        input.focus();
      }
    });
  }

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !panel.hidden) {
      setOpen(false);
      fab.focus();
    }
  });

  // The button's Dia follows the pointer with its eye while the panel is closed.
  const followers = root.querySelectorAll(".dia-follows");
  let lookFrame = 0;
  let lastPointer = null;

  function resetLook() {
    followers.forEach((mascot) => {
      mascot.style.removeProperty("--dia-look-x");
      mascot.style.removeProperty("--dia-look-y");
    });
  }

  function updateLook() {
    lookFrame = 0;
    if (!lastPointer || !panel.hidden || reducedMotion.matches) {
      return;
    }
    followers.forEach((mascot) => {
      const box = mascot.getBoundingClientRect();
      const dx = lastPointer.x - (box.left + box.width / 2);
      const dy = lastPointer.y - (box.top + box.height * 0.6);
      const distance = Math.hypot(dx, dy);
      if (distance < 1) {
        resetLook();
        return;
      }
      const reach = Math.min(1, distance / 260);
      mascot.style.setProperty("--dia-look-x", ((dx / distance) * reach * 58).toFixed(1) + "px");
      mascot.style.setProperty("--dia-look-y", ((dy / distance) * reach * 66).toFixed(1) + "px");
    });
  }

  if (followers.length > 0 && aiReady) {
    document.addEventListener(
      "pointermove",
      (event) => {
        if (event.pointerType && event.pointerType !== "mouse") {
          return;
        }
        lastPointer = { x: event.clientX, y: event.clientY };
        if (!lookFrame) {
          lookFrame = requestAnimationFrame(updateLook);
        }
      },
      { passive: true }
    );
    document.addEventListener("pointerleave", () => {
      lastPointer = null;
      resetLook();
    });
  }

  // A short greeting, once per browser session, while nothing has been asked yet.
  if (greeting) {
    greeting.addEventListener("click", () => setOpen(true));
    let alreadyGreeted = false;
    try {
      alreadyGreeted = sessionStorage.getItem(greetedKey) === "1";
      sessionStorage.setItem(greetedKey, "1");
    } catch (error) {
      alreadyGreeted = true;
    }
    if (!alreadyGreeted && loadHistory().length === 0) {
      setTimeout(() => {
        if (panel.hidden) {
          greeting.hidden = false;
          setMood("happy", 1600);
          setTimeout(hideGreeting, 7000);
        }
      }, 1200);
    }
  }

  if (!form || !input) {
    return;
  }

  input.addEventListener("input", () => {
    if (!busy) {
      setMood(input.value.trim() !== "" ? "listening" : restingMood);
    }
  });
  input.addEventListener("blur", () => {
    if (!busy) {
      setMood(restingMood);
    }
  });

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const text = input.value.trim();
    if (!text || input.disabled) {
      return;
    }

    const history = loadHistory();
    history.push({ role: "user", content: text });
    saveHistory(history);
    appendBubble("user", text);
    input.value = "";
    input.disabled = true;
    form.querySelector(".diamon-minibot-send").disabled = true;
    busy = true;
    setMood("thinking");

    const thinking = appendBubble("assistant", root.dataset.thinking || "…", "thinking", "thinking");
    const thinkingAvatar = thinking.querySelector(".dia-mascot");
    if (thinkingAvatar) {
      thinkingAvatar.classList.remove("dia-static");
    }

    let outcome = "confused";

    fetch("endpoints/ai/chat.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": window.csrfToken,
      },
      body: JSON.stringify({
        message: text,
        history: history.slice(0, -1).slice(-6),
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        thinking.remove();
        if (data && data.success && data.reply) {
          const next = loadHistory();
          next.push({ role: "assistant", content: data.reply });
          saveHistory(next);
          settleAvatar(appendBubble("assistant", data.reply), "happy", 2400);
          outcome = "happy";
        } else {
          const row = appendBubble("assistant", (data && data.message) || root.dataset.error || "");
          settleAvatar(row, "confused", 3000);
        }
      })
      .catch(() => {
        thinking.remove();
        settleAvatar(appendBubble("assistant", root.dataset.error || ""), "confused", 3000);
      })
      .finally(() => {
        busy = false;
        setMood(outcome, outcome === "happy" ? 2400 : 3000);
        input.disabled = false;
        form.querySelector(".diamon-minibot-send").disabled = false;
        input.focus();
      });
  });
})();
