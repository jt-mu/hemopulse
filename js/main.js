document.addEventListener("DOMContentLoaded", () => {
  const faqItems = document.querySelectorAll(".faq-item");

  faqItems.forEach((item) => {
    const questionBtn = item.querySelector(".faq-question");
    questionBtn.addEventListener("click", () => {
      const isOpen = item.classList.contains("open");

      // Close other accordions
      faqItems.forEach((i) => i.classList.remove("open"));

      // If it wasn't open before, open it
      if (!isOpen) {
        item.classList.add("open");
      }
    });
  });
});