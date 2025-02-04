<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card Grid with DM Sans</title>

    <!-- Google Font: DM Sans -->
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="jjs.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<body>
    <!-- Loader -->


    <!-- Content (Initially hidden) -->
    <div class="collapse" id="navbarToggleExternalContent" data-bs-theme="dark">
        <div class="bg-dark p-4">
            <a href="logout.php" class="logout-item"><i class="fas fa-sign-out-alt icon-size"></i> Logout</a>
        </div>
    </div>
    <nav class="navbar navbar-dark sticky-top" style="background-color: #17C37B;">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarToggleExternalContent" aria-controls="navbarToggleExternalContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </nav>

    <div class="container mt-4" id="content">
        <div class="header_text text-left mb-3 ">
            <h2 class="fw-bold" style="margin-left: -27px;">Choose Category</h2>
        </div>
        <div class="container mt-4  scrollable-container">
            <div class="row g-2">
                <!-- Card 1 -->
                <div class="col-6">
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden" data-bs-toggle="modal" data-bs-target="#heavy_snacks_Modal">
                        <img src="heavy_snacks.jpg" class="card-img" alt="Heavy Snacks" loading="lazy" width="500" height="300">
                        <div class="card-img-overlay d-flex flex-column justify-content-end bg-dark bg-opacity-50 p-3">
                            <h5 class="card-title text-white fw-bold">Heavy Snacks</h5>
                        </div>
                    </div>
                </div>
                <!-- Card 2 -->
                <div class="col-6">
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden" data-bs-toggle="modal" data-bs-target="#pack_plate_Modal">
                        <img src="pack_plated.jpg" class="card-img" alt="Packed or Plated">
                        <div class="card-img-overlay d-flex flex-column justify-content-end bg-dark bg-opacity-50 p-3">
                            <h5 class="card-title text-white fw-bold">Packed or Plated</h5>
                        </div>
                    </div>
                </div>
                <!-- Card 3 -->
                <div class="col-6">
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden" data-bs-toggle="modal" data-bs-target="#outCatering_Modal">
                        <img src="cater-out.jpg" class="card-img" alt="Out Catering">
                        <div class="card-img-overlay d-flex flex-column justify-content-end bg-dark bg-opacity-50 p-3">
                            <h5 class="card-title text-white fw-bold">Out Catering</h5>
                        </div>
                    </div>
                </div>
                <!-- Card 4 -->
                <div class="col-6">
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden" data-bs-toggle="modal" data-bs-target="#inCatering_Modal">
                        <img src="live-in.jpg" class="card-img" alt="Live-in Package">
                        <div class="card-img-overlay d-flex flex-column justify-content-end bg-dark bg-opacity-50 p-3">
                            <h5 class="card-title text-white fw-bold">Live-in Package</h5>
                        </div>
                    </div>
                </div>
                <!-- Card 5 -->
                <div class="col-6">
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden" data-bs-toggle="modal" data-bs-target="#live_out_Catering_Modal">
                        <img src="out-cater.jpg" class="card-img" alt="Live-Out Package">
                        <div class="card-img-overlay d-flex flex-column justify-content-end bg-dark bg-opacity-50 p-3">
                            <h5 class="card-title text-white fw-bold">Live-Out Package</h5>
                        </div>
                    </div>
                </div>
                <!-- More cards if needed -->
            </div>
        </div>
        <div class="container mt-3 order_container">
            <div class="fw-bold order_header mt-2">Your Order</div>
            <div id="orderList" class="order-cards-container"></div>
        </div>

    </div>
    <div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderModalLabel">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="orderForm">
                        <div class="mb-3">
                            <label for="orderDate" class="form-label">Date</label>
                            <input type="date" class="form-control" id="orderDate" name="orderDate" required>
                        </div>
                        <div class="mb-3">
                            <label for="orderActivity" class="form-label">Activity</label>
                            <input type="text" class="form-control" id="orderActivity" name="orderActivity" placeholder="Enter Activity" required>
                        </div>
                        <div class="mb-3">
                            <label for="orderPoItb" class="form-label">P.O / ITB No.</label>
                            <input type="text" class="form-control" id="orderPoItb" name="orderPoItb" placeholder="Enter P.O / ITB" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Generate Order Slip</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="heavy_snacks_Modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Heavy Snacks Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <!-- First Card -->
                        <div class="col-6">
                            <div class="card card-custom mb-3 clickable-card" style=" border-radius: 20px ;">
                                <div class="card-body">
                                    <h5 class="card-title">₱230.00/Head</h5>
                                    <div id="cat_details">
                                        <p>Sliced Bread (2 Slices)</p>
                                        <p>Buttered Chicken</p>
                                        <p>Bam-i Guisado</p>
                                        <p>Soft Drinks Sakto or Bottled Water</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card card-custom mb-3 clickable-card" style=" border-radius: 20px ;">
                                <div class="card-body">
                                    <h5 class="card-title">₱250.00/Head</h5>
                                    <div id="cat_details">
                                        <p>Fried Bread (2 Slices)</p>
                                        <p>Buttered Chicken</p>
                                        <p>Spaghetti or Bam-i Guisado</p>
                                        <p>Soft Drinks Sakto or Bottled Water</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h5 class="Choices-title" style="text-align: left;">Choices</h5>
                    </div>
                    <div class="row g-2">
                        <!-- First Dropdown -->
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-3">
                                <select class="form-select" id="inputGroupSelectSpaghettiBami">
                                    <option selected disabled>Choose Spaghetti or Bam-i...</option>
                                    <option value="1">Spaghetti</option>
                                    <option value="2">Bam-i Guisado</option>
                                </select>
                            </div>
                        </div>

                        <!-- Second Dropdown -->
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-3">
                                <select class="form-select" id="inputGroupSelectDrinks">
                                    <option selected disabled>Choose Soft Drinks (Sakto) or Bottled Water...</option>
                                    <option value="1">Soft Drinks (Sakto)</option>
                                    <option value="2">Bottled Water</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="input-group input-group-sm col-6 mb-6 ">
                        <span class="input-group-text" id="inputGroup-sizing-sm">No. of Pax</span>
                        <input type="text" class="form-control" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-sm">
                    </div>
                    <div class="notebody">
                        <h5 class="note-title">Note</h5>
                        <div id="note_details">
                            <p>Review your order</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="row g-2 d-flex justify-content-between align-items-center w-100">
                            <div class="col-6">
                                <div class="paxbody">
                                    <div id="pax_details">
                                        <p>price</p>
                                    </div>
                                    <h5 class="pax-title"></h5>
                                </div>
                            </div>
                            <div class="col-6 d-flex justify-content-end">
                                <button type="button" class="btn btn-checkout" id="checkout-btn">Add to Cart</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="pack_plate_Modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Live in Package Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <!-- First Card -->
                        <div class="col-6">
                            <div class="card card-custom mb-3 clickable-card" style="border-radius: 20px;">
                                <div class="card-body">
                                    <h5 class="card-title">₱275.00/Head</h5>
                                    <div id="cat_details">
                                        <p>Plain Rice</p>
                                        <p>Chicken</p>
                                        <p>Pork</p>
                                        <p>Dessert</p>
                                        <p>Soft Drinks Sakto</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card card-custom mb-3 clickable-card" style="border-radius: 20px;">
                                <div class="card-body">
                                    <h5 class="card-title">₱330.00/Head</h5>
                                    <div id="cat_details">
                                        <p>Plain Rice</p>
                                        <p>Chicken</p>
                                        <p>Pork</p>
                                        <p>Vegetable or Noodles</p>
                                        <p>Dessert</p>
                                        <p>Soft Drinks Sakto or Bottled Water</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Choices Text above Dropdown -->
                    <div class="mb-3">
                        <h5 class="Choices-title" style="text-align: left;">Choices</h5>
                    </div>
                    <div class="row g-2">
                        <!-- First Dropdown -->
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-2">
                                <select class="form-select" id="pork_menu_select">
                                    <option selected>Choose Pork...</option>
                                    <option value="1">Pork Salciado</option>
                                    <option value="2">Pork w/ Sweet & Sour</option>
                                    <option value="3">Pork Calderitas</option>
                                    <option value="4">Pork Afritada</option>
                                    <option value="5">Lumpia Shanghai</option>
                                </select>
                            </div>
                        </div>

                        <!-- Second Dropdown -->
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-2">
                                <select class="form-select" id="vegetable_menu_select">
                                    <option selected>Choose Vegetables...</option>
                                    <option value="1">Pinakbet</option>
                                    <option value="2">Chopsuey Guisado</option>
                                    <option value="3">Mixed Vegetables</option>
                                </select>
                            </div>
                        </div>

                        <!-- Third Dropdown (Chicken) -->
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-2">
                                <select class="form-select" id="chicken_menu_select">
                                    <option selected>Choose Chicken...</option>
                                    <option value="1">Buttered Chicken</option>
                                    <option value="2">Chicken Adobo</option>
                                    <option value="3">Chicken w/ Sweet & Sour</option>
                                    <option value="4">Chicken Salciado</option>
                                    <option value="5">Chicken Calderitas</option>
                                    <option value="6">Chicken Afritada </option>
                                </select>
                            </div>
                        </div>

                        <!-- Fourth Dropdown (Noodles) -->
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-2">
                                <select class="form-select" id="noodle_menu_select">
                                    <option selected>Choose Noodles...</option>
                                    <option value="1">Bam-i Guisado</option>
                                    <option value="2">Sotanghon Guisado</option>
                                    <option value="3">Bihon Guisado</option>
                                    <option value="4">Pancit Canton</option>
                                </select>
                            </div>
                        </div>

                        <!-- Fifth Dropdown (Dessert) -->
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-2">
                                <select class="form-select" id="dessert_menu_select">
                                    <option selected>Choose Dessert...</option>
                                    <option value="1">Maja Blanca</option>
                                    <option value="2">Brownies</option>
                                    <option value="3">Macaroons</option>
                                    <option value="4">Pinapple Cream</option>
                                </select>
                            </div>
                        </div>

                        <!-- Sixth Dropdown (Drinks) -->
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-3">
                                <select class="form-select" id="drinks_menu_select">
                                    <option selected>Choose Sakto or Bottled Water...</option>
                                    <option value="1">SoftDrinks (Sakto)</option>
                                    <option value="2">Bottled Water</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Pax Input Field -->
                    <div class="input-group input-group-sm col-6 mb-4">
                        <span class="input-group-text" id="inputGroup-sizing-sm">No. of Pax</span>
                        <input type="text" class="form-control" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-sm">
                    </div>

                    <!-- Note Section -->
                    <div class="notebody">
                        <h5 class="note-title">Note</h5>
                        <div id="note_details">
                            <p>Review your order</p>
                        </div>
                    </div>

                    <!-- Footer Section -->
                    <div class="modal-footer">
                        <div class="row g-2 d-flex justify-content-between align-items-center w-100">
                            <div class="col-6">
                                <div class="paxbody">
                                    <div id="pax_details">
                                        <p>price</p>
                                    </div>
                                    <h5 class="pax-title"></h5>
                                </div>
                            </div>
                            <div class="col-6 d-flex justify-content-end">
                                <button type="button" class="btn btn-checkout" id="checkout-btn1">Add to Cart</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="outCatering_Modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Out Catering Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <!-- First Card -->
                        <div class="col-6">
                            <div class="card card-custom mb-1 clickable-card" style=" border-radius: 20px ;">
                                <div class="card-body">
                                    <h5 class="card-title">₱400.00/Head</h5>
                                    <div id="cat_details">
                                        <p>Plain Rice</p>
                                        <p>Chicken</p>
                                        <p>Fish</p>
                                        <p>Vegetables</p>
                                        <p>Noodles</p>
                                        <p>Dessert</p>
                                        <p>Ice Tea Or SoftDrinks in Glass</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card card-custom mb-1 clickable-card" style=" border-radius: 20px ;">
                                <div class="card-body">
                                    <h5 class="card-title">₱450.00/Head</h5>
                                    <div id="cat_details">
                                        <p>Plain Rice</p>
                                        <p>Pork</p>
                                        <p>Chicken</p>
                                        <p>Fish</p>
                                        <p>Vegetables or Noodles</p>
                                        <p>Dessert</p>
                                        <p>Ice Tea Or SoftDrinks in Glass</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card card-custom mb-4 clickable-card" style=" border-radius: 20px ;">
                                <div class="card-body">
                                    <h5 class="card-title">₱500.00/Head</h5>
                                    <div id="cat_details">
                                        <p>Plain Rice</p>
                                        <p>Pork</p>
                                        <p>Chicken</p>
                                        <p>Fish</p>
                                        <p>Vegetables</p>
                                        <p>Noodles</p>
                                        <p>Dessert</p>
                                        <p>Ice Tea Or SoftDrinks in Glass</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card card-custom mb-4 clickable-card" style=" border-radius: 20px ;">
                                <div class="card-body">
                                    <h5 class="card-title">₱550.00/Head</h5>
                                    <div id="cat_details">
                                        <p>Plain Rice</p>
                                        <p>Pork</p>
                                        <p>Chicken</p>
                                        <p>Fish</p>
                                        <p>Beef</p>
                                        <p>Vegetables or Noodles</p>
                                        <p>Dessert</p>
                                        <p>Ice Tea Or SoftDrinks in Glass</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h5 class="Choices-title" style="text-align: left;">Choices</h5>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="rice_menu_select">
                                    <option selected>Choose Rice...</option>
                                    <option value="1">Plain Rice</option>
                                </select>
                            </div>
                        </div>
                        <!-- Second Dropdown -->
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="drinks_menu_select">
                                    <option selected>Choose Iced Tea or SoftDrinks...</option>
                                    <option value="1">SoftDrinks in Glass</option>
                                    <option value="2">Iced Tea</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="pork_menu_select">
                                    <option selected>Choose Pork...</option>
                                    <option value="1">Pork with Sweet and Sour Sauce</option>
                                    <option value="2">Pork Afritada</option>
                                    <option value="3">Pork Calderita</option>
                                    <option value="4">Pork Shanghai</option>
                                    <option value="5">Fried Meatballs w/ Sweet n Sour</option>
                                    <option value="6">Pork Salciado</option>
                                    <option value="7">Pork Menudo</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="chicken_menu_select">
                                    <option selected>Choose Chicken...</option>
                                    <option value="1">Buttered Chicken</option>
                                    <option value="2">Chicken Kalderita</option>
                                    <option value="3">Jjs Chicken</option>
                                    <option value="4">Chicken Hawaiian</option>
                                    <option value="5">Fried Chicken</option>
                                    <option value="6">Chicken w/ Sweet n Sour</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="beef_menu_select">
                                    <option selected>Choose Beef...</option>
                                    <option value="1">Beef Calderita</option>
                                    <option value="2">Beef Ampalaya</option>
                                    <option value="3">Beef Steak Boholano</option>
                                    <option value="4">Beef w/ Oyster Sauce</option>
                                    <option value="5">Beef Afritada </option>
                                    <option value="6">Beef w/ Brocolli</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="fish_menu_select">
                                    <option selected>Choose Fish...</option>
                                    <option value="1">Fish Fillet w/ Sweet n Sour</option>
                                    <option value="2">Fish Fillet w/ Toase Sauce</option>
                                    <option value="3">Fish Fillet w/ Cream Sauce</option>
                                    <option value="4">Fish Fillet w/ Escabeche Sauce</option>
                                    <option value="5">Fish Fillet Tempura</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="vegetable_menu_select">
                                    <option selected>Choose Vegetable...</option>
                                    <option value="1">Chopsuey</option>
                                    <option value="2">Mixed Vegetables in Oyster Sauce</option>
                                    <option value="3">Buttered Mixed Vegetable</option>
                                    <option value="4">Fresh Lumpia w/ Peanut Sauce</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="noodle_menu_select">
                                    <option selected>Choose Noodles...</option>
                                    <option value="1">Bam-i Guisado</option>
                                    <option value="2">Bihon Guisado</option>
                                    <option value="3">Pancit Canton</option>
                                    <option value="4">Sotanghon Guisado</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-3">
                                <select class="form-select" id="dessert_menu_select">
                                    <option selected>Choose Dessert...</option>
                                    <option value="1">Brownies</option>
                                    <option value="2">Maja Blanca</option>
                                    <option value="3">Pineapple Cream</option>
                                    <option value="4">Mango Float</option>
                                    <option value="5">Yema Cake</option>
                                    <option value="6">Chocolate Cake</option>
                                    <option value="7">Macaroons</option>
                                    <option value="8">Leche Flan</option>
                                    <option value="9">Cookies in Cream</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="input-group input-group-sm col-6 mb-6 ">
                        <span class="input-group-text" id="inputGroup-sizing-sm">No. of Pax</span>
                        <input type="text" class="form-control" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-sm">
                    </div>

                    <div class="notebody">
                        <h5 class="note-title">Note</h5>
                        <div id="note_details">
                            <p>Inclusions: Buffet Table with Skirting, Tables, Chairs Utensils, Portable Water Dispenser with Water</p>
                            <p>Additional Charges: Waiter - P250.00 each and Transpo : P50.00/Km if Outside Tagbilaran</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="row g-2 d-flex justify-content-between align-items-center w-100">
                            <div class="col-6">
                                <div class="paxbody">
                                    <div class="pax_details">
                                        <p>price</p>
                                    </div>
                                    <h5 class="pax-title"></h5>
                                </div>
                            </div>
                            <div class="col-6 d-flex justify-content-end">
                                <button type="button" class="btn btn-checkout" id="checkout-btn2">Add to Cart</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="inCatering_Modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Live in Package Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <!-- First Card -->
                        <div class="col-8">
                            <div class="card card-custom mb-3 clickable-card" style=" border-radius: 20px ;">
                                <div class="card-body">
                                    <h5 class="card-title">₱1,850.00/Head</h5>
                                    <div id="cat_details">
                                        <p>Break Fast = ₱250.00</p>
                                        <p>Lunch = ₱350.00</p>
                                        <p>Dinner = ₱350.00</p>
                                        <p>AM/PM Snacks = ₱150.00</p>
                                        <p>Accomodation = ₱750.00(Triple Sharing)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h5 class="Choices-title" style="text-align: left;">Choices</h5>
                    </div>
                    <div class="row g-2">
                        <div class="mb-0">
                            <h5 class="note-title" style="font-size: .8rem;">BreakFast Menu</h5>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="breakfast_rice_select">
                                    <option selected>Choose Rice...</option>
                                    <option value="1">Plain Rice</option>
                                    <option value="2">Fried Rice</option>
                                </select>
                            </div>
                        </div>

                        <!-- Second Dropdown -->
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="egg_select">
                                    <option selected>Choose Egg...</option>
                                    <option value="1">Scrambled Egg</option>
                                    <option value="2">Fried Egg</option>
                                    <option value="3">Hard Boiled Egg</option>
                                    <option value="4">Ampalaya</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="breakfast_meat_select">
                                    <option selected>Choose Longanisa...</option>
                                    <option value="1">Longanisa</option>
                                    <option value="2">Pork Tocino</option>
                                    <option value="3">Corn Beef</option>
                                    <option value="4">Paksiw na Isda</option>
                                    <option value="5">Sausage</option>
                                    <option value="6">Fried Bangus</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="fruit_select">
                                    <option selected>Choose Fruit...</option>
                                    <option value="1">Fresh Melon</option>
                                    <option value="2">Banana</option>
                                    <option value="3">Pineapple</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-3">
                                <select class="form-select" id="breakfast_drinks_select">
                                    <option selected>Choose Drinks...</option>
                                    <option value="1">Coffee with Cream</option>
                                    <option value="2">Hot Choco</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-0">
                            <h5 class="note-title" style="font-size: .8rem;">Lunch Menu</h5>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="lunch_rice_select">
                                    <option selected>Choose Rice...</option>
                                    <option value="1">Plain Rice</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="soup_select">
                                    <option selected>Choose Soup...</option>
                                    <option value="1">Nilaw-uy</option>
                                    <option value="2">Tinunoang Sari-saring Gulay</option>
                                    <option value="3">Tinola</option>
                                    <option value="4">Sinigang na Manok</option>
                                    <option value="5">Nilaga</option>
                                    <option value="6">Sinigang na Buto-buto ng Baboy</option>
                                    <option value="7">Bicol Express Soup</option>
                                    <option value="8">Chicken Sotanghon Soup</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="lunch_chicken_select">
                                    <option selected>Choose Chicken...</option>
                                    <option value="1">Chicken Adobo Binisaya</option>
                                    <option value="2">Chicken Kalderita</option>
                                    <option value="3">Chicken Ginataan</option>
                                    <option value="4">Chicken Halang-Halang</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="lunch_pork_select">
                                    <option selected>Choose Pork...</option>
                                    <option value="1">Pork Humba</option>
                                    <option value="2">Sinugbang Baboy</option>
                                    <option value="3">Pork with SSS</option>
                                    <option value="4">Breaded Pork Chop</option>
                                    <option value="5">Pork Kalderita</option>
                                    <option value="6">Pork Kalderita</option>
                                    <option value="7">Pork Salciado</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="lunch_dessert_select">
                                    <option selected>Choose Dessert...</option>
                                    <option value="1">Maja Blanca</option>
                                    <option value="2">Pineapple Cream</option>
                                    <option value="3">Macaroons</option>
                                    <option value="4">Cookies in Cream</option>
                                    <option value="5">Brownies</option>
                                    <option value="6">Watermelon Slice</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="lunch_fish_select">
                                    <option selected>Choose Fish/Seafood...</option>
                                    <option value="1">Fried Fish Ordinary w/ Escabeche Sauce</option>
                                    <option value="2">Sinugbang Isda</option>
                                    <option value="3">Inun-unang Isda</option>
                                    <option value="4">Adobong Nukos w/ata</option>
                                    <option value="5">Tuna Fish Tempura</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="lunch_vegetable_select">
                                    <option selected>Choose Vegetables...</option>
                                    <option value="1">Pinakbet</option>
                                    <option value="2">Steamed Mixed Vegetables</option>
                                    <option value="3">Ginataang Langka/Dried Fish</option>
                                    <option value="4">Chopsuey Guisado</option>
                                    <option value="5">Ginisang Ampalaya w/ Ground Pork</option>
                                    <option value="6">Buttered Mixed Corn</option>
                                    <option value="7">Ginisang Sayote w/ Ground Pork</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="lunch_noodles_select">
                                    <option selected>Choose Noodles...</option>
                                    <option value="1">Bihon Guisado</option>
                                    <option value="2">Bam-i Guisado</option>
                                    <option value="3">Pancit Canton</option>
                                    <option value="4">Sotanghon Guisado</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="lunch_ensalada_select">
                                    <option selected>Choose Ensalada...</option>
                                    <option value="1">Ensaladang Talong</option>
                                    <option value="2">Ensaladang Pipino</option>
                                    <option value="3">Ensaladang Manga w/ Bagoong</option>
                                    <option value="4">Kilawin Guso</option>
                                    <option value="5">Ensaladang Labanos</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="lunch_drinks_select">
                                    <option selected>Choose Drinks...</option>
                                    <option value="1">SoftDrinks Sakto</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-0">
                            <h5 class="note-title" style="font-size: .8rem;">AM Snacks Menu</h5>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="am_snacks_select">
                                    <option selected>Choose Am Snacks...</option>
                                    <option value="1">Torta</option>
                                    <option value="2">Ensaymada</option>
                                    <option value="3">Tuna Bread</option>
                                    <option value="4">Meat Bread</option>
                                    <option value="5">Cinammon Roll</option>
                                    <option value="6">German Bread</option>
                                    <option value="7">Puto Cheese</option>
                                    <option value="8">Raisin Bread</option>
                                    <option value="9">Ham Bread</option>
                                    <option value="10">Banana Cake</option>
                                    <option value="11">Puto Maya and Sikwate</option>
                                    <option value="12">Francis Bread and Binignit</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="snacks_drinks_select">
                                    <option selected>Choose Juices...</option>
                                    <option value="1">Iced Tea</option>
                                    <option value="2">Orange Juice</option>
                                    <option value="3">Mango Juice</option>
                                    <option value="4">Fresh Buko Juice</option>
                                    <option value="5">Calamansi Juice</option>
                                    <option value="6">Pineapple Juice</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-0">
                            <h5 class="note-title" style="font-size: .8rem;">PM Snacks Menu</h5>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="pm_snacks_select">
                                    <option selected>Choose Pm Snacks...</option>
                                    <option value="1">Torta</option>
                                    <option value="2">Ensaymada</option>
                                    <option value="3">Tuna Bread</option>
                                    <option value="4">Meat Bread</option>
                                    <option value="5">Cinammon Roll</option>
                                    <option value="6">German Bread</option>
                                    <option value="7">Puto Cheese</option>
                                    <option value="8">Raisin Bread</option>
                                    <option value="9">Ham Bread</option>
                                    <option value="10">Banana Cake</option>
                                    <option value="11">Puto Maya and Sikwate</option>
                                    <option value="12">Francis Bread and Binignit</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-3">
                                <select class="form-select" id="snacks_drinks_select">
                                    <option selected>Choose Juices...</option>
                                    <option value="1">Iced Tea</option>
                                    <option value="2">Orange Juice</option>
                                    <option value="3">Mango Juice</option>
                                    <option value="4">Fresh Buko Juice</option>
                                    <option value="5">Calamansi Juice</option>
                                    <option value="6">Pineapple Juice</option>
                                </select>
                            </div>
                        </div>
                        <div class="input-group input-group-sm col-6 mb-6 ">
                            <span class="input-group-text" id="inputGroup-sizing-sm">No. of Pax</span>
                            <input type="text" class="form-control" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-sm">
                        </div>
                    </div>
                    <div class="notebody">
                        <h5 class="note-title">Note</h5>
                        <div id="note_details">
                            <p>Inclusions:</p>
                            <p>Free Flowing Coffee, Free Use of Swimming Pool</p>
                            <p>Free Use of Function Room w/ Sound System and Mic</p>
                            <p>Stand-by Waiter</p>
                            <p>Fully Air-Conditioned Room w/own Bathroom, Comfort Room and Cable Tv</p>
                            <p>Internet Available</p>
                            <p>Stand By Hot and Cold Purified Drinking Water</p>
                            <p>24hrs Stand By ATS Generator</p>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="row g-2 d-flex justify-content-between align-items-center w-100">
                        <div class="col-6">
                            <div class="paxbody">
                                <div id="pax_details">
                                    <p>price</p>
                                </div>
                                <h5 class="pax-title"></h5>
                            </div>
                        </div>
                        <div class="col-6 d-flex justify-content-end">
                            <button type="button" class="btn btn-checkout" id="checkout-btn3">Add to Cart</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    </div>
    <div class="modal fade" id="live_out_Catering_Modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Live Out Package Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <!-- First Card -->
                        <div class="col-6">
                            <div class="card card-custom mb-3 clickable-card" style=" border-radius: 20px ;">
                                <div class="card-body">
                                    <h5 class="card-title">₱550.00/Head</h5>
                                    <div id="cat_details">
                                        <p>Plain Rice</p>
                                        <p>Soup</p>
                                        <p>Pork or Chicken</p>
                                        <p>Fish</p>
                                        <p>Vegetables or Noodles</p>
                                        <p>Ensalada</p>
                                        <p>Dessert</p>
                                        <p>SoftDrinks 1 Round</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card card-custom mb-3 clickable-card" style=" border-radius: 20px ;">
                                <div class="card-body">
                                    <h5 class="card-title">₱600.00/Head</h5>
                                    <div id="cat_details">
                                        <p>Plain Rice</p>
                                        <p>Soup</p>
                                        <p>Pork</p>
                                        <p>Chicken</p>
                                        <p>Fish</p>
                                        <p>Vegetables or Noodles</p>
                                        <p>Ensalada</p>
                                        <p>Dessert</p>
                                        <p>SoftDrinks 1 Round</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h5 class="Choices-title" style="text-align: left;">Choices</h5>
                    </div>
                    <div class="row g-2">
                        <!-- First Dropdown -->
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="live_out_rice_select">
                                    <option selected>Choose Rice...</option>
                                    <option value="1">Plain Rice</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="live_out_soup_select">
                                    <option selected>Choose Soup...</option>
                                    <option value="1">Tinola</option>
                                    <option value="2">Sinigang na Buto-buto ng Baboy</option>
                                    <option value="3">Chicken Sotanghon</option>
                                    <option value="4">Sinigang na Manok</option>
                                    <option value="5">Nilaw Uy</option>
                                    <option value="6">Tinunoang Sari saring Gulay</option>
                                </select>
                            </div>
                        </div>
                        <!-- Second Dropdown -->
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="live_out_fish_select">
                                    <option selected>Choose Fish...</option>
                                    <option value="1">Fish Fillet w/ Sweet & Sour</option>
                                    <option value="2">Fish Fillet w/ Taose Sauce</option>
                                    <option value="3">Fried Fish w/ Sweet & Sour</option>
                                    <option value="4">Fried Fish w/ Escabeche Sauce</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="live_out_chicken_select">
                                    <option selected>Choose Chicken...</option>
                                    <option value="1">Chicken Halang-Halang</option>
                                    <option value="2">Chicken Ginataan</option>
                                    <option value="3">Chicken w/ Sweet & Sour</option>
                                    <option value="4">Chicken Afritada</option>
                                    <option value="5">Chicken Kalderita</option>
                                    <option value="6">Buttered Chicken</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="live_out_pork_select">
                                    <option selected>Choose Pork...</option>
                                    <option value="1">Pork Kalderita</option>
                                    <option value="2">Pork w/ Sweet & Sour</option>
                                    <option value="3">Pork Salciado</option>
                                    <option value="4">Fired Meatballs w/ SSS</option>
                                    <option value="5">Lumpia Shanghai</option>
                                    <option value="6">Pork Menudo</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="live_out_noodles_select">
                                    <option selected>Choose Noodles...</option>
                                    <option value="1">Bihon Guisado</option>
                                    <option value="2">Bam-i Guisado</option>
                                    <option value="3">Sotanghon Guisado</option>
                                    <option value="4">Pancit Canton</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="live_out_ensalada_select">
                                    <option selected>Choose Ensalada...</option>
                                    <option value="1">Ensaladang Talong</option>
                                    <option value="2">Ensaladang Pipino</option>
                                    <option value="3">Ensaladang Manga w/ Bagoong</option>
                                    <option value="4">Kilawin Guso</option>
                                    <option value="5">Ensaladang Labanos</option>
                                    <option value="6">Ensaladang Ampalaya</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-3">
                                <select class="form-select" id="live_out_dessert_select">
                                    <option selected>Choose Dessert...</option>
                                    <option value="1">Maja Blanca</option>
                                    <option value="2">Pineapple Cream</option>
                                    <option value="3">Fruit Cake</option>
                                    <option value="4">Old Chocolate Cake</option>
                                    <option value="5">Brownies</option>
                                    <option value="6">Yema Cake</option>
                                    <option value="7">Macaroons</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-0">
                            <h5 class="note-title" style="font-size: .8rem;">AM Snacks Menu</h5>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="live_out_am_snacks_select">
                                    <option selected>Choose Am Snacks...</option>
                                    <option value="1">Torta</option>
                                    <option value="2">Ensaymada</option>
                                    <option value="3">Tuna Bread</option>
                                    <option value="4">Meat Bread</option>
                                    <option value="5">Cinammon Roll</option>
                                    <option value="6">German Bread</option>
                                    <option value="7">Puto Cheese</option>
                                    <option value="8">Raisin Bread</option>
                                    <option value="9">Ham Bread</option>
                                    <option value="10">Banana Cake</option>
                                    <option value="11">Puto Maya and Sikwate</option>
                                    <option value="12">Francis Bread and Binignit</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-1">
                                <select class="form-select" id="live_out_drinks_select">
                                    <option selected>Choose Juices...</option>
                                    <option value="1">Iced Tea</option>
                                    <option value="2">Orange Juice</option>
                                    <option value="3">Mango Juice</option>
                                    <option value="4">Fresh Buko Juice</option>
                                    <option value="5">Calamansi Juice</option>
                                    <option value="6">Pineapple Juice</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-0">
                            <h5 class="note-title" style="font-size: .8rem;">PM Snacks Menu</h5>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-3">
                                <select class="form-select" id="live_out_pm_snacks_select">
                                    <option selected>Choose Pm Snacks...</option>
                                    <option value="1">Torta</option>
                                    <option value="2">Ensaymada</option>
                                    <option value="3">Tuna Bread</option>
                                    <option value="4">Meat Bread</option>
                                    <option value="5">Cinammon Roll</option>
                                    <option value="6">German Bread</option>
                                    <option value="7">Puto Cheese</option>
                                    <option value="8">Raisin Bread</option>
                                    <option value="9">Ham Bread</option>
                                    <option value="10">Banana Cake</option>
                                    <option value="11">Puto Maya and Sikwate</option>
                                    <option value="12">Francis Bread and Binignit</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 dropdown_men">
                            <div class="input-group mb-3">
                                <select class="form-select" id="live_out_drinks_select">
                                    <option selected>Choose Juices...</option>
                                    <option value="1">Iced Tea</option>
                                    <option value="2">Orange Juice</option>
                                    <option value="3">Mango Juice</option>
                                    <option value="4">Fresh Buko Juice</option>
                                    <option value="5">Calamansi Juice</option>
                                    <option value="6">Pineapple Juice</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="input-group input-group-sm col-6 mb-6 ">
                        <span class="input-group-text" id="inputGroup-sizing-sm">No. of Pax</span>
                        <input type="text" class="form-control" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-sm">
                    </div>

                    <div class="notebody">
                        <h5 class="note-title">Note</h5>
                        <div id="note_details">
                            <p>Inclusions:</p>
                            <p>Free Use of Function Room</p>
                            <p>Free Use of Function Room w/ Sound System and Mic</p>
                            <p>Stand-by Waiter</p>
                            <p>Fax and Internet Available</p>
                            <p>Stand By Purified Drinking Water</p>
                            <p>24hrs Stand By ATS Generator</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="row g-2 d-flex justify-content-between align-items-center w-100">
                            <div class="col-6">
                                <div class="paxbody">
                                    <div id="pax_details">
                                        <p>price</p>
                                    </div>
                                    <h5 class="pax-title"></h5>
                                </div>
                            </div>
                            <div class="col-6 d-flex justify-content-end">
                                <button type="button" class="btn btn-checkout" id="checkout-btn4">Add to Cart</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="statusErrorsModal" tabindex="-1" role="dialog" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-body text-center p-lg-4">
                    <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">
                        <circle class="path circle" fill="none" stroke="#db3646" stroke-width="6" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1" />
                        <line class="path line" fill="none" stroke="#db3646" stroke-width="6" stroke-linecap="round" stroke-miterlimit="10" x1="34.4" y1="37.9" x2="95.8" y2="92.3" />
                        <line class="path line" fill="none" stroke="#db3646" stroke-width="6" stroke-linecap="round" stroke-miterlimit="10" x1="95.8" y1="38" X2="34.4" y2="92.2" />
                    </svg>
                    <h4 class="text-danger mt-3">Invalid email!</h4>
                    <p class="mt-3">This email is already registered, please login.</p>
                    <button type="button" class="btn btn-sm mt-3 btn-danger" data-bs-dismiss="modal">Ok</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="statusSuccessModal" tabindex="-1" role="dialog" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-body text-center p-lg-4">
                    <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">
                        <circle class="path circle" fill="none" stroke="#198754" stroke-width="6" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1" />
                        <polyline class="path check" fill="none" stroke="#198754" stroke-width="6" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 " />
                    </svg>
                    <h4 class="text-success mt-3">Oh Yeah!</h4>
                    <p class="mt-3">You have successfully added to cart.</p>
                    <button type="button" class="btn btn-sm mt-3 btn-success" data-bs-dismiss="modal">Ok</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Order Now Modal -->
    <div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderModalLabel">Your Order Summary</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Dynamically populated order details will go here -->
                    <p>No items in the cart.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="confirm-order-btn">Confirm Order</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="jjs.js"></script>
</body>

</html>