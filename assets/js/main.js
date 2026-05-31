// ============================================================
// main.js - Portal Ivan Cisneros
// ============================================================

// Navbar: sombra al hacer scroll.
(function () {
  const nav = document.querySelector('nav');
  if (!nav) return;
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 10);
  }, { passive: true });
})();

// Smooth scroll para anclas internas.
document.querySelectorAll('a[href^="#"]').forEach(link => {
  link.addEventListener('click', function (e) {
    const id = this.getAttribute('href').slice(1);
    const target = document.getElementById(id);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
});

// ── Resaltar link activo del navbar al hacer scroll ─────────
(function () {
  const navLinks = document.querySelectorAll('nav a[href*="#"]');
  const sections = Array.from(document.querySelectorAll('section[id]'));
  if (!sections.length || !navLinks.length) return;

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        navLinks.forEach(link => {
          const matches = link.getAttribute('href').includes('#' + entry.target.id);
          link.classList.toggle('text-[#1E3A8A]', matches);
          link.classList.toggle('font-bold', matches);
        });
      }
    });
  }, { threshold: 0.4 });

  sections.forEach(s => observer.observe(s));
})();

// ── Contador regresivo ───────────────────────────────────────
(function () {
  const block = document.getElementById('countdown-block');
  if (!block) return;

  const dateStr = block.dataset.target; // "YYYY-MM-DD"
  // Medianoche hora Peru (UTC-5) = 05:00 UTC
  const target = new Date(dateStr + 'T05:00:00Z').getTime();

  const elDays  = document.getElementById('cd-days');
  const elHours = document.getElementById('cd-hours');
  const elMin   = document.getElementById('cd-min');
  const elSec   = document.getElementById('cd-sec');
  const elLabel = document.getElementById('cd-label');

  function pad(n) { return String(n).padStart(2, '0'); }

  function tick() {
    const now  = Date.now();
    const diff = target - now;

    if (diff <= 0) {
      elDays.textContent  = '00';
      elHours.textContent = '00';
      elMin.textContent   = '00';
      elSec.textContent   = '00';
      if (elLabel) elLabel.textContent = '¡Hoy es el día!';
      return;
    }

    const days  = Math.floor(diff / 86400000);
    const hours = Math.floor((diff % 86400000) / 3600000);
    const min   = Math.floor((diff % 3600000)  / 60000);
    const sec   = Math.floor((diff % 60000)    / 1000);

    elDays.textContent  = pad(days);
    elHours.textContent = pad(hours);
    elMin.textContent   = pad(min);
    elSec.textContent   = pad(sec);
  }

  tick();
  setInterval(tick, 1000);
})();
