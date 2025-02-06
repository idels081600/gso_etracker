// Common variables
const orderContainer = document.querySelector(".order_container");
const paxInputs = document.querySelectorAll(".input-group .form-control");
const modals = {
  heavySnacks: document.getElementById("heavy_snacks_Modal"),
  packPlate: document.getElementById("pack_plate_Modal"),
  outCatering: document.getElementById("outCatering_Modal"),
  inCatering: document.getElementById("inCatering_Modal"),
  liveOutCatering: document.getElementById("live_out_Catering_Modal"),
};

// Bootstrap modals
const statusErrorsModal = new bootstrap.Modal(
  document.getElementById("statusErrorsModal")
);
const statusSuccessModal = new bootstrap.Modal(
  document.getElementById("statusSuccessModal")
);
const orderModal = new bootstrap.Modal(document.getElementById("orderModal"));

let selectedCard = null;
let orderNowButton = null;

// Card selection for all modals
document.querySelectorAll(".clickable-card").forEach((card) => {
  card.addEventListener("click", () => {
    const modalCards = card
      .closest(".modal-body")
      .querySelectorAll(".clickable-card");
    modalCards.forEach((c) => c.classList.remove("selected-card", "active"));
    card.classList.add("selected-card", "active");
    selectedCard = card;
    updatePaxTitle(card);
  });
});

function updatePaxTitle(card) {
  const cardTitle = card.querySelector(".card-title").textContent;
  const paxTitle = card.closest(".modal-content").querySelector(".pax-title");
  if (paxTitle) {
    paxTitle.textContent = cardTitle;
  }
}

// Handle checkout for all modals
document
  .getElementById("checkout-btn")
  .addEventListener("click", () => handleCheckout("heavy-snacks"));
document
  .getElementById("checkout-btn1")
  .addEventListener("click", () => handleCheckout("pack-plate"));
document
  .getElementById("checkout-btn2")
  .addEventListener("click", () => handleCheckout("out-catering"));
document
  .getElementById("checkout-btn3")
  .addEventListener("click", () => handleCheckout("in-catering"));
document
  .getElementById("checkout-btn4")
  .addEventListener("click", () => handleCheckout("live-out-catering"));

function handleCheckout(type) {
  if (!selectedCard) {
    showErrorModal();
    return;
  }

  const modal = selectedCard.closest(".modal");
  const modalTitle = modal.querySelector(".modal-title").textContent;
  const paxInput = modal.querySelector(".input-group .form-control");
  const pax = parseInt(paxInput.value);

  if (isNaN(pax) || pax < 1) {
    showErrorModal();
    return;
  }

  const orderData = {
    title: selectedCard.querySelector(".card-title").textContent,
    modalTitle: modalTitle,
    details: selectedCard.querySelectorAll("#cat_details p"),
    selections: getSelections(type),
    pax: pax,
  };

  createOrder(orderData, type);
  showSuccessModal();
}

function getSelections(type) {
  switch (type) {
    case "heavy-snacks":
      return {
        dish: document.getElementById("inputGroupSelectSpaghettiBami").value,
        drinks: document.getElementById("inputGroupSelectDrinks").value,
      };
    case "out-catering":
      return {
        rice: document.getElementById("rice_out_select").value,
        pork: document.getElementById("pork_out_select").value,
        chicken: document.getElementById("chicken_out_select").value,
        fish: document.getElementById("fish_out_select").value,
        beef: document.getElementById("beef_out_select").value,
        vegetable: document.getElementById("vegetable_out_select").value,
        noodles: document.getElementById("noodle_out_select").value,
        dessert: document.getElementById("dessert_out_select").value,
        drinks: document.getElementById("drinks_out_select").value,
      };
    case "in-catering":
      return {
        breakfast: {
          rice: document.getElementById("breakfast_rice_select").value,
          egg: document.getElementById("egg_select").value,
          meat: document.getElementById("breakfast_meat_select").value,
          fruit: document.getElementById("fruit_select").value,
          drinks: document.getElementById("breakfast_drinks_select").value,
        },
        lunch: {
          rice: document.getElementById("lunch_rice_select").value,
          soup: document.getElementById("soup_select").value,
          chicken: document.getElementById("lunch_chicken_select").value,
          pork: document.getElementById("lunch_pork_select").value,
          fish: document.getElementById("lunch_fish_select").value,
          vegetable: document.getElementById("lunch_vegetable_select").value,
          ensalada: document.getElementById("lunch_ensalada_select").value,
          dessert: document.getElementById("lunch_dessert_select").value,
          drinks: document.getElementById("lunch_drinks_select").value,
        },
        snacks: {
          am: document.getElementById("am_snacks_select").value,
          pm: document.getElementById("pm_snacks_select").value,
          drinks: document.getElementById("snacks_drinks_select").value,
        },
      };
    case "live-out-catering":
      return {
        rice: document.getElementById("live_out_rice_select").value,
        soup: document.getElementById("live_out_soup_select").value,
        fish: document.getElementById("live_out_fish_select").value,
        chicken: document.getElementById("live_out_chicken_select").value,
        pork: document.getElementById("live_out_pork_select").value,
        noodles: document.getElementById("live_out_noodles_select").value,
        ensalada: document.getElementById("live_out_ensalada_select").value,
        dessert: document.getElementById("live_out_dessert_select").value,
        snacks: {
          am: document.getElementById("live_out_am_snacks_select").value,
          pm: document.getElementById("live_out_pm_snacks_select").value,
          drinks: document.getElementById("live_out_drinks_select").value,
        },
      };
    default:
      return {
        pork: document.getElementById("pork_menu_select").value,
        vegetable: document.getElementById("vegetable_menu_select").value,
        chicken: document.getElementById("chicken_menu_select").value,
        noodles: document.getElementById("noodle_menu_select").value,
        dessert: document.getElementById("dessert_menu_select").value,
        drinks: document.getElementById("drinks_menu_select").value,
      };
  }
}

function createOrder(orderData, type) {
  const uniqueId = `order-${Date.now()}`;
  const orderHTML = generateOrderHTML(uniqueId, orderData, type);
  orderContainer.insertAdjacentHTML("beforeend", orderHTML);
  attachOrderControls(uniqueId);
  toggleOrderNowButton();
}
function generateOrderHTML(uniqueId, orderData, type) {
  let orderContent = `
    <div class="col-12 mb-2">
      <div class="card_cart" id="${uniqueId}">
        <div class="card-body">
          <h5 class="card-title mb-2 editable" contenteditable="true">${orderData.title}</h5>
          <p class="text-muted small mb-3 editable" contenteditable="true">${orderData.modalTitle}</p>
          <div class="card-text">
            <ul class="order_text mb-3">`;

  orderData.details.forEach((detail) => {
    orderContent += `<li class="editable" contenteditable="true">${detail.textContent}</li>`;
  });

  orderContent += `</ul>`;
  orderContent += generateSelectionsHTML(type, orderData.selections);
  orderContent += `
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <div class="pax-controls">
              <button class="btn btn-outline-secondary btn-sm decrement-pax" type="button">-</button>
              <span class="mx-2 pax-count">${orderData.pax}</span>
              <button class="btn btn-outline-secondary btn-sm increment-pax" type="button">+</button>
            </div>
            <button class="btn btn-danger remove-order" type="button">Remove</button>
          </div>
        </div>
      </div>
    </div>`;

  return orderContent;
}
function generateSelectionsHTML(type, selections) {
  let html = "";

  switch (type) {
    case "heavy-snacks":
      html += `
        <div class="menu-section">
          <p><strong>Dish Selected:</strong> ${
            selections.dish === "1" ? "Spaghetti" : "Bam-i Guisado"
          }</p>
          <p><strong>Drink Selected:</strong> ${
            selections.drinks === "1" ? "Soft Drinks (Sakto)" : "Bottled Water"
          }</p>
        </div>`;
      break;
    case "out-catering":
      html += `<div class="menu-section">`;
      const outCateringItems = [
        "rice",
        "pork",
        "chicken",
        "fish",
        "beef",
        "vegetable",
        "noodle",
        "dessert",
        "drinks",
      ];

      outCateringItems.forEach((item) => {
        const element = document.getElementById(`${item}_out_select`);
        if (
          element &&
          element.selectedIndex > 0 &&
          !element.options[element.selectedIndex].text.includes("Choose")
        ) {
          html += `<p><strong>${capitalize(item)}:</strong> ${
            element.options[element.selectedIndex].text
          }</p>`;
        }
      });
      html += `</div>`;
      break;

    case "pack-plate":
      html += `<div class="menu-section">`;
      const packPlateItems = [
        "pork",
        "vegetable",
        "chicken",
        "noodle",
        "dessert",
        "drinks",
      ];
      packPlateItems.forEach((item) => {
        const element = document.getElementById(`${item}_menu_select`);
        if (element && element.selectedIndex > 0) {
          html += `<p><strong>${capitalize(item)}:</strong> ${
            element.options[element.selectedIndex].text
          }</p>`;
        }
      });
      html += `</div>`;
      break;

    case "in-catering":
      // Breakfast Section
      html += `<div class="menu-section">
        <h6 class="section-title">Breakfast</h6>`;
      Object.entries(selections.breakfast).forEach(([key, value]) => {
        const element = document.getElementById(`breakfast_${key}_select`);
        if (element && element.selectedIndex > 0) {
          html += `<p><strong>${capitalize(key)}:</strong> ${
            element.options[element.selectedIndex].text
          }</p>`;
        }
      });

      // Lunch Section
      html += `</div><div class="menu-section">
        <h6 class="section-title">Lunch</h6>`;
      Object.entries(selections.lunch).forEach(([key, value]) => {
        const element = document.getElementById(`lunch_${key}_select`);
        if (element && element.selectedIndex > 0) {
          html += `<p><strong>${capitalize(key)}:</strong> ${
            element.options[element.selectedIndex].text
          }</p>`;
        }
      });

      // Snacks Section
      html += `</div><div class="menu-section">
        <h6 class="section-title">Snacks</h6>`;
      const snacksElements = {
        "AM Snack": "am_snacks_select",
        "PM Snack": "pm_snacks_select",
        Drinks: "snacks_drinks_select",
      };
      Object.entries(snacksElements).forEach(([label, id]) => {
        const element = document.getElementById(id);
        if (element && element.selectedIndex > 0) {
          html += `<p><strong>${label}:</strong> ${
            element.options[element.selectedIndex].text
          }</p>`;
        }
      });
      html += "</div>";
      break;

    case "live-out-catering":
      // Main dishes section
      html += `<div class="menu-section">
        <h6 class="section-title">Main Course</h6>`;
      const mainCourseItems = [
        "rice",
        "soup",
        "fish",
        "chicken",
        "pork",
        "noodles",
        "ensalada",
        "dessert",
      ];
      mainCourseItems.forEach((item) => {
        const element = document.getElementById(`live_out_${item}_select`);
        if (element && element.selectedIndex > 0) {
          html += `<p><strong>${capitalize(item)}:</strong> ${
            element.options[element.selectedIndex].text
          }</p>`;
        }
      });

      // Snacks section
      html += `</div><div class="menu-section">
        <h6 class="section-title">Snacks</h6>`;
      ["am_snacks", "pm_snacks", "drinks"].forEach((item) => {
        const element = document.getElementById(`live_out_${item}_select`);
        if (element && element.selectedIndex > 0) {
          const label =
            item === "am_snacks"
              ? "AM Snack"
              : item === "pm_snacks"
              ? "PM Snack"
              : "Drinks";
          html += `<p><strong>${label}:</strong> ${
            element.options[element.selectedIndex].text
          }</p>`;
        }
      });
      html += "</div>";
      break;
  }

  return html;
}
function capitalize(string) {
  return string.charAt(0).toUpperCase() + string.slice(1);
}

function attachOrderControls(uniqueId) {
  const orderCard = document.getElementById(uniqueId);

  orderCard.querySelector(".increment-pax").addEventListener("click", () => {
    const paxCount = orderCard.querySelector(".pax-count");
    paxCount.textContent = parseInt(paxCount.textContent) + 1;
  });

  orderCard.querySelector(".decrement-pax").addEventListener("click", () => {
    const paxCount = orderCard.querySelector(".pax-count");
    if (parseInt(paxCount.textContent) > 1) {
      paxCount.textContent = parseInt(paxCount.textContent) - 1;
    }
  });

  orderCard.querySelector(".remove-order").addEventListener("click", () => {
    orderCard.remove();
    toggleOrderNowButton();
  });
}

function toggleOrderNowButton() {
  if (orderNowButton) {
    orderNowButton.parentElement.remove();
    orderNowButton = null;
  }

  const hasOrders = orderContainer.querySelectorAll(".card_cart").length > 0;

  if (hasOrders && window.innerWidth <= 576) {
    // Only show on mobile
    const buttonContainer = document.createElement("div");
    buttonContainer.className = "mobile-order-button";

    orderNowButton = document.createElement("button");
    orderNowButton.className = "btn btn-order";
    orderNowButton.textContent = "Order Now";

    const orderModal = new bootstrap.Modal(
      document.getElementById("orderModal")
    );

    orderNowButton.addEventListener("click", () => {
      orderModal.show();
    });

    buttonContainer.appendChild(orderNowButton);
    document.body.appendChild(buttonContainer);
  }
}

// Add resize listener to handle screen size changes
window.addEventListener("resize", toggleOrderNowButton);

function showErrorModal() {
  statusErrorsModal.show();
  setTimeout(() => statusErrorsModal.hide(), 2000);
}

function showSuccessModal() {
  statusSuccessModal.show();
  setTimeout(() => statusSuccessModal.hide(), 1000);
}
// Add this to your existing JavaScript file
document.getElementById("orderForm").addEventListener("submit", function (e) {
  e.preventDefault();

  const orders = Array.from(orderContainer.querySelectorAll(".card_cart")).map(
    (card) => {
      return {
        packageTitle: cleanText(card.querySelector(".card-title").textContent),
        modalTitle: cleanText(
          card.querySelector(".text-muted.small").textContent
        ),
        pax: card.querySelector(".pax-count").textContent,
        catDetails: Array.from(
          card.querySelector(".order_text").getElementsByTagName("li")
        ).map((li) => cleanText(li.textContent)),
        selections: Array.from(card.querySelectorAll(".menu-section p")).map(
          (p) => cleanText(p.textContent)
        ),
        orderContent: cleanText(card.innerHTML),
      };
    }
  );

  const formData = {
    date: document.getElementById("orderDate").value,
    activity: cleanText(document.getElementById("orderActivity").value),
    poItb: cleanText(document.getElementById("orderPoItb").value),
    orders: orders,
  };

  fetch("jjs_pdf.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(formData),
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.blob();
    })
    .then((blob) => {
      const url = window.URL.createObjectURL(blob);
      window.open(url, "_blank");
    })
    .catch((error) => {
      console.error("Error:", error);
      alert("Failed to process order. Please try again.");
    });

  bootstrap.Modal.getInstance(document.getElementById("orderModal")).hide();
});

function cleanText(text) {
  return text
    .replace(/[^\w\s\(\)\/\-\.,]/g, "")
    .replace(/\s+/g, " ")
    .trim();
}
document.addEventListener("DOMContentLoaded", function () {
  const scrollableContainer = document.querySelector(".scrollable-container");

  scrollableContainer.style.cursor = "ew-resize"; // Adds east-west resize cursor

  scrollableContainer.addEventListener("wheel", function (event) {
    event.preventDefault(); // Prevent vertical scroll
    this.scrollBy({
      left: event.deltaY, // Move horizontally
      behavior: "smooth", // Smooth scrolling effect
    });
  });
});

// Add this to your existing card click event listeners
document.querySelectorAll(".clickable-card").forEach((card) => {
  card.addEventListener("click", () => {
    const modalCards = card
      .closest(".modal-body")
      .querySelectorAll(".clickable-card");
    modalCards.forEach((c) => c.classList.remove("selected-card", "active"));
    card.classList.add("selected-card", "active");
    selectedCard = card;
    updatePaxTitle(card);

    // Add this new condition
    const cardPrice = card.querySelector(".card-title").textContent;
    const spaghettiBamiSelect = document.getElementById(
      "inputGroupSelectSpaghettiBami"
    );

    if (cardPrice.includes("230")) {
      // Remove Spaghetti option for 230/head
      spaghettiBamiSelect.innerHTML = `
        <option selected disabled>Choose Bam-i...</option>
        <option value="2">Bam-i Guisado</option>
      `;
    } else {
      // Show both options for 250/head
      spaghettiBamiSelect.innerHTML = `
        <option selected disabled>Choose Spaghetti or Bam-i...</option>
        <option value="1">Spaghetti</option>
        <option value="2">Bam-i Guisado</option>
      `;
    }
  });
});
//packed or plated
document.querySelectorAll(".clickable-card").forEach((card) => {
  card.addEventListener("click", () => {
    const modalCards = card
      .closest(".modal-body")
      .querySelectorAll(".clickable-card");
    modalCards.forEach((c) => c.classList.remove("selected-card", "active"));
    card.classList.add("selected-card", "active");
    selectedCard = card;
    updatePaxTitle(card);

    const cardPrice = card.querySelector(".card-title").textContent;
    const vegetableSelect = document
      .getElementById("vegetable_menu_select")
      .closest(".dropdown_men");
    const noodleSelect = document
      .getElementById("noodle_menu_select")
      .closest(".dropdown_men");
    const drinksSelect = document.getElementById("drinks_menu_select");

    if (cardPrice.includes("275")) {
      // Hide vegetable and noodle dropdowns for 275/head
      vegetableSelect.style.display = "none";
      noodleSelect.style.display = "none";
      // Update drinks dropdown to show only Sakto
      drinksSelect.innerHTML = `
        <option selected disabled>Choose Drinks...</option>
        <option value="1">SoftDrinks (Sakto)</option>
        <option value="2">Bottled Water</option>
      `;
    } else {
      // Show all dropdowns for 330/head
      vegetableSelect.style.display = "block";
      noodleSelect.style.display = "block";
      // Show both drink options
      drinksSelect.innerHTML = `
        <option selected disabled>Choose Sakto or Bottled Water...</option>
        <option value="1">SoftDrinks (Sakto)</option>
        <option value="2">Bottled Water</option>
      `;
    }
  });
});
//out catering dropdown
document.querySelectorAll(".clickable-card").forEach((card) => {
  card.addEventListener("click", (event) => {
    // Get the card title to determine the price
    const priceText = card.querySelector(".card-title").textContent;

    // Grab dropdown elements
    const porkDropdown =
      document.getElementById("pork_out_select").parentElement.parentElement;
    const beefDropdown =
      document.getElementById("beef_out_select").parentElement.parentElement;

    // Show all dropdowns by default
    porkDropdown.style.display = "block";
    beefDropdown.style.display = "block";

    if (priceText.includes("₱400.00")) {
      // Hide pork and beef dropdowns
      porkDropdown.style.display = "none";
      beefDropdown.style.display = "none";
    } else if (priceText.includes("₱450.00")) {
      // Hide beef dropdown
      beefDropdown.style.display = "none";
    } else if (priceText.includes("₱500.00")) {
      // Hide beef dropdown
      beefDropdown.style.display = "none";
    } else {
      // Show all dropdowns for other cases
      porkDropdown.style.display = "block";
      beefDropdown.style.display = "block";
    }
  });
});
document.addEventListener("DOMContentLoaded", function () {
  const porkSelect = document.getElementById("live_out_pork_select");
  const chickenSelect = document.getElementById("live_out_chicken_select");
  const priceCards = document.querySelectorAll(".clickable-card");

  // Function to handle dropdown visibility
  function handleDropdownVisibility(selectedPrice) {
    if (selectedPrice === "550.00") {
      // For 550/head package
      porkSelect.addEventListener("change", function () {
        if (this.value !== "Choose Pork...") {
          chickenSelect.parentElement.parentElement.style.display = "none";
        } else {
          chickenSelect.parentElement.parentElement.style.display = "block";
        }
      });

      chickenSelect.addEventListener("change", function () {
        if (this.value !== "Choose Chicken...") {
          porkSelect.parentElement.parentElement.style.display = "none";
        } else {
          porkSelect.parentElement.parentElement.style.display = "block";
        }
      });
    } else {
      // Reset visibility for other price packages
      porkSelect.parentElement.parentElement.style.display = "block";
      chickenSelect.parentElement.parentElement.style.display = "block";

      // Remove event listeners
      porkSelect.removeEventListener("change", null);
      chickenSelect.removeEventListener("change", null);
    }
  }

  // Add click event listeners to price cards
  priceCards.forEach((card) => {
    card.addEventListener("click", function () {
      const price = this.querySelector(".card-title")
        .textContent.split("₱")[1]
        .split("/")[0];
      handleDropdownVisibility(price);

      // Reset dropdowns when switching cards
      porkSelect.value = "Choose Pork...";
      chickenSelect.value = "Choose Chicken...";
      porkSelect.parentElement.parentElement.style.display = "block";
      chickenSelect.parentElement.parentElement.style.display = "block";
    });
  });
});
document.addEventListener("DOMContentLoaded", function () {
  const orderNowContainer = document.querySelector(".orderNow-container");
  const viewOrderBtn = document.createElement("button");
  const orderModal = new bootstrap.Modal(document.getElementById("orderModal"));

  document.body.appendChild(viewOrderBtn);

  function handleMobileView() {
    if (window.innerWidth <= 576) {
      orderNowContainer.style.display = "none";
      viewOrderBtn.style.display = "block";
    } else {
      orderNowContainer.style.display = "block";
      viewOrderBtn.style.display = "none";
    }
  }

  viewOrderBtn.addEventListener("click", () => {
    orderModal.show();
  });

  handleMobileView();
  window.addEventListener("resize", handleMobileView);
});
