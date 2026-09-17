document.addEventListener("DOMContentLoaded", () => {
  lucide.createIcons();

  const menuButton = document.getElementById("menu-button");
  const mobileMenu = document.getElementById("mobile-menu");
  menuButton.addEventListener("click", () => {
    const open = mobileMenu.classList.toggle("hidden") === false;
    menuButton.setAttribute("aria-expanded", String(open));
  });

  mobileMenu.querySelectorAll("a").forEach(link => link.addEventListener("click", () => {
    mobileMenu.classList.add("hidden");
    menuButton.setAttribute("aria-expanded", "false");
  }));

  document.querySelectorAll(".faq-toggle").forEach(button => {
    button.addEventListener("click", () => {
      const item = button.closest(".faq-item");
      const open = item.classList.toggle("open");
      button.setAttribute("aria-expanded", String(open));
    });
  });

  const dateInput = document.getElementById("booking-date");
  const now = new Date();
  const localToday = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  dateInput.min = localToday;

  const serviceSelect = document.getElementById("booking-service");
  document.querySelectorAll(".book-service").forEach(button => {
    button.addEventListener("click", () => {
      serviceSelect.value = button.dataset.service;
      document.getElementById("booking").scrollIntoView({ behavior: "smooth" });
    });
  });

  document.getElementById("certificate-button").addEventListener("click", () => {
    serviceSelect.value = "Подарочный сертификат";
    document.getElementById("booking").scrollIntoView({ behavior: "smooth" });
  });

  const form = document.getElementById("booking-form");
  const error = document.getElementById("booking-error");
  const submit = document.getElementById("booking-submit");
  const loading = document.getElementById("booking-loading");
  const success = document.getElementById("booking-success");
  const summary = document.getElementById("booking-summary");

  form.addEventListener("submit", event => {
    event.preventDefault();
    error.classList.add("hidden-state");

    if (!form.checkValidity() || dateInput.value < localToday) {
      error.classList.remove("hidden-state");
      form.reportValidity();
      return;
    }

    submit.classList.add("hidden-state");
    loading.classList.remove("hidden-state");

    window.setTimeout(() => {
      const date = new Date(dateInput.value + "T12:00:00");
      const formattedDate = new Intl.DateTimeFormat("ru-RU", {
        day: "numeric", month: "long", year: "numeric"
      }).format(date);

      summary.textContent = `${serviceSelect.value} · ${formattedDate} · ${document.getElementById("booking-time").value}`;
      form.classList.add("hidden-state");
      success.classList.remove("hidden-state");
      loading.classList.add("hidden-state");
    }, 700);
  });

  document.getElementById("booking-reset").addEventListener("click", () => {
    form.reset();
    form.classList.remove("hidden-state");
    success.classList.add("hidden-state");
    submit.classList.remove("hidden-state");
    dateInput.min = localToday;
  });

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add("visible");
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });

  document.querySelectorAll(".reveal").forEach(element => observer.observe(element));
});
