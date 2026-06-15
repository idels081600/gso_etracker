(function () {
  "use strict";

  const token = document.querySelector('meta[name="csrf-token"]')?.content || "";

  window.AssetApp = {
    csrfToken: token,
    escapeHtml(value) {
      return String(value ?? "").replace(/[&<>"']/g, (character) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
      })[character]);
    },
  };

  if (!token) return;

  const nativeFetch = window.fetch;
  window.fetch = function (resource, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set("X-CSRF-Token", token);
    headers.set("X-Requested-With", "XMLHttpRequest");
    return nativeFetch(resource, { ...options, headers });
  };

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('form[method="post" i]').forEach((form) => {
      if (form.querySelector('input[name="_csrf"]')) return;
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = "_csrf";
      input.value = token;
      form.appendChild(input);
    });

    if (window.jQuery) {
      window.jQuery.ajaxSetup({
        headers: {
          "X-CSRF-Token": token,
          "X-Requested-With": "XMLHttpRequest",
        },
      });
    }

    const sidebar = document.querySelector(".sidebar");
    if (sidebar) {
      const toggle = document.createElement("button");
      toggle.type = "button";
      toggle.className = "asset-sidebar-toggle";
      toggle.setAttribute("aria-label", "Open navigation");
      toggle.setAttribute("aria-expanded", "false");
      toggle.innerHTML = '<span aria-hidden="true">☰</span>';

      const overlay = document.createElement("button");
      overlay.type = "button";
      overlay.className = "asset-sidebar-overlay";
      overlay.setAttribute("aria-label", "Close navigation");

      const closeSidebar = () => {
        document.body.classList.remove("asset-sidebar-open");
        toggle.setAttribute("aria-expanded", "false");
      };

      toggle.addEventListener("click", () => {
        const isOpen = document.body.classList.toggle("asset-sidebar-open");
        toggle.setAttribute("aria-expanded", String(isOpen));
      });
      overlay.addEventListener("click", closeSidebar);

      document.body.append(toggle, overlay);

      const currentPage = window.location.pathname.split("/").pop();
      sidebar.querySelectorAll("a[href]").forEach((link) => {
        if (link.getAttribute("href")?.split("?")[0] === currentPage) {
          link.classList.add("active");
          link.setAttribute("aria-current", "page");
        }
      });
    }
  });
})();
