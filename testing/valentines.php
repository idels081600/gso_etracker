<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Happy Valentine’s 💖</title>

<link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">

<style>
/* =====================
   GLOBAL STYLES
===================== */
* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
  font-family: 'Pacifico', cursive;
}

body {
  height: 100vh;
  background: linear-gradient(135deg, #ff7eb3, #ff3d3d);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

/* =====================
   FLOATING HEARTS
===================== */
.heart {
  position: absolute;
  bottom: -20px;
  color: rgba(255, 255, 255, 0.6);
  font-size: 20px;
  animation: floatUp 8s linear infinite;
  z-index: 0;
}

@keyframes floatUp {
  0% { transform: translateY(0) scale(1); opacity: 0; }
  20% { opacity: 1; }
  100% { transform: translateY(-110vh) scale(1.5); opacity: 0; }
}

/* =====================
   CARD
===================== */
.card {
  background: rgba(255, 255, 255, 0.95);
  padding: 40px 30px;
  border-radius: 20px;
  box-shadow: 0 20px 40px rgba(0,0,0,0.2);
  text-align: center;
  max-width: 350px;
  width: 90%;
  transition: opacity 0.8s ease, transform 0.8s ease;
  z-index: 10;
}

.card.hide {
  opacity: 0;
  transform: scale(0.8);
  pointer-events: none;
}

.card h1 {
  font-size: 32px;
  color: #ff3d6b;
  margin-bottom: 30px;
}

/* =====================
   BUTTONS
===================== */
.buttons {
  display: flex;
  justify-content: center;
  gap: 15px;
}

button {
  border: none;
  border-radius: 50px;
  padding: 12px 24px;
  font-size: 18px;
  cursor: pointer;
  transition: all 0.3s ease;
}

#yesBtn {
  background: linear-gradient(135deg, #ff4d79, #ff1e56);
  color: white;
  box-shadow: 0 8px 20px rgba(255, 30, 86, 0.5);
}

#yesBtn:hover {
  transform: scale(1.1);
  box-shadow: 0 12px 25px rgba(255, 30, 86, 0.8);
}

#noBtn {
  background: #f1f1f1;
  color: #555;
  font-size: 16px;
  position: relative;
}

/* =====================
   BOUQUET & COLLAGE VIEW
===================== */
.bouquet {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) scale(0.7);
  text-align: center;
  opacity: 0;
  transition: all 1s ease;
  width: 95%;
  max-width: 600px;
  display: flex;
  flex-direction: column;
  align-items: center;
  z-index: 5;
}

.bouquet.show {
  opacity: 1;
  transform: translate(-50%, -50%) scale(1);
}

/* Photo Collage Styles */
.collage {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  margin-bottom: 20px;
  padding: 10px;
}

.collage img {
  width: 100%;
  height: 120px;
  object-fit: cover;
  border-radius: 10px;
  border: 4px solid white;
  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
  transition: transform 0.3s ease;
}

.collage img:hover {
  transform: scale(1.05) rotate(2deg);
  z-index: 2;
}

/* Give images a slight "scattered" polaroid look */
.collage img:nth-child(even) { transform: rotate(-3deg); }
.collage img:nth-child(odd) { transform: rotate(3deg); }

.roses {
  font-size: 50px;
  animation: bloom 2s ease-in-out infinite alternate;
}

@keyframes bloom {
  from { transform: scale(1); }
  to { transform: scale(1.1); }
}

.message {
  margin-top: 10px;
  font-size: 28px;
  color: white;
  text-shadow: 0 4px 10px rgba(0,0,0,0.3);
}

/* =====================
   PETALS
===================== */
.petal {
  position: absolute;
  top: -10px;
  font-size: 18px;
  animation: petalFall 6s linear infinite;
}

@keyframes petalFall {
  to {
    transform: translateY(110vh) rotate(360deg);
    opacity: 0;
  }
}
</style>
</head>

<body>

<script>
for (let i = 0; i < 20; i++) {
  const heart = document.createElement("div");
  heart.className = "heart";
  heart.textContent = "❤️";
  heart.style.left = Math.random() * 100 + "vw";
  heart.style.animationDuration = 5 + Math.random() * 5 + "s";
  document.body.appendChild(heart);
}
</script>

<div class="card" id="card">
  <h1>Will you be my Valentine?</h1>
  <div class="buttons">
    <button id="yesBtn">Yes 💖</button>
    <button id="noBtn">No 💔</button>
  </div>
</div>

<div class="bouquet" id="bouquet">
  <div class="collage">
    <img src="pics/ravens.jpg" alt="Memories 1">
    <img src="pics/2.jpg" alt="Memories 2">
    <img src="pics/3.jpg" alt="Memories 3">
    <img src="pics/4.jpg" alt="Memories 4">
    <img src="pics/5.jpg" alt="Memories 5">
    <img src="pics/6.jpg" alt="Memories 6">
  </div>
  <div class="roses">🌹🌹🌹</div>
  <div class="message">Happy Valentine’s Day ❤️</div>
</div>

<script>
const yesBtn = document.getElementById("yesBtn");
const noBtn = document.getElementById("noBtn");
const card = document.getElementById("card");
const bouquet = document.getElementById("bouquet");

yesBtn.addEventListener("click", () => {
  card.classList.add("hide");
  setTimeout(() => {
    bouquet.classList.add("show");
    createPetals();
  }, 800);
});

// "No" button escapes on mouse enter (for desktop) or touch (for mobile)
const moveNoBtn = () => {
  const x = Math.random() * (window.innerWidth - noBtn.offsetWidth) - (window.innerWidth / 2 - noBtn.offsetLeft);
  const y = Math.random() * (window.innerHeight - noBtn.offsetHeight) - (window.innerHeight / 2 - noBtn.offsetTop);
  noBtn.style.transform = `translate(${x}px, ${y}px)`;
};

noBtn.addEventListener("mouseenter", moveNoBtn);
noBtn.addEventListener("touchstart", (e) => {
    e.preventDefault(); // Prevents clicking it on mobile
    moveNoBtn();
});

function createPetals() {
  for (let i = 0; i < 25; i++) {
    const petal = document.createElement("div");
    petal.className = "petal";
    petal.textContent = "🌸";
    petal.style.left = Math.random() * 100 + "vw";
    petal.style.animationDuration = 4 + Math.random() * 4 + "s";
    document.body.appendChild(petal);
    setTimeout(() => petal.remove(), 8000);
  }
}
</script>

</body>
</html>