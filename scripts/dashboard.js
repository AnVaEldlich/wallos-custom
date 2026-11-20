document.addEventListener("DOMContentLoaded", function () {
  function updateAiRecommendationNumbers() {
    document.querySelectorAll(".ai-recommendation-item").forEach(function (item, index) {
      const numberSpan = item.querySelector(".ai-recommendation-header h3 > span");
      if (numberSpan) {
        numberSpan.textContent = `${index + 1}. `;
      }
    });
  }

  document.querySelectorAll(".ai-recommendation-item").forEach(function (item) {
    item.addEventListener("click", function () {
      item.classList.toggle("expanded");
    });
  });

  document.querySelectorAll(".upcoming-subscriptions > .dashboard-subscriptions-container").forEach(function (container) {
    enableUpcomingAutoScroll(container);
  });

  document.querySelectorAll(".delete-ai-recommendation").forEach(function (el) {
    el.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const item = el.closest(".ai-recommendation-item");
      const id = item.getAttribute("data-id");

      fetch("endpoints/ai/delete_recommendation.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": window.csrfToken,
        },
        body: JSON.stringify({ id: id }),
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            item.remove();
            updateAiRecommendationNumbers();
            showSuccessMessage(translate("success"));
          } else {
            showErrorMessage(data.message || translate("failed_delete_ai_recommendation"));
          }
        })
        .catch(error => {
          console.error(error);
          showErrorMessage(translate("unknown_error"));
        });
    });
  });

});

function enableCardRowDragScroll(container) {
  if (!container || container.dataset.dragScroll === "1") {
    return;
  }
  container.dataset.dragScroll = "1";

  let active = false;
  let dragged = false;
  let startX = 0;
  let startScroll = 0;
  let lastX = 0;
  let lastTime = 0;
  let velocity = 0;
  let inertiaFrame = 0;

  function canScroll() {
    return container.scrollWidth - container.clientWidth > 4;
  }

  function refreshCursor() {
    container.classList.toggle("is-draggable", canScroll());
  }

  container.addEventListener("pointerdown", function (event) {
    if (event.pointerType !== "mouse" || event.button !== 0 || !canScroll()) {
      return;
    }
    cancelAnimationFrame(inertiaFrame);
    active = true;
    dragged = false;
    startX = lastX = event.clientX;
    startScroll = container.scrollLeft;
    lastTime = performance.now();
    velocity = 0;
  });

  container.addEventListener("pointermove", function (event) {
    if (!active) {
      return;
    }
    const dx = event.clientX - startX;
    if (!dragged && Math.abs(dx) < 6) {
      return;
    }
    if (!dragged) {
      dragged = true;
      container.classList.add("is-grabbing");
      container.setPointerCapture(event.pointerId);
    }
    const now = performance.now();
    const dt = Math.max(now - lastTime, 1);
    velocity = (event.clientX - lastX) / dt;
    lastX = event.clientX;
    lastTime = now;
    container.scrollLeft = startScroll - dx;
  });

  function stop(event) {
    if (!active) {
      return;
    }
    active = false;
    container.classList.remove("is-grabbing");
    if (container.hasPointerCapture(event.pointerId)) {
      container.releasePointerCapture(event.pointerId);
    }
    if (!dragged) {
      return;
    }
    let speed = -velocity * 16;
    function coast() {
      if (Math.abs(speed) < 0.4) {
        return;
      }
      container.scrollLeft += speed;
      speed *= 0.92;
      inertiaFrame = requestAnimationFrame(coast);
    }
    inertiaFrame = requestAnimationFrame(coast);
  }

  container.addEventListener("pointerup", stop);
  container.addEventListener("pointercancel", stop);
  container.addEventListener("dragstart", function (event) {
    event.preventDefault();
  });
  container.addEventListener("click", function (event) {
    if (!dragged) {
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    dragged = false;
  }, true);

  refreshCursor();
  window.addEventListener("resize", refreshCursor);
  window.addEventListener("load", refreshCursor);
}

function enableUpcomingAutoScroll(container) {
  const list = container.querySelector(".dashboard-subscriptions-list");
  if (!list || container.dataset.upcomingAutoScroll === "1") {
    return;
  }

  const originals = Array.from(list.children).filter(function (node) {
    return node.classList && node.classList.contains("subscription-item");
  });
  if (originals.length < 2) {
    return;
  }

  // Two identical tracks: CSS moves the row by -50% for a seamless loop.
  originals.forEach(function (item) {
    const clone = item.cloneNode(true);
    clone.classList.add("is-carousel-clone");
    clone.setAttribute("aria-hidden", "true");
    clone.tabIndex = -1;
    list.appendChild(clone);
  });

  container.dataset.upcomingAutoScroll = "1";
  container.classList.add("is-upcoming-carousel");
  container.classList.add("is-draggable");

  // ~3.2s per card, floored so a long list still finishes a lap in good time.
  const durationSeconds = Math.max(16, Math.min(48, originals.length * 3.2));
  list.style.setProperty("--upcoming-carousel-duration", durationSeconds + "s");

  enableUpcomingCarouselDrag(container, list, durationSeconds);
}

function enableUpcomingCarouselDrag(container, list, durationSeconds) {
  let active = false;
  let dragging = false;
  let startX = 0;
  let originTx = 0;
  let currentTx = 0;

  function readTranslateX() {
    const transform = window.getComputedStyle(list).transform;
    if (!transform || transform === "none") {
      return 0;
    }
    try {
      return new DOMMatrixReadOnly(transform).m41;
    } catch (error) {
      const match = transform.match(/matrix\(([^)]+)\)/);
      if (!match) {
        return 0;
      }
      const parts = match[1].split(",");
      return parseFloat(parts[4]) || 0;
    }
  }

  function loopWidth() {
    return list.scrollWidth / 2;
  }

  function wrapTranslate(tx) {
    const half = loopWidth();
    if (half <= 0) {
      return tx;
    }
    let x = tx % half;
    if (x > 0) {
      x -= half;
    }
    if (x <= -half) {
      x += half;
    }
    return x;
  }

  function freezeAt(tx) {
    currentTx = wrapTranslate(tx);
    container.classList.add("is-dragging");
    list.style.animation = "none";
    list.style.transform = "translate3d(" + currentTx + "px, 0, 0)";
  }

  function resumeAutoplay(fromTx) {
    const half = loopWidth();
    const x = wrapTranslate(fromTx);
    const progress = half > 0 ? Math.min(Math.max((-x) / half, 0), 0.9999) : 0;
    const delay = -(progress * durationSeconds);

    container.classList.remove("is-dragging");
    container.classList.remove("is-grabbing");

    // Restart the CSS loop at the dragged offset with a negative delay so the
    // visible position stays put, then the automatic marquee continues.
    list.style.animation = "none";
    list.style.transform = "translate3d(" + x + "px, 0, 0)";
    void list.offsetWidth;
    list.style.removeProperty("transform");
    list.style.animation = "upcoming-carousel-marquee " + durationSeconds + "s linear infinite";
    list.style.animationDelay = delay.toFixed(4) + "s";
  }

  container.addEventListener("pointerdown", function (event) {
    if (event.button !== 0 && event.pointerType === "mouse") {
      return;
    }
    active = true;
    dragging = false;
    startX = event.clientX;
    originTx = readTranslateX();
    currentTx = originTx;
  });

  container.addEventListener("pointermove", function (event) {
    if (!active) {
      return;
    }
    const dx = event.clientX - startX;
    if (!dragging && Math.abs(dx) < 8) {
      return;
    }
    if (!dragging) {
      dragging = true;
      container.dataset.carouselSuppressClick = "1";
      freezeAt(originTx);
      container.classList.add("is-grabbing");
      try {
        container.setPointerCapture(event.pointerId);
      } catch (error) {
        // Some environments reject capture for non-primary synthetic pointers.
      }
    }
    event.preventDefault();
    currentTx = wrapTranslate(originTx + dx);
    list.style.transform = "translate3d(" + currentTx + "px, 0, 0)";
  }, { passive: false });

  function endDrag(event) {
    if (!active) {
      return;
    }
    const wasDragging = dragging;
    active = false;
    dragging = false;
    try {
      if (event && event.pointerId >= 0 && container.hasPointerCapture(event.pointerId)) {
        container.releasePointerCapture(event.pointerId);
      }
    } catch (error) {
      // Synthetic or already-released pointers can throw here; safe to ignore.
    }
    if (!wasDragging) {
      return;
    }
    resumeAutoplay(currentTx);
  }

  container.addEventListener("pointerup", endDrag);
  container.addEventListener("pointercancel", endDrag);
  container.addEventListener("lostpointercapture", function () {
    if (!active || !dragging) {
      return;
    }
    endDrag({ pointerId: -1 });
  });
  container.addEventListener("dragstart", function (event) {
    event.preventDefault();
  });
  container.addEventListener("click", function (event) {
    if (!container.classList.contains("is-dragging") && !container.dataset.carouselSuppressClick) {
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    delete container.dataset.carouselSuppressClick;
  }, true);
}

