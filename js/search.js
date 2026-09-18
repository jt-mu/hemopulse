function searchCampaign() {
  const searchInput = document.getElementById("searchCampaign");
  if (!searchInput) return;

  const query = searchInput.value.trim().toLowerCase();
  const cards = document.querySelectorAll(".campaign-card");

  cards.forEach(card => {
    const cardText = card.textContent.toLowerCase();
    
    card.style.display = cardText.includes(query) ? "" : "none";
  });
}