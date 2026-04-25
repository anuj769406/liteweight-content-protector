/* global LWCP_CONFIG */
(function () {
  "use strict";

  if (window.__LWCP_PROTECTION_ACTIVE__) {
    return;
  }

  window.__LWCP_PROTECTION_ACTIVE__ = true;

  const config = window.LWCP_CONFIG || {};
  let alertElement = null;
  let hideTimer = null;
  let lastAlertAt = 0;

  const needsKeydownHandler =
    !!config.disableRightClick ||
    !!config.disableCopy ||
    !!config.disableCtrlU ||
    !!config.disableCtrlS ||
    !!config.disableCtrlP ||
    !!config.disableF12;

  const needsSelectionHandler = !!config.disableTextSelection;

  /**
   * Normalize event target nodes to elements when possible.
   *
   * @param {*} target Event target candidate.
   * @return {Element|null} Normalized element.
   */
  function normalizeTarget(target) {
    if (!target) {
      return null;
    }

    if (3 === target.nodeType) {
      return target.parentElement || null;
    }

    return target;
  }

  /**
   * Find the closest selector match from a target node.
   *
   * @param {*} target Event target candidate.
   * @param {string} selector CSS selector.
   * @return {Element|null} Closest matching element.
   */
  function findClosest(target, selector) {
    const node = normalizeTarget(target);

    if (!node || "function" !== typeof node.closest) {
      return null;
    }

    return node.closest(selector);
  }

  /**
   * Check if interaction should be bypassed for editable targets.
   *
   * @param {*} target Event target.
   * @return {boolean} True when bypass should apply.
   */
  function shouldBypassTarget(target) {
    return Boolean(
      findClosest(
        target,
        'input, textarea, select, option, [contenteditable]:not([contenteditable="false"])',
      ),
    );
  }

  /**
   * Validate that a link has a usable navigation href.
   *
   * @param {Element|null} link Candidate link element.
   * @return {boolean} True when href is usable.
   */
  function hasUsableLinkHref(link) {
    let href;

    if (!link || "function" !== typeof link.getAttribute) {
      return false;
    }

    href = (link.getAttribute("href") || "").trim().toLowerCase();

    if (!href || "#" === href || 0 === href.indexOf("javascript:")) {
      return false;
    }

    return true;
  }

  /**
   * Determine whether a link behaves like a widget trigger.
   *
   * @param {Element|null} link Link element.
   * @return {boolean} True for widget-style links.
   */
  function isWidgetStyleLink(link) {
    if (!link || "function" !== typeof link.matches) {
      return false;
    }

    if (
      link.matches(
        '[role="button"], .button, .btn, .elementor-button, .elementor-button-link, [aria-controls], [aria-expanded], [data-toggle], [data-bs-toggle], [data-elementor-open-lightbox], [data-elementor-lightbox-slideshow]',
      )
    ) {
      return true;
    }

    return Boolean(
      findClosest(
        link,
        ".faq, .accordion, .elementor-accordion, .elementor-toggle, .elementor-tabs",
      ),
    );
  }

  /**
   * Determine whether a target belongs to an interactive widget.
   *
   * @param {*} target Event target.
   * @return {boolean} True for widget-related targets.
   */
  function isWidgetStyleTarget(target) {
    return Boolean(
      findClosest(
        target,
        'button, [role="button"], .button, .btn, .elementor-button, .elementor-button-link, .elementor-button-text, .elementor-button-content-wrapper, [aria-controls], [aria-expanded], [data-toggle], [data-bs-toggle], .faq, .accordion, .elementor-accordion, .elementor-toggle, .elementor-tabs',
      ),
    );
  }

  /**
   * Resolve a link element from event target or composed path.
   *
   * @param {Event} event Browser event.
   * @return {Element|null} Link element when present.
   */
  function getLinkFromEvent(event) {
    let path;
    let index;
    let link = findClosest(event.target, "a[href]");

    if (link) {
      return link;
    }

    if ("function" !== typeof event.composedPath) {
      return null;
    }

    path = event.composedPath();
    for (index = 0; index < path.length; index += 1) {
      link = findClosest(path[index], "a[href]");
      if (link) {
        return link;
      }
    }

    return null;
  }

  /**
   * Check if right-click should remain enabled for this target.
   *
   * @param {Event} event Browser event.
   * @return {boolean} True when context menu should be allowed.
   */
  function shouldAllowRightClick(event) {
    let link;

    if (!config.allowRightClickLinks) {
      return false;
    }

    if (isWidgetStyleTarget(event.target)) {
      return false;
    }

    link = getLinkFromEvent(event);
    if (!link) {
      return false;
    }

    if (!hasUsableLinkHref(link) || isWidgetStyleLink(link)) {
      return false;
    }

    return true;
  }

  /**
   * Check if this event represents a right-click mouse action.
   *
   * @param {Event} event Browser event.
   * @return {boolean} True for right-button actions.
   */
  function isRightMouseAction(event) {
    return event && "number" === typeof event.button && 2 === event.button;
  }

  /**
   * Block right-click mouse events when required.
   *
   * @param {MouseEvent} event Mouse event.
   * @return {void}
   */
  function handleRightClickEvent(event) {
    if (!isRightMouseAction(event)) {
      return;
    }

    if (shouldAllowRightClick(event)) {
      return;
    }

    blockAction(event);
  }

  /**
   * Detect browser inspector shortcut combinations.
   *
   * @param {KeyboardEvent} event Keyboard event.
   * @param {string} key Lower-cased key value.
   * @return {boolean} True when shortcut matches inspector patterns.
   */
  function isInspectorShortcut(event, key) {
    const hasPrimaryModifier = event.ctrlKey || event.metaKey;

    if (!hasPrimaryModifier) {
      return false;
    }

    if (
      event.shiftKey &&
      ("i" === key || "j" === key || "c" === key || "k" === key)
    ) {
      return true;
    }

    if (event.altKey && ("i" === key || "j" === key || "c" === key)) {
      return true;
    }

    return false;
  }

  /**
   * Build or retrieve the floating alert element.
   *
   * @return {Element|null} Alert element when alerts are enabled.
   */
  function getAlertElement() {
    if (!config.enableAlerts) {
      return null;
    }

    if (alertElement) {
      return alertElement;
    }

    alertElement = document.createElement("div");
    alertElement.className = "lwcp-alert";
    alertElement.setAttribute("role", "status");
    alertElement.setAttribute("aria-live", "polite");
    alertElement.textContent = config.alertMessage || "";
    document.body.appendChild(alertElement);

    return alertElement;
  }

  /**
   * Display blocked-action feedback with rate limiting.
   *
   * @return {void}
   */
  function showAlert() {
    const now = Date.now();
    const element = getAlertElement();

    if (now - lastAlertAt < 700) {
      return;
    }

    lastAlertAt = now;

    if (!element) {
      return;
    }

    element.classList.add("is-visible");

    if (hideTimer) {
      window.clearTimeout(hideTimer);
    }

    hideTimer = window.setTimeout(function () {
      element.classList.remove("is-visible");
    }, 1400);
  }

  /**
   * Cancel an interaction and show feedback.
   *
   * @param {Event} event Browser event.
   * @return {void}
   */
  function blockAction(event) {
    event.preventDefault();
    event.stopPropagation();

    if ("function" === typeof event.stopImmediatePropagation) {
      event.stopImmediatePropagation();
    }

    showAlert();
  }

  if (config.disableRightClick) {
    document.addEventListener(
      "contextmenu",
      function (event) {
        if (shouldAllowRightClick(event)) {
          return;
        }

        blockAction(event);
      },
      true,
    );

    document.addEventListener("mousedown", handleRightClickEvent, true);
    document.addEventListener("pointerdown", handleRightClickEvent, true);
    document.addEventListener("auxclick", handleRightClickEvent, true);
  }

  if (config.disableCopy) {
    document.addEventListener(
      "copy",
      function (event) {
        if (shouldBypassTarget(event.target)) {
          return;
        }

        blockAction(event);
      },
      true,
    );
  }

  if (config.disableImageDrag) {
    document.addEventListener(
      "dragstart",
      function (event) {
        if (event.target && "IMG" === event.target.tagName) {
          blockAction(event);
        }
      },
      true,
    );
  }

  if (needsSelectionHandler) {
    document.addEventListener(
      "selectstart",
      function (event) {
        if (shouldBypassTarget(event.target)) {
          return;
        }

        blockAction(event);
      },
      true,
    );
  }

  if (needsKeydownHandler) {
    document.addEventListener(
      "keydown",
      function (event) {
        const rawKey = event.key || "";
        const key = event.key ? event.key.toLowerCase() : "";
        const hasModifier = event.ctrlKey || event.metaKey;

        if (
          config.disableRightClick &&
          ("contextmenu" === key || (event.shiftKey && "F10" === rawKey))
        ) {
          blockAction(event);
          return;
        }

        if (
          config.disableF12 &&
          ("F12" === rawKey || isInspectorShortcut(event, key))
        ) {
          blockAction(event);
          return;
        }

        if (!hasModifier) {
          return;
        }

        if (config.disableCopy && "c" === key) {
          if (shouldBypassTarget(event.target)) {
            return;
          }

          blockAction(event);
          return;
        }

        if (shouldBypassTarget(event.target)) {
          return;
        }

        if (config.disableCtrlU && "u" === key) {
          blockAction(event);
          return;
        }

        if (config.disableCtrlS && "s" === key) {
          blockAction(event);
          return;
        }

        if (config.disableCtrlP && "p" === key) {
          blockAction(event);
        }
      },
      true,
    );
  }
})();
