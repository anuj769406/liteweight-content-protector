/* global LWCP_ADMIN */
(function () {
  "use strict";

  const config = window.LWCP_ADMIN || {};
  const removeLabel = config.removeLabel || "";

  if (!config.ajaxUrl || !config.nonce) {
    return;
  }

  /**
   * Toggle admin rows based on selected apply mode.
   *
   * @return {void}
   */
  function initApplyModeUi() {
    const radios = document.querySelectorAll(
      'input[name="lwcp_settings[apply_mode]"]',
    );
    const specificRow = document.getElementById("lwcp-specific-pages-row");
    const excludedRow = document.getElementById("lwcp-excluded-pages-row");

    if (!radios.length || !specificRow || !excludedRow) {
      return;
    }

    /**
     * Show only the row relevant for the current apply mode.
     *
     * @return {void}
     */
    function updateModeRows() {
      let mode = "global";

      radios.forEach(function (radio) {
        if (radio.checked) {
          mode = radio.value;
        }
      });

      if ("specific" === mode) {
        specificRow.style.display = "";
        excludedRow.style.display = "none";
        return;
      }

      specificRow.style.display = "none";
      excludedRow.style.display = "";
    }

    radios.forEach(function (radio) {
      radio.addEventListener("change", updateModeRows);
    });

    updateModeRows();
  }

  /**
   * Initialize one searchable page picker instance.
   *
   * @param {Object} elements Picker DOM references.
   * @return {void}
   */
  function initPicker(elements) {
    const picker = elements.picker;
    const searchInput = elements.searchInput;
    const resultsList = elements.resultsList;
    const selectedList = elements.selectedList;
    const idsInput = elements.idsInput;
    let selected = {};
    let debounceTimer = null;

    if (!picker || !searchInput || !resultsList || !selectedList || !idsInput) {
      return;
    }

    /**
     * Read initial picker values from data attributes.
     *
     * @return {void}
     */
    function parseInitialPages() {
      const raw = picker.getAttribute("data-initial-pages");
      let pages;

      if (!raw) {
        return;
      }

      try {
        pages = JSON.parse(raw);
      } catch (error) {
        return;
      }

      if (!Array.isArray(pages)) {
        return;
      }

      pages.forEach(function (page) {
        if (page && page.id) {
          selected[String(page.id)] = {
            id: Number(page.id),
            title: page.title || "#" + page.id,
          };
        }
      });
    }

    /**
     * Render selected items in the picker list.
     *
     * @return {void}
     */
    function renderSelectedItems() {
      const keys = Object.keys(selected);

      selectedList.innerHTML = "";

      if (!keys.length) {
        return;
      }

      keys.forEach(function (key) {
        const item = selected[key];
        const li = document.createElement("li");
        const label = document.createElement("span");
        const button = document.createElement("button");

        li.className = "lwcp-selected-item";
        label.className = "lwcp-selected-label";
        label.textContent = item.title + " (#" + item.id + ")";

        button.type = "button";
        button.className = "button-link-delete";
        button.textContent = removeLabel;
        button.setAttribute("data-id", String(item.id));
        button.setAttribute("aria-label", removeLabel + " " + item.title);

        li.appendChild(label);
        li.appendChild(button);
        selectedList.appendChild(li);
      });
    }

    /**
     * Sync the hidden/manual IDs field with selected items.
     *
     * @return {void}
     */
    function syncIdsField() {
      const ids = Object.keys(selected)
        .map(function (key) {
          return String(selected[key].id);
        })
        .sort(function (a, b) {
          return Number(a) - Number(b);
        });

      idsInput.value = ids.join(", ");
    }

    /**
     * Refresh selected items UI and source field.
     *
     * @return {void}
     */
    function updateSelectedUi() {
      renderSelectedItems();
      syncIdsField();
    }

    /**
     * Remove any visible search results.
     *
     * @return {void}
     */
    function clearResults() {
      resultsList.innerHTML = "";
    }

    /**
     * Add a page to selected items.
     *
     * @param {Object} page Page object with id and title.
     * @return {void}
     */
    function addSelected(page) {
      const id = String(page.id);

      if (!id || selected[id]) {
        return;
      }

      selected[id] = {
        id: Number(page.id),
        title: page.title || "#" + page.id,
      };

      updateSelectedUi();
    }

    /**
     * Remove one selected page by id.
     *
     * @param {string} id Selected item id.
     * @return {void}
     */
    function removeSelectedById(id) {
      if (!selected[id]) {
        return;
      }

      delete selected[id];
      updateSelectedUi();
    }

    /**
     * Parse comma/space-separated IDs from manual input.
     *
     * @param {string} raw Raw input value.
     * @return {void}
     */
    function parseIdsValue(raw) {
      const ids = (raw || "")
        .split(/[\s,]+/)
        .map(function (part) {
          return part.trim();
        })
        .filter(function (part) {
          return /^\d+$/.test(part);
        });

      selected = {};
      ids.forEach(function (id) {
        selected[id] = {
          id: Number(id),
          title: "#" + id,
        };
      });
    }

    /**
     * Render API search results.
     *
     * @param {Array<Object>} items Search result items.
     * @return {void}
     */
    function renderResults(items) {
      clearResults();

      if (!items.length) {
        return;
      }

      items.forEach(function (item) {
        if (!item || !item.id || selected[String(item.id)]) {
          return;
        }

        const li = document.createElement("li");
        const button = document.createElement("button");

        button.type = "button";
        button.className = "button";
        button.textContent = item.title + " (#" + item.id + ")";
        button.setAttribute("data-id", String(item.id));
        button.setAttribute("data-title", item.title || "");

        li.appendChild(button);
        resultsList.appendChild(li);
      });
    }

    /**
     * Query the page search endpoint.
     *
     * @param {string} term Search term.
     * @return {void}
     */
    function searchPages(term) {
      let url;

      if (!term || term.length < 2) {
        clearResults();
        return;
      }

      url =
        config.ajaxUrl +
        "?" +
        new URLSearchParams({
          action: "lwcp_search_pages",
          nonce: config.nonce,
          term: term,
        }).toString();

      window
        .fetch(url, { credentials: "same-origin" })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (!data || !data.success || !Array.isArray(data.data)) {
            clearResults();
            return;
          }

          renderResults(data.data);
        })
        .catch(function () {
          clearResults();
        });
    }

    searchInput.addEventListener("input", function (event) {
      const term = event.target.value.trim();

      if (debounceTimer) {
        window.clearTimeout(debounceTimer);
      }

      debounceTimer = window.setTimeout(function () {
        searchPages(term);
      }, 180);
    });

    resultsList.addEventListener("click", function (event) {
      const target = event.target;
      let button;
      let id;
      let title;

      if (!target || !(target instanceof Element)) {
        return;
      }

      button = target.closest("button[data-id]");
      if (!button) {
        return;
      }

      id = button.getAttribute("data-id");
      title = button.getAttribute("data-title") || "#" + id;

      addSelected({ id: Number(id), title: title });
      searchInput.focus();
    });

    selectedList.addEventListener("click", function (event) {
      const target = event.target;
      let button;

      if (!target || !(target instanceof Element)) {
        return;
      }

      button = target.closest("button[data-id]");
      if (!button) {
        return;
      }

      removeSelectedById(String(button.getAttribute("data-id")));
    });

    idsInput.addEventListener("change", function () {
      // Keep manual fallback input as source of truth if edited directly.
      parseIdsValue(idsInput.value);
      renderSelectedItems();
      clearResults();
    });

    parseInitialPages();
    updateSelectedUi();
  }

  initPicker({
    picker: document.getElementById("lwcp-specific-pages-picker"),
    searchInput: document.getElementById("lwcp-specific-page-search"),
    resultsList: document.getElementById("lwcp-specific-page-search-results"),
    selectedList: document.getElementById("lwcp-specific-selected-pages"),
    idsInput: document.getElementById("lwcp-specific-page-ids"),
  });

  initPicker({
    picker: document.getElementById("lwcp-excluded-pages-picker"),
    searchInput: document.getElementById("lwcp-page-search"),
    resultsList: document.getElementById("lwcp-page-search-results"),
    selectedList: document.getElementById("lwcp-selected-pages"),
    idsInput: document.getElementById("lwcp-excluded-page-ids"),
  });

  initApplyModeUi();
})();
