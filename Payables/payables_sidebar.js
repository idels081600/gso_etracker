(function () {
  const toggle = document.getElementById("sidebarToggle");
  const overlay = document.getElementById("sidebarOverlay");
  const desktopQuery = window.matchMedia("(min-width: 901px)");

  function syncToggleState() {
    const isMobile = !desktopQuery.matches;
    const expanded = isMobile
      ? document.body.classList.contains("sidebar-mobile-open")
      : !document.body.classList.contains("sidebar-collapsed");
    if (toggle) {
      toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
    }
  }

  function closeMobileSidebar() {
    document.body.classList.remove("sidebar-mobile-open");
    syncToggleState();
  }

  function applyStoredDesktopState() {
    if (!desktopQuery.matches) {
      document.body.classList.remove("sidebar-collapsed");
      closeMobileSidebar();
      return;
    }

    if (localStorage.getItem("payablesSidebarCollapsed") === "1") {
      document.body.classList.add("sidebar-collapsed");
    } else {
      document.body.classList.remove("sidebar-collapsed");
    }
    closeMobileSidebar();
    syncToggleState();
  }

  if (toggle) {
    toggle.addEventListener("click", function () {
      if (desktopQuery.matches) {
        document.body.classList.toggle("sidebar-collapsed");
        localStorage.setItem(
          "payablesSidebarCollapsed",
          document.body.classList.contains("sidebar-collapsed") ? "1" : "0"
        );
      } else {
        document.body.classList.toggle("sidebar-mobile-open");
      }
      syncToggleState();
    });
  }

  if (overlay) {
    overlay.addEventListener("click", closeMobileSidebar);
  }

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      closeMobileSidebar();
    }
  });

  desktopQuery.addEventListener("change", applyStoredDesktopState);
  applyStoredDesktopState();
})();
