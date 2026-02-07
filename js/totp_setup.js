(() => {
  const $ = (sel) => document.querySelector(sel);
  const PROJECT_BASE = "/FinalYearProject/";

  const feedback = $("#feedback-message");
  const qrSection = $("#qr-section");
  const backupCodesSection = $("#backup-codes-section");
  const qrContainer = $("#qr-code");
  const secretKeyDisplay = $("#secret-key-display");
  const backupCodesList = $("#backup-codes-list");

  const verifyForm = $("#verify-form");
  const totpCodeInput = $("#totp-code");
  const verifyBtn = $("#verify-btn");
  const verifyText = $("#verify-text");
  const backToLoginBtn = $("#backToLoginBtn");

  let totpSecret = "";
  let backupCodes = [];

  function showMessage(msg, type = "info") {
    if (!feedback) return;
    feedback.className = `alert alert-${type}`;
    feedback.textContent = msg;
    feedback.style.display = msg ? "block" : "none";
  }

  function setLoading(loading) {
    if (!verifyBtn) return;
    verifyBtn.disabled = loading;
    if (verifyText) verifyText.textContent = loading ? "Verifying..." : "Verify and Enable 2FA";
  }

  function extractOtpauthUriFromQrServerUrl(qrUrl) {
    try {
      const u = new URL(qrUrl);
      const dataParam = u.searchParams.get("data");
      if (!dataParam) return null;
      return decodeURIComponent(dataParam);
    } catch {
      return null;
    }
  }

  function renderQrLocally(otpauthUri) {
    if (!qrContainer) return;

    qrContainer.innerHTML = "";

    if (typeof window.QRCode !== "function") {
      showMessage("QR library failed to load. Please refresh the page.", "error");
      return;
    }

  
    new window.QRCode(qrContainer, {
      text: otpauthUri,
      width: 250,
      height: 250,
      correctLevel: window.QRCode.CorrectLevel.M,
    });
  }

  async function initSetup() {
    try {
      const res = await fetch("./api/totp_init.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
      });

      const raw = await res.text();
      let data;
      try {
        data = JSON.parse(raw);
      } catch (e) {
        console.error("Invalid JSON from totp_init.php:", raw);
        showMessage("Server returned invalid response. Check console.", "error");
        return;
      }

      if (!data.ok) {
        showMessage(data.message || "Failed to initialize 2FA setup", "error");
        return;
      }

      totpSecret = data.secret || "";
      backupCodes = Array.isArray(data.backup_codes) ? data.backup_codes : [];

  
      const otpauthUri = extractOtpauthUriFromQrServerUrl(data.qr_code_url || "");
      if (!otpauthUri) {
        showMessage("Failed to build QR data. Please refresh and try again.", "error");
        return;
      }

      renderQrLocally(otpauthUri);

      if (secretKeyDisplay) secretKeyDisplay.textContent = data.secret_formatted || totpSecret || "";

      if (backupCodesList) {
        if (backupCodes.length === 0) {
          backupCodesList.innerHTML = "<p>No backup codes provided.</p>";
        } else {
          backupCodesList.innerHTML = backupCodes
            .map(
              (code, i) => `
                <div class="backup-code-item">
                  <span class="backup-code-number">${i + 1}.</span>
                  <code class="backup-code">${escapeHtml(code)}</code>
                </div>
              `
            )
            .join("");
        }
      }

      if (qrSection) qrSection.style.display = "block";
      if (backupCodesSection) backupCodesSection.style.display = "block";

      showMessage("Scan the QR code with your authenticator app, then enter the 6-digit code to verify.", "info");
    } catch (err) {
      console.error("Init error:", err);
      showMessage("Unable to initialize 2FA setup: " + (err?.message || "Unknown error"), "error");
    }
  }

  if (verifyForm) {
    verifyForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      showMessage("");

      const code = (totpCodeInput?.value || "").trim();

      if (!/^\d{6}$/.test(code)) {
        showMessage("Please enter a valid 6-digit code", "error");
        return;
      }

      if (!totpSecret) {
        showMessage("Setup error: missing secret. Please refresh and try again.", "error");
        return;
      }

      try {
        setLoading(true);

        const res = await fetch("./api/totp_verify.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "same-origin",
          body: JSON.stringify({ code, secret: totpSecret }),
        });

        const raw = await res.text();
        let data;
        try {
          data = JSON.parse(raw);
        } catch (e) {
          console.error("Invalid JSON from totp_verify.php:", raw);
          showMessage("Server returned invalid response. Check console.", "error");
          return;
        }

        if (!data.ok) {
          showMessage(data.message || "Invalid code. Please try again.", "error");
          if (totpCodeInput) {
            totpCodeInput.value = "";
            totpCodeInput.focus();
          }
          return;
        }

        showMessage("Two-factor authentication enabled successfully! Redirecting to login...", "success");

        if (verifyBtn) verifyBtn.disabled = true;
        if (totpCodeInput) totpCodeInput.disabled = true;

        
        sessionStorage.setItem("totp_setup_complete", "true");

        setTimeout(() => {
          window.location.href = PROJECT_BASE + "index.html";
        }, 1500);
      } catch (err) {
        console.error("Verify error:", err);
        showMessage("Unable to verify code: " + (err?.message || "Unknown error"), "error");
      } finally {
        setLoading(false);
      }
    });
  }

  if (totpCodeInput) {
    totpCodeInput.addEventListener("input", (e) => {
      e.target.value = e.target.value.replace(/\D/g, "").slice(0, 6);
    });
  }

  if (backToLoginBtn) {
    backToLoginBtn.addEventListener("click", () => {
      window.location.href = "index.html";
    });
  }

  function escapeHtml(str) {
    if (str === null || str === undefined) return "";
    return String(str).replace(/[&<>"']/g, (m) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    })[m]);
  }

  void initSetup();
})();
