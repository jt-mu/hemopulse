async function loadCampaigns() {
  try {
    const response = await fetch("backend/api/campaigns.php");

    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }

    const result = await response.json();

    if (result.success && Array.isArray(result.data)) {
      displayCampaigns(result.data);
    } else {
      console.error("API returned unsuccessful status or invalid data format:", result);
    }
  } catch (error) {
    console.error("Failed to load campaigns:", error);
  }
}

function displayCampaigns(data) {
  const container = document.getElementById("campaignList");
  if (!container) return;

  if (data.length === 0) {
    container.innerHTML = "<p>No active campaigns available.</p>";
    return;
  }

  const campaignCardsHTML = data.map(campaign => `
    <div class="campaign-card">
      <h3>${escapeHTML(campaign.title)}</h3>
      <p>${escapeHTML(campaign.location_venue)}</p>
      <p>Date: ${escapeHTML(campaign.campaign_date)}</p>
      <p>Available Slots: ${escapeHTML(campaign.available_slots)}</p>
      <button type="button" onclick="registerCampaign(${Number(campaign.campaign_id)})">
        Register
      </button>
    </div>
  `).join("");

  container.innerHTML = campaignCardsHTML;
}

function registerCampaign(id) {
  alert(`Redirecting to appointment page for campaign ID: ${id}`);
}

function escapeHTML(str) {
  if (str == null) return "";
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

loadCampaigns();