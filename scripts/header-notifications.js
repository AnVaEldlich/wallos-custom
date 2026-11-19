function inAppNotificationText(template, values) {
  return String(template || "").replace(/\{(\w+)\}/g, function (_, key) {
    return Object.prototype.hasOwnProperty.call(values, key) ? String(values[key]) : "";
  });
}

function formatUnusedEndingBody(notification, strings) {
  const days = Number(notification.days_left || 0);
  if (days <= 0) {
    return strings.unused_body_today || "";
  }
  return inAppNotificationText(strings.unused_body, { days: days });
}

function subscriptionPrompt(key, fallback) {
  if (typeof translate !== "function") {
    return fallback;
  }
  const value = translate(key);
  return value === "[Translation Missing]" ? fallback : value;
}

function createSubscriptionCancelDialog() {
  let dialog = document.getElementById("subscription-cancel-confirmation");
  if (dialog) {
    return dialog;
  }

  const strings = window.inAppNotificationStrings || {};
  dialog = document.createElement("dialog");
  dialog.id = "subscription-cancel-confirmation";
  dialog.className = "subscription-cancel-dialog";
  dialog.setAttribute("aria-labelledby", "subscription-cancel-title");
  dialog.setAttribute("aria-describedby", "subscription-cancel-message");

  const content = document.createElement("div");
  content.className = "subscription-cancel-content";

  const icon = document.createElement("span");
  icon.className = "subscription-cancel-icon";
  icon.setAttribute("aria-hidden", "true");
  icon.innerHTML = '<i class="fa-solid fa-ban"></i>';

  const title = document.createElement("h2");
  title.id = "subscription-cancel-title";
  title.textContent = strings.cancelSubscription || "Cancel subscription";

  const message = document.createElement("p");
  message.id = "subscription-cancel-message";
  message.textContent = subscriptionPrompt(
    "confirm_cancel_subscription",
    "Cancel this subscription? It stays saved so you can resume it later."
  );

  const actions = document.createElement("div");
  actions.className = "subscription-cancel-actions";

  const keepButton = document.createElement("button");
  keepButton.type = "button";
  keepButton.className = "button secondary-button";
  keepButton.textContent = strings.keepSubscription || "Keep subscription";

  const confirmButton = document.createElement("button");
  confirmButton.type = "button";
  confirmButton.className = "button subscription-cancel-confirm";
  confirmButton.textContent = strings.cancelSubscription || "Cancel subscription";

  actions.append(keepButton, confirmButton);
  content.append(icon, title, message, actions);
  dialog.appendChild(content);
  document.body.appendChild(dialog);

  keepButton.addEventListener("click", () => dialog.close("keep"));
  confirmButton.addEventListener("click", () => dialog.close("confirm"));
  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) {
      dialog.close("keep");
    }
  });

  return dialog;
}

let subscriptionCancellationPromise = null;

function confirmSubscriptionCancellation() {
  if (subscriptionCancellationPromise) {
    return subscriptionCancellationPromise;
  }

  const dialog = createSubscriptionCancelDialog();
  subscriptionCancellationPromise = new Promise((resolve) => {
    dialog.returnValue = "";
    dialog.addEventListener("close", () => {
      subscriptionCancellationPromise = null;
      resolve(dialog.returnValue === "confirm");
    }, { once: true });
    dialog.showModal();
    dialog.querySelector(".subscription-cancel-actions button").focus();
  });
  return subscriptionCancellationPromise;
}

async function setSubscriptionInactive(event, id, inactive) {
  if (event) {
    event.stopPropagation();
    event.preventDefault();
  }

  if (inactive && !(await confirmSubscriptionCancellation())) {
    return;
  }

  fetch("endpoints/subscription/set_state.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": window.csrfToken,
    },
    body: JSON.stringify({ id: id, inactive: inactive ? 1 : 0 }),
  })
    .then(function (response) {
      return response.json();
    })
    .then(function (data) {
      if (!data || !data.success) {
        if (typeof showErrorMessage === "function") {
          showErrorMessage((data && data.message) || subscriptionPrompt("error", "Error"));
        }
        return;
      }
      if (typeof showSuccessMessage === "function") {
        showSuccessMessage(data.message);
      }
      if (typeof loadInAppNotifications === "function") {
        loadInAppNotifications();
      }
      if (typeof closeSubscriptionDetails === "function") {
        closeSubscriptionDetails();
      }
      if (typeof fetchSubscriptions === "function") {
        fetchSubscriptions(null, null, inactive ? "cancel" : "resume");
      }
    })
    .catch(function () {
      if (typeof showErrorMessage === "function") {
        showErrorMessage(subscriptionPrompt("unknown_error", "Unknown error"));
      }
    });
}

// ---------------------------------------------------------------------------
// Assisted cancellation. "Cancel" sends the user to the service's own cancel
// page and then asks, in a dialog that cannot be dismissed, whether the
// cancellation went through. When the Diamon browser extension sees the
// service's confirmation page, the server records it and the dialog closes by
// itself. The attempt lives on the server, so reloading the page brings the
// question back until it is answered.

const CANCELLATION_STATUS_POLL_MS = 3000;

let cancellationOutcome = null;

function cancellationStrings() {
  return window.inAppNotificationStrings || {};
}

function postCancellationJson(path, body) {
  return fetch(path, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": window.csrfToken,
    },
    body: JSON.stringify(body || {}),
  }).then(function (response) {
    return response.json();
  });
}

function refreshAfterCancellation() {
  if (typeof loadInAppNotifications === "function") {
    loadInAppNotifications();
  }
  if (typeof closeSubscriptionDetails === "function") {
    closeSubscriptionDetails();
  }
  if (typeof fetchSubscriptions === "function") {
    fetchSubscriptions(null, null, "cancel");
  } else {
    // Pages without the subscription list (dashboard, stats) show totals that
    // just changed.
    window.location.reload();
  }
}

function createCancellationOutcomeDialog() {
  let dialog = document.getElementById("subscription-cancel-outcome");
  if (dialog) {
    return dialog;
  }

  const strings = cancellationStrings();
  dialog = document.createElement("dialog");
  dialog.id = "subscription-cancel-outcome";
  dialog.className = "subscription-cancel-dialog subscription-cancel-outcome";
  dialog.setAttribute("aria-labelledby", "subscription-cancel-outcome-title");
  dialog.setAttribute("aria-describedby", "subscription-cancel-outcome-message");

  dialog.innerHTML =
    '<div class="subscription-cancel-content">' +
    '<span class="subscription-cancel-icon" aria-hidden="true"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>' +
    '<h2 id="subscription-cancel-outcome-title"></h2>' +
    '<p id="subscription-cancel-outcome-message"></p>' +
    '<p class="subscription-cancel-outcome-waiting" hidden><i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i> <span></span></p>' +
    '<button type="button" class="subscription-cancel-reopen" hidden></button>' +
    '<div class="subscription-cancel-actions">' +
    '<button type="button" class="button secondary-button" data-outcome="kept"></button>' +
    '<button type="button" class="button subscription-cancel-confirm" data-outcome="cancelled"></button>' +
    "</div>" +
    "</div>";

  dialog.querySelector('[data-outcome="kept"]').textContent = strings.cancelOutcomeNotCancelled || "Not cancelled";
  dialog.querySelector('[data-outcome="cancelled"]').textContent = strings.cancelOutcomeCancelled || "Cancelled";
  dialog.querySelector(".subscription-cancel-reopen").textContent = strings.cancelOutcomeReopen || "Open the cancellation page again";
  dialog.querySelector(".subscription-cancel-outcome-waiting span").textContent =
    strings.cancelOutcomeWaiting || "Waiting for the service to confirm…";

  // The question must be answered: Escape and clicks on the backdrop do
  // nothing, and if the browser closes the dialog anyway it reopens.
  dialog.addEventListener("cancel", function (event) {
    event.preventDefault();
  });
  dialog.addEventListener("close", function () {
    if (cancellationOutcome && !cancellationOutcome.finished) {
      dialog.showModal();
    }
  });
  dialog.querySelector('[data-outcome="kept"]').addEventListener("click", function () {
    answerCancellationOutcome(false);
  });
  dialog.querySelector('[data-outcome="cancelled"]').addEventListener("click", function () {
    answerCancellationOutcome(true);
  });
  dialog.querySelector(".subscription-cancel-reopen").addEventListener("click", function () {
    if (cancellationOutcome && cancellationOutcome.cancelUrl) {
      openCancellationPage(cancellationOutcome.cancelUrl, null);
    }
  });

  document.body.appendChild(dialog);
  return dialog;
}

function openCancellationPage(url, preopened) {
  const tab = preopened || window.open("", "_blank");
  if (!tab) {
    return false;
  }
  tab.opener = null;
  tab.location.href = url;
  return true;
}

function showCancellationOutcome(attempt) {
  const strings = cancellationStrings();
  const dialog = createCancellationOutcomeDialog();

  stopCancellationOutcome();
  cancellationOutcome = {
    attemptId: attempt.attempt_id,
    cancelUrl: attempt.cancel_url || null,
    detectable: !!attempt.detectable,
    finished: false,
    busy: false,
    timer: null,
  };

  const name = attempt.subscription_name || "";
  dialog.querySelector("#subscription-cancel-outcome-title").textContent = inAppNotificationText(
    strings.cancelOutcomeTitle || "Did you cancel {name}?",
    { name: name }
  );
  let message;
  if (!cancellationOutcome.cancelUrl) {
    message = strings.cancelOutcomeNoUrl || "Diamon has no cancellation page for this service. Cancel it with the service, then tell us how it went.";
  } else if (cancellationOutcome.detectable) {
    message = strings.cancelOutcomeDetectable || "Finish cancelling in the tab that opened. If the Diamon extension is installed, this closes by itself once the service confirms; otherwise, tell us how it went.";
  } else {
    message = strings.cancelOutcomeManual || "Finish cancelling in the tab that opened, then tell us how it went.";
  }
  dialog.querySelector("#subscription-cancel-outcome-message").textContent = message;
  dialog.querySelector(".subscription-cancel-outcome-waiting").hidden = !cancellationOutcome.detectable;
  dialog.querySelector(".subscription-cancel-reopen").hidden = !cancellationOutcome.cancelUrl;
  dialog.querySelectorAll(".subscription-cancel-actions button").forEach(function (button) {
    button.disabled = false;
  });

  if (!dialog.open) {
    dialog.showModal();
  }
  dialog.querySelector('[data-outcome="cancelled"]').focus();

  if (cancellationOutcome.detectable) {
    cancellationOutcome.timer = setInterval(checkCancellationStatus, CANCELLATION_STATUS_POLL_MS);
  }
}

function stopCancellationOutcome() {
  if (cancellationOutcome && cancellationOutcome.timer) {
    clearInterval(cancellationOutcome.timer);
  }
}

function finishCancellationOutcome(cancelled, message) {
  if (!cancellationOutcome || cancellationOutcome.finished) {
    return;
  }
  cancellationOutcome.finished = true;
  stopCancellationOutcome();
  const dialog = document.getElementById("subscription-cancel-outcome");
  if (dialog && dialog.open) {
    dialog.close();
  }
  if (message && typeof showSuccessMessage === "function") {
    showSuccessMessage(message);
  }
  if (cancelled) {
    refreshAfterCancellation();
  }
}

function checkCancellationStatus() {
  if (!cancellationOutcome || cancellationOutcome.finished || cancellationOutcome.busy) {
    return;
  }
  const attemptId = cancellationOutcome.attemptId;
  postCancellationJson("endpoints/subscription/cancellation_status.php", { attempt_id: attemptId })
    .then(function (data) {
      if (!data || !data.success || !cancellationOutcome || cancellationOutcome.attemptId !== attemptId) {
        return;
      }
      if (data.status === "detected" || data.status === "confirmed") {
        finishCancellationOutcome(true, cancellationStrings().cancelDetected || "The service confirmed the cancellation.");
      } else if (data.status !== "pending") {
        // Answered in another tab, or superseded.
        finishCancellationOutcome(false, null);
      }
    })
    .catch(function () {});
}

function answerCancellationOutcome(cancelled) {
  if (!cancellationOutcome || cancellationOutcome.finished || cancellationOutcome.busy) {
    return;
  }
  cancellationOutcome.busy = true;
  const dialog = document.getElementById("subscription-cancel-outcome");
  dialog.querySelectorAll(".subscription-cancel-actions button").forEach(function (button) {
    button.disabled = true;
  });

  postCancellationJson("endpoints/subscription/resolve_cancellation.php", {
    attempt_id: cancellationOutcome.attemptId,
    cancelled: cancelled,
  })
    .then(function (data) {
      cancellationOutcome.busy = false;
      if (!data || !data.success) {
        throw new Error((data && data.message) || "error");
      }
      finishCancellationOutcome(!!data.cancelled, data.message);
    })
    .catch(function (error) {
      if (cancellationOutcome) {
        cancellationOutcome.busy = false;
      }
      dialog.querySelectorAll(".subscription-cancel-actions button").forEach(function (button) {
        button.disabled = false;
      });
      if (typeof showErrorMessage === "function") {
        showErrorMessage(error && error.message !== "error" ? error.message : subscriptionPrompt("unknown_error", "Unknown error"));
      }
    });
}

async function cancelSubscription(event, id) {
  if (event) {
    event.stopPropagation();
    event.preventDefault();
  }

  if (!(await confirmSubscriptionCancellation())) {
    return;
  }

  // Open the tab while the click still counts as a user gesture, so popup
  // blockers allow it; it is pointed at the cancel page once the server
  // answers, or closed when there is no page to open.
  const preopened = window.open("", "_blank");

  postCancellationJson("endpoints/subscription/start_cancellation.php", { id: id })
    .then(function (data) {
      if (!data || !data.success) {
        throw new Error((data && data.message) || "error");
      }
      if (data.cancel_url) {
        openCancellationPage(data.cancel_url, preopened);
      } else if (preopened) {
        preopened.close();
      }
      showCancellationOutcome(data);
    })
    .catch(function (error) {
      if (preopened) {
        preopened.close();
      }
      if (typeof showErrorMessage === "function") {
        showErrorMessage(error && error.message !== "error" ? error.message : subscriptionPrompt("unknown_error", "Unknown error"));
      }
    });
}

function resumePendingCancellation() {
  if (!window.csrfToken) {
    return;
  }
  postCancellationJson("endpoints/subscription/get_pending_cancellation.php", {})
    .then(function (data) {
      if (data && data.success && data.pending) {
        showCancellationOutcome(data.pending);
      }
    })
    .catch(function () {});
}

document.addEventListener("DOMContentLoaded", resumePendingCancellation);

// Coming back from the service's tab: check at once instead of waiting for
// the next poll.
document.addEventListener("visibilitychange", function () {
  if (document.visibilityState === "visible" && cancellationOutcome && cancellationOutcome.detectable) {
    checkCancellationStatus();
  }
});

function resumeSubscription(event, id) {
  setSubscriptionInactive(event, id, false);
}

function notificationsUseHover() {
  return window.matchMedia("(min-width: 769px)").matches;
}

function renderInAppNotifications(notifications) {
  const list = document.getElementById("header-notifications-list");
  const badge = document.getElementById("header-notifications-badge");
  const strings = window.inAppNotificationStrings || {};
  if (!list || !badge) {
    return;
  }

  const items = Array.isArray(notifications) ? notifications : [];
  badge.textContent = String(items.length);
  badge.classList.toggle("hide", items.length === 0);
  badge.setAttribute("aria-hidden", items.length === 0 ? "true" : "false");

  list.innerHTML = "";
  if (items.length === 0) {
    const empty = document.createElement("p");
    empty.className = "header-notifications-empty";
    empty.textContent = strings.empty || "";
    list.appendChild(empty);
    return;
  }

  items.forEach(function (notification) {
    const card = document.createElement("article");
    card.className = "header-notification-item";
    card.dataset.subscriptionId = String(notification.id);

    const title = document.createElement("h3");
    title.textContent = notification.name || "";
    card.appendChild(title);

    const body = document.createElement("p");
    body.className = "header-notification-body";
    body.textContent = formatUnusedEndingBody(notification, strings);
    card.appendChild(body);

    const action = document.createElement("button");
    action.type = "button";
    action.className = "header-notification-action";
    action.textContent = strings.review || "";
    action.addEventListener("click", function (event) {
      event.preventDefault();
      event.stopPropagation();
      cancelSubscription(event, notification.id);
    });
    card.appendChild(action);

    list.appendChild(card);
  });
}

function setHeaderNotificationsOpen(isOpen) {
  const root = document.getElementById("header-notifications");
  const toggle = document.getElementById("header-notifications-toggle");
  const panel = document.getElementById("header-notifications-panel");
  if (!root || !toggle || !panel) {
    return;
  }
  root.classList.toggle("is-open", isOpen);
  panel.classList.toggle("hide", !isOpen);
  toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
}

function loadInAppNotifications() {
  return fetch("endpoints/notifications/in_app.php", {
    method: "GET",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
    },
  })
    .then(function (response) {
      if (!response.ok) {
        throw new Error("failed_to_load_notifications");
      }
      return response.json();
    })
    .then(function (data) {
      if (!data || data.success === false) {
        renderInAppNotifications([]);
        return;
      }
      renderInAppNotifications(data.notifications || []);
    })
    .catch(function () {
      renderInAppNotifications([]);
    });
}

document.addEventListener("DOMContentLoaded", function () {
  const toggle = document.getElementById("header-notifications-toggle");
  const root = document.getElementById("header-notifications");
  if (!toggle || !root) {
    return;
  }

  loadInAppNotifications();

  toggle.addEventListener("click", function (event) {
    if (notificationsUseHover()) {
      return;
    }
    event.stopPropagation();
    const willOpen = !root.classList.contains("is-open");
    setHeaderNotificationsOpen(willOpen);
    if (willOpen) {
      loadInAppNotifications();
    }
  });

  root.addEventListener("mouseenter", function () {
    if (!notificationsUseHover()) {
      return;
    }
    toggle.setAttribute("aria-expanded", "true");
    loadInAppNotifications();
  });

  root.addEventListener("mouseleave", function () {
    if (!notificationsUseHover()) {
      return;
    }
    toggle.setAttribute("aria-expanded", "false");
  });

  document.addEventListener("click", function (event) {
    if (!root.classList.contains("is-open")) {
      return;
    }
    if (root.contains(event.target)) {
      return;
    }
    setHeaderNotificationsOpen(false);
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      setHeaderNotificationsOpen(false);
    }
  });
});
