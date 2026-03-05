const header = document.querySelector(".site-header");
const nav = document.querySelector(".main-nav");
const toggle = document.querySelector(".mobile-toggle");
const toTop = document.getElementById("toTop");
const yearEl = document.getElementById("year");

if (yearEl) {
  yearEl.textContent = new Date().getFullYear();
}

function onScroll() {
  const y = window.scrollY || document.documentElement.scrollTop;
  header.classList.toggle("scrolled", y > 24);
  if (toTop) toTop.classList.toggle("show", y > 400);
}

window.addEventListener("scroll", onScroll);
onScroll();

if (toggle && nav) {
  toggle.addEventListener("click", () => {
    const open = nav.classList.toggle("open");
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
  });
  document.addEventListener("click", (e) => {
    if (!nav.contains(e.target) && !toggle.contains(e.target)) {
      nav.classList.remove("open");
      toggle.setAttribute("aria-expanded", "false");
    }
  });
}

const links = [...document.querySelectorAll(".nav-link")];
const path = location.pathname.split("/").pop() || "index.html";
links.forEach((a) => {
  const href = a.getAttribute("href");
  if (href === path) a.classList.add("active");
});

if (toTop) {
  toTop.addEventListener("click", () => {
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
}

document
  .querySelectorAll("img[loading='lazy']")
  .forEach((img) => (img.decoding = "async"));

// Lightbox Logic
(function () {
  const lightbox = document.getElementById("certLightbox");
  if (!lightbox) return;

  const lightboxImg = document.getElementById("lightboxImg");
  const closeBtn = document.querySelector(".lightbox-close");
  const overlay = document.querySelector(".lightbox-overlay");
  const prevBtn = document.querySelector(".lightbox-prev");
  const nextBtn = document.querySelector(".lightbox-next");
  const certImages = Array.from(document.querySelectorAll(".cert-img"));

  let currentIndex = 0;

  function openLightbox(index) {
    currentIndex = index;
    updateLightboxImage();
    lightbox.classList.add("is-open");
    document.body.style.overflow = "hidden"; // Prevent background scrolling
  }

  function closeLightbox() {
    lightbox.classList.remove("is-open");
    document.body.style.overflow = "";
    setTimeout(() => {
      if (!lightbox.classList.contains("is-open")) {
        lightboxImg.src = ""; // Clear src to stop loading/displaying
      }
    }, 300); // Wait for fade out if we had transitions, but safe to clear
  }

  function updateLightboxImage() {
    if (currentIndex < 0) currentIndex = certImages.length - 1;
    if (currentIndex >= certImages.length) currentIndex = 0;

    const img = certImages[currentIndex];
    // Use data-full if available, otherwise src
    const src = img.getAttribute("data-full") || img.src;
    lightboxImg.src = src;
    lightboxImg.alt = img.alt;
  }

  // Event Listeners for opening
  certImages.forEach((img, index) => {
    img.addEventListener("click", (e) => {
      e.preventDefault(); // Prevent default if wrapped in link
      openLightbox(index);
    });
  });

  // Event Listeners for closing
  if (closeBtn) closeBtn.addEventListener("click", closeLightbox);
  if (overlay) overlay.addEventListener("click", closeLightbox);

  // Navigation
  if (prevBtn) {
    prevBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      currentIndex--;
      updateLightboxImage();
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      currentIndex++;
      updateLightboxImage();
    });
  }

  // Keyboard support
  document.addEventListener("keydown", (e) => {
    if (!lightbox.classList.contains("is-open")) return;

    if (e.key === "Escape") closeLightbox();
    if (e.key === "ArrowLeft") {
      currentIndex--;
      updateLightboxImage();
    }
    if (e.key === "ArrowRight") {
      currentIndex++;
      updateLightboxImage();
    }
  });
})();
