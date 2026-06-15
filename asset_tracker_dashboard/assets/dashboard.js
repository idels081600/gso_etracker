document.addEventListener("DOMContentLoaded", () => {
  const selector = document.getElementById("sel1");
  const search = document.getElementById("search-input");
  const tables = {
    Tent: document.getElementById("table_tent1"),
    Transportation: document.getElementById("table_transportation"),
    RFQ: document.getElementById("table_rfq"),
  };

  const activeTable = () => tables[selector?.value || "Tent"];

  selector?.addEventListener("change", () => {
    Object.values(tables).forEach((table) => {
      if (table) table.style.display = table === activeTable() ? "table" : "none";
    });
    search?.dispatchEvent(new Event("input"));
  });

  search?.addEventListener("input", () => {
    const filter = search.value.trim().toLowerCase();
    activeTable()?.querySelectorAll("tbody tr").forEach((row) => {
      row.hidden = !row.textContent.toLowerCase().includes(filter);
    });
  });
});
