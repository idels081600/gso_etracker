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
        rice: document.getElementById("rice_menu_select").value,
        pork: document.getElementById("pork_menu_select").value,
        chicken: document.getElementById("chicken_menu_select").value,
        fish: document.getElementById("fish_menu_select").value,
        beef: document.getElementById("beef_menu_select").value,
        vegetable: document.getElementById("vegetable_menu_select").value,
        noodles: document.getElementById("noodle_menu_select").value,
        dessert: document.getElementById("dessert_menu_select").value,
        drinks: document.getElementById("drinks_menu_select").value,
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
        noodle: document.getElementById("noodle_menu_select").value,
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
    <div class="card mt-4" id="${uniqueId}" style="width: 20rem; margin-left:5%;">
      <div class="card-body">
        <h5 class="card-title mb-0">${orderData.title}</h5>
        <p class="text-muted small mb-2">${orderData.modalTitle}</p>
        <ul class="card-text order_text">`;

  orderData.details.forEach((detail) => {
    orderContent += `<li>${detail.textContent}</li>`;
  });

  orderContent += `</ul>`;
  orderContent += generateSelectionsHTML(type, orderData.selections);

  orderContent += `
        <div class="d-flex align-items-center mt-3">
          <button class="btn btn-outline-secondary btn-sm decrement-pax" type="button">-</button>
          <span class="mx-2 pax-count">${orderData.pax}</span>
          <button class="btn btn-outline-secondary btn-sm increment-pax" type="button">+</button>
        </div>
        <div class="d-flex justify-content-end mt-3">
          <button class="btn btn-danger me-2 remove-order" type="button">Remove</button>
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
        "noodles",
        "dessert",
        "drinks",
      ];
      outCateringItems.forEach((item) => {
        const element = document.getElementById(`${item}_menu_select`);
        if (element && element.selectedIndex > 0) {
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

  const hasOrders = orderContainer.querySelectorAll(".card").length > 0;

  if (hasOrders) {
    const buttonContainer = document.createElement("div");
    buttonContainer.className = "col-11 d-flex justify-content-end mt-3";

    orderNowButton = document.createElement("button");
    orderNowButton.className = "btn btn-order";
    orderNowButton.textContent = "Order Now";
    orderNowButton.addEventListener("click", () => orderModal.show());

    buttonContainer.appendChild(orderNowButton);

    // Always append to the end of orderContainer
    orderContainer.appendChild(buttonContainer);
  }
}

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

  const orders = Array.from(orderContainer.querySelectorAll(".card")).map(
    (card) => {
      return {
        packageTitle: cleanText(card.querySelector(".card-title").textContent),
        modalTitle: cleanText(
          card.querySelector(".text-muted.small").textContent
        ),
        pax: card.querySelector(".pax-count").textContent,
        catDetails: Array.from(
          card.querySelectorAll(".card-text.order_text li")
        ).map((li) => cleanText(li.textContent)),
        selections: Array.from(
          card.querySelectorAll(
            ".menu-section p, p:not(.card-title, .text-muted)"
          )
        ).map((p) => cleanText(p.textContent)),
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
.then(async response => {
    const contentType = response.headers.get("content-type");
    if (!response.ok) {
        const errorText = await response.text();
        console.log("Server response:", errorText);
        throw new Error(`Server error: ${response.status}`);
    }
    if (!contentType || !contentType.includes("application/pdf")) {
        console.log("Invalid content type:", contentType);
        throw new Error("Invalid response format");
    }
    return response.blob();
})
.then((blob) => {
    const url = window.URL.createObjectURL(blob);
    const newWindow = window.open(url, '_blank');
    if (!newWindow) console.log("Popup blocked - PDF generation successful but display blocked");
    window.URL.revokeObjectURL(url);
})
.catch((error) => {
    console.log("Full error details:", error);
    alert("PDF Generation Issue - Check server logs for details");
});  bootstrap.Modal.getInstance(document.getElementById("orderModal")).hide();
  function cleanText(text) {
    return text
      .replace(/[^\w\s\(\)\/\-\.,]/g, "") // Keeps alphanumeric, spaces, parentheses, forward slashes, hyphens, periods, commas
      .replace(/\s+/g, " ") // Replaces multiple spaces with single space
      .trim();
  }
});
