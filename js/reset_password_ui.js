// Password visibility toggle functionality
function setupPasswordToggle(toggleButton, inputField) {
  if (!toggleButton || !inputField) return;

  const eyeClosed = toggleButton.querySelector(".eye-closed");
  const eyeOpen = toggleButton.querySelector(".eye-open");

  if (!eyeClosed || !eyeOpen) return;

  toggleButton.addEventListener("click", (e) => {
    e.preventDefault();
    const isPassword = inputField.type === "password";
    inputField.type = isPassword ? "text" : "password";
    eyeClosed.style.display = isPassword ? "none" : "block";
    eyeOpen.style.display = isPassword ? "block" : "none";
  });
}

// Password requirements validation
// Implements the SAME password policy used in registration.

// Common password blocklist (same as registration)
const COMMON_PW = new Set([
  "password", "password123", "password@123", "qwerty", "qwerty123",
  "admin", "admin123", "welcome", "letmein", "iloveyou",
  "123456", "12345678", "123456789", "1234567890",
  "abc123", "000000", "111111", "passw0rd", "p@ssw0rd",
]);

// Similarity check: blocks full token AND any 3+ prefix of token.
// Tokens are derived from email/username entered by the user.
function isPasswordTooSimilar(pwd, emailOrUsername) {
  const pwdLower = (pwd || "").toLowerCase().trim();
  if (pwdLower.length < 3) return false;

  const id = (emailOrUsername || "").toLowerCase().trim();
  if (!id) return false;

  const tokens = [];
  if (id.includes("@")) {
    const prefix = (id.split("@")[0] || "").trim();
    if (prefix) tokens.push(prefix);
  }
  tokens.push(id);

  for (const tRaw of tokens) {
    const t = (tRaw || "").trim();
    if (t.length < 3) continue;

    if (pwdLower.includes(t)) return true;

    for (let i = 3; i <= t.length; i++) {
      if (pwdLower.includes(t.slice(0, i))) return true;
    }
  }

  return false;
}

function updatePasswordRequirements() {
  const password = document.getElementById("password").value;
  const confirmPassword = document.getElementById("password_confirmation").value;
  const emailOrUsername = (document.getElementById("emailOrUsername")?.value || "").trim();

  // Length check (12+ characters)
  const lengthMet = password.length >= 12;
  const reqLength = document.getElementById("req-length");
  reqLength.classList.toggle("met", lengthMet);
  reqLength.querySelector(".icon-circle").style.display = lengthMet ? "none" : "block";
  reqLength.querySelector(".icon-check").style.display = lengthMet ? "block" : "none";

  // Uppercase check
  const uppercaseMet = /[A-Z]/.test(password);
  const reqUppercase = document.getElementById("req-uppercase");
  reqUppercase.classList.toggle("met", uppercaseMet);
  reqUppercase.querySelector(".icon-circle").style.display = uppercaseMet ? "none" : "block";
  reqUppercase.querySelector(".icon-check").style.display = uppercaseMet ? "block" : "none";

  // Lowercase check
  const lowercaseMet = /[a-z]/.test(password);
  const reqLowercase = document.getElementById("req-lowercase");
  reqLowercase.classList.toggle("met", lowercaseMet);
  reqLowercase.querySelector(".icon-circle").style.display = lowercaseMet ? "none" : "block";
  reqLowercase.querySelector(".icon-check").style.display = lowercaseMet ? "block" : "none";

  // Number check
  const numberMet = /[0-9]/.test(password);
  const reqNumber = document.getElementById("req-number");
  reqNumber.classList.toggle("met", numberMet);
  reqNumber.querySelector(".icon-circle").style.display = numberMet ? "none" : "block";
  reqNumber.querySelector(".icon-check").style.display = numberMet ? "block" : "none";

  // Special character check (same regex used in registration.js)
  const specialMet = /[!@#$%^&*(),.?":{}|<>]/.test(password);
  const reqSpecial = document.getElementById("req-special");
  reqSpecial.classList.toggle("met", specialMet);
  reqSpecial.querySelector(".icon-circle").style.display = specialMet ? "none" : "block";
  reqSpecial.querySelector(".icon-check").style.display = specialMet ? "block" : "none";

  // Similarity check (now enforced)
  const similarityMet = password.length > 0 && !isPasswordTooSimilar(password, emailOrUsername);
  const reqSimilarity = document.getElementById("req-similarity");
  reqSimilarity.classList.toggle("met", similarityMet);
  reqSimilarity.querySelector(".icon-circle").style.display = similarityMet ? "none" : "block";
  reqSimilarity.querySelector(".icon-check").style.display = similarityMet ? "block" : "none";

  // Optional: if you have a "common password" requirement row in HTML (id="req-common")
  const reqCommon = document.getElementById("req-common");
  if (reqCommon) {
    const commonMet = password.length > 0 && !COMMON_PW.has(password.toLowerCase().trim());
    reqCommon.classList.toggle("met", commonMet);
    reqCommon.querySelector(".icon-circle").style.display = commonMet ? "none" : "block";
    reqCommon.querySelector(".icon-check").style.display = commonMet ? "block" : "none";
  }

  // Match check
  const matchMet = password.length > 0 && password === confirmPassword;
  const reqMatch = document.getElementById("req-match");
  reqMatch.classList.toggle("met", matchMet);
  reqMatch.querySelector(".icon-circle").style.display = matchMet ? "none" : "block";
  reqMatch.querySelector(".icon-check").style.display = matchMet ? "block" : "none";
}

// Fetch CSRF token on page load
let csrfToken = "";

async function fetchCSRFToken() {
  try {
    const PROJECT_BASE = "/FinalYearProject/";
    const response = await fetch(`${PROJECT_BASE}api/get_csrf_token.php`);
    const data = await response.json();
    if (data.csrf_token) {
      csrfToken = data.csrf_token;
      console.log("CSRF token loaded");
    }
  } catch (error) {
    console.error("Failed to fetch CSRF token:", error);
  }
}

// Export for use in reset_password_2step.js
window.getCSRFToken = () => csrfToken;

// Initialize on page load
document.addEventListener("DOMContentLoaded", () => {
  // Fetch CSRF token
  fetchCSRFToken();

  // Setup password toggles
  setupPasswordToggle(
    document.getElementById("toggle-recovery-key-btn"),
    document.getElementById("recoveryKey")
  );

  setupPasswordToggle(
    document.getElementById("toggle-password-btn"),
    document.getElementById("password")
  );

  setupPasswordToggle(
    document.getElementById("toggle-confirm-password-btn"),
    document.getElementById("password_confirmation")
  );

  // Setup password requirements validation
  const passwordInput = document.getElementById("password");
  const confirmPasswordInput = document.getElementById("password_confirmation");

  if (passwordInput) {
    passwordInput.addEventListener("input", updatePasswordRequirements);
    passwordInput.addEventListener("focus", () => {
      document.getElementById("password-requirements").style.display = "block";
    });
  }

  if (confirmPasswordInput) {
    confirmPasswordInput.addEventListener("input", updatePasswordRequirements);
    confirmPasswordInput.addEventListener("focus", () => {
      document.getElementById("password-requirements").style.display = "block";
    });
  }

  // Keep similarity status in sync as user edits identifier
  const emailOrUsernameInput = document.getElementById("emailOrUsername");
  if (emailOrUsernameInput) {
    emailOrUsernameInput.addEventListener("input", updatePasswordRequirements);
  }
});
