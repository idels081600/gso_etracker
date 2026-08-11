document.addEventListener("DOMContentLoaded", function () {
  const input = document.querySelector('input[name="document_search"]');
  const hidden = document.getElementById("documentRefValue");
  const options = Array.from(document.querySelectorAll("#barcodeDocumentSuggestions option"));

  function syncDocumentRef() {
    if (!input || !hidden) return;
    const typed = input.value.trim().toLowerCase();
    const match = options.find(function (option) {
      return option.value.trim().toLowerCase() === typed;
    });
    hidden.value = match ? match.dataset.value || "" : "";
  }

  input?.addEventListener("input", syncDocumentRef);
  input?.addEventListener("change", syncDocumentRef);
  input?.form?.addEventListener("submit", syncDocumentRef);
});