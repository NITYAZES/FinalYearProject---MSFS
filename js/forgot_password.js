document.addEventListener("DOMContentLoaded", async () => {
 
  try {
    const response = await fetch("./api/get_csrf_token.php", {
      method: "GET",
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });

    const data = await response.json();
    const tokenInput = document.getElementById("csrf_token");
    if (tokenInput && data && data.csrf_token) {
      tokenInput.value = data.csrf_token;
    }
  } catch (error) {
    console.error("Failed to fetch CSRF token:", error);
  }


  const form = document.getElementById("forgotPasswordForm");
  if (!form) return;

  const submitBtn = form.querySelector(".btn-primary");
  if (!submitBtn) return;

  form.addEventListener("submit", () => {
    submitBtn.disabled = true;
    submitBtn.innerHTML = `
      <span class="btn-text">Sending...</span>
      <svg class="btn-spinner" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
        <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
      </svg>
    `;
  });
});
