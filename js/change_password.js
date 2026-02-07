(() => {
  const PROJECT_BASE = "/FinalYearProject/";

  // DOM elements
  const form = document.getElementById("changePasswordForm");
  const currentPwdInput = document.getElementById("current_password");
  const newPwdInput = document.getElementById("new_password");
  const confirmPwdInput = document.getElementById("confirm_password");
  const messageDiv = document.getElementById("message");
  const submitBtn = document.getElementById("submitBtn");

  const toggleCurrentBtn = document.getElementById("toggle-current-btn");
  const toggleNewBtn = document.getElementById("toggle-new-btn");
  const toggleConfirmBtn = document.getElementById("toggle-confirm-btn");

  if (!form) return;

  // Crypto constants
  const PWKDF_ITERATIONS_MIN = 100000;
  const PWKDF_ITERATIONS_DEFAULT = 150000;
  const AES_KEY_LENGTH = 256;
  const GCM_IV_BYTES = 12;
  const SALT_BYTES = 16;

  // Password policy (same as registration/reset)
  const PWD = {
    MIN_LEN: 12,
    RE_UPPER: /[A-Z]/,
    RE_LOWER: /[a-z]/,
    RE_NUM: /[0-9]/,
    RE_SPECIAL: /[!@#$%^&*(),.?":{}|<>]/,
  };

  const COMMON_PW = new Set([
    "password", "password123", "password@123", "qwerty", "qwerty123",
    "admin", "admin123", "welcome", "letmein", "iloveyou",
    "123456", "12345678", "123456789", "1234567890",
    "abc123", "000000", "111111", "passw0rd", "p@ssw0rd",
  ]);

  // Encoding helpers
  const enc = new TextEncoder();

  const b64 = {
    fromArrayBuffer(buf) {
      const bytes = new Uint8Array(buf);
      let binary = "";
      for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
      return btoa(binary);
    },
    toArrayBuffer(b64str) {
      const binary = atob(b64str);
      const len = binary.length;
      const bytes = new Uint8Array(len);
      for (let i = 0; i < len; i++) bytes[i] = binary.charCodeAt(i);
      return bytes.buffer;
    },
  };

  // Messaging helper
  function showMessage(type, text) {
    if (!messageDiv) return;
    messageDiv.textContent = text;
    messageDiv.className = "message show " + (type === "success" ? "success" : "error");
  }

  // Password visibility toggles
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

  setupPasswordToggle(toggleCurrentBtn, currentPwdInput);
  setupPasswordToggle(toggleNewBtn, newPwdInput);
  setupPasswordToggle(toggleConfirmBtn, confirmPwdInput);

  function getSessionUser() {
    try {
      const raw = sessionStorage.getItem("user");
      if (!raw) return { name: "", username: "", email: "" };
      const u = JSON.parse(raw);
      return {
        name: (u?.name || u?.user_fullname || "").toString(),
        username: (u?.username || "").toString(),
        email: (u?.email || u?.user_email || "").toString(),
      };
    } catch {
      return { name: "", username: "", email: "" };
    }
  }

  // Prefix-blocking similarity check (same idea as registration/reset)
  function isPasswordTooSimilar(pwd, name, username, email) {
    const pwdLower = (pwd || "").toLowerCase().trim();
    if (pwdLower.length < 3) return false;

    const nameLower = (name || "").toLowerCase().trim();
    const usernameLower = (username || "").toLowerCase().trim();
    const emailLower = (email || "").toLowerCase().trim();
    const emailPrefix = (emailLower.split("@")[0] || "").trim();

    const tokens = [];

    // name parts (>=3)
    for (const part of (nameLower.split(/\s+/) || [])) {
      const p = (part || "").trim();
      if (p.length >= 3) tokens.push(p);
    }

    if (usernameLower) tokens.push(usernameLower);
    if (emailPrefix) tokens.push(emailPrefix);

    for (const tRaw of tokens) {
      const t = (tRaw || "").trim();
      if (t.length < 3) continue;

      // block full token
      if (pwdLower.includes(t)) return true;

      // block any 3+ prefix (Nit from Nitya)
      for (let i = 3; i <= t.length; i++) {
        const prefix = t.slice(0, i);
        if (prefix && pwdLower.includes(prefix)) return true;
      }
    }

    return false;
  }

  function passwordPolicyIssues(pwd) {
    const p = pwd || "";
    const issues = [];
    if (p.length < PWD.MIN_LEN) issues.push(`at least ${PWD.MIN_LEN} characters`);
    if (!PWD.RE_LOWER.test(p)) issues.push("a lowercase letter");
    if (!PWD.RE_UPPER.test(p)) issues.push("an uppercase letter");
    if (!PWD.RE_NUM.test(p)) issues.push("a number");
    if (!PWD.RE_SPECIAL.test(p)) issues.push("a special character");
    if (COMMON_PW.has(p.toLowerCase().trim())) issues.push("not be a common password");
    return issues;
  }

  // UI requirement indicators (safe even if some IDs don’t exist)
  function updateRequirement(reqId, met) {
    const reqElement = document.getElementById(reqId);
    if (!reqElement) return;

    const svg = reqElement.querySelector("svg");
    if (svg) {
      if (met) {
        svg.innerHTML = '<polyline points="20 6 9 17 4 12" stroke-width="2" fill="none"></polyline>';
        svg.style.stroke = "#27ae60";
        reqElement.classList.remove("invalid");
        reqElement.classList.add("valid");
      } else {
        svg.innerHTML = '<circle cx="12" cy="12" r="10" stroke-width="2" fill="none"></circle>';
        svg.style.stroke = "#e74c3c";
        reqElement.classList.remove("valid");
        reqElement.classList.add("invalid");
      }
    } else {
      reqElement.classList.toggle("valid", !!met);
      reqElement.classList.toggle("invalid", !met);
    }
  }

  function validatePassword() {
    const current = currentPwdInput?.value || "";
    const n = newPwdInput?.value || "";
    const c = confirmPwdInput?.value || "";

    const user = getSessionUser();

    const lengthMet = n.length >= PWD.MIN_LEN;
    const lowerMet = PWD.RE_LOWER.test(n);
    const upperMet = PWD.RE_UPPER.test(n);
    const numberMet = PWD.RE_NUM.test(n);
    const specialMet = PWD.RE_SPECIAL.test(n);

    const differentMet = n !== "" && current !== "" && n !== current;
    const matchMet = n !== "" && n === c;

    const commonMet = n === "" || !COMMON_PW.has(n.toLowerCase().trim());
    const notSimilarMet = n === "" || !isPasswordTooSimilar(n, user.name, user.username, user.email);

    // Existing IDs (kept) + extra IDs (optional in HTML)
    updateRequirement("req-length", lengthMet);
    updateRequirement("req-letter", lowerMet);          // if your UI calls it "letter"
    updateRequirement("req-lowercase", lowerMet);
    updateRequirement("req-uppercase", upperMet);
    updateRequirement("req-number", numberMet);
    updateRequirement("req-special", specialMet);
    updateRequirement("req-different", differentMet);
    updateRequirement("req-match", matchMet);
    updateRequirement("req-not-similar", notSimilarMet);
    updateRequirement("req-not-common", commonMet);

    const allOk =
      lengthMet && lowerMet && upperMet && numberMet && specialMet &&
      differentMet && matchMet && notSimilarMet && commonMet;

    if (submitBtn) submitBtn.disabled = !allOk;
    return allOk;
  }

  currentPwdInput?.addEventListener("input", validatePassword);
  newPwdInput?.addEventListener("input", validatePassword);
  confirmPwdInput?.addEventListener("input", validatePassword);

  // WebCrypto helpers
  function randBytes(n) {
    const b = new Uint8Array(n);
    crypto.getRandomValues(b);
    return b;
  }

  async function importPbkdf2KeyFromPassword(password) {
    return crypto.subtle.importKey("raw", enc.encode(password), { name: "PBKDF2" }, false, ["deriveKey"]);
  }

  async function derivePwKey(pwRawKey, salt, iterations) {
    return crypto.subtle.deriveKey(
      { name: "PBKDF2", salt, iterations, hash: "SHA-256" },
      pwRawKey,
      { name: "AES-GCM", length: AES_KEY_LENGTH },
      false,
      ["encrypt", "decrypt"]
    );
  }

  async function aesGcmDecrypt(key, ciphertextBytes, ivBytes) {
    return crypto.subtle.decrypt({ name: "AES-GCM", iv: ivBytes }, key, ciphertextBytes);
  }

  async function aesGcmEncrypt(key, plaintextBytes, ivBytes) {
    return crypto.subtle.encrypt({ name: "AES-GCM", iv: ivBytes }, key, plaintextBytes);
  }

  // Main form submission
  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!validatePassword()) {
      showMessage("error", "Please meet all password requirements.");
      return;
    }

    const currentPassword = currentPwdInput.value || "";
    const newPassword = newPwdInput.value || "";

    if (!currentPassword) {
      showMessage("error", "Please enter your current password.");
      return;
    }

    if (currentPassword === newPassword) {
      showMessage("error", "New password must be different from current password.");
      return;
    }

    // Strong submit-time checks
    const user = getSessionUser();
    const issues = passwordPolicyIssues(newPassword);
    if (issues.length) {
      showMessage("error", `Password must include ${issues.join(", ")}.`);
      return;
    }
    if (isPasswordTooSimilar(newPassword, user.name, user.username, user.email)) {
      showMessage("error", "Password is too similar to your name, username, or email.");
      return;
    }

    // Get session user (must exist)
    const userRaw = sessionStorage.getItem("user");
    if (!userRaw) {
      showMessage("error", "Session expired. Please login again.");
      setTimeout(() => (window.location.href = `${PROJECT_BASE}index.html`), 1500);
      return;
    }
    const userObj = JSON.parse(userRaw);

    // UI state
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="loading-spinner"></span>Re-encrypting keys...';
    }
    if (messageDiv) messageDiv.className = "message";

    try {
      // Step 1: Fetch current crypto parameters
      const fetchRes = await fetch(`${PROJECT_BASE}api/get_crypto_params.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ user_id: userObj.user_id }),
      });

      if (!fetchRes.ok) throw new Error("Failed to fetch encryption parameters");

      const cryptoData = await fetchRes.json();
      if (!cryptoData.success) throw new Error(cryptoData.message || "Failed to get crypto params");

      // Decode base64 parameters
      const kekEncOld = b64.toArrayBuffer(cryptoData.kek_enc);
      const kekIvOld = b64.toArrayBuffer(cryptoData.kek_iv);
      const pwkdfSaltOld = b64.toArrayBuffer(cryptoData.pwkdf_salt);
      const pwkdfIterationsOld = Number(cryptoData.pwkdf_iterations) || 0;

      // Step 2: Derive PWKey_old from current password
      const pwRawKeyOld = await importPbkdf2KeyFromPassword(currentPassword);
      const pwKeyOld = await derivePwKey(pwRawKeyOld, new Uint8Array(pwkdfSaltOld), pwkdfIterationsOld);

      // Step 3: Decrypt KEK with PWKey_old
      let kekRaw;
      try {
        kekRaw = await aesGcmDecrypt(pwKeyOld, kekEncOld, new Uint8Array(kekIvOld));
      } catch {
        showMessage("error", "Current password is incorrect.");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Change Password";
        }
        return;
      }

      // Step 4: Generate new salt and iterations
      const pwkdfSaltNew = randBytes(SALT_BYTES);
      const pwkdfIterationsNew = Math.max(PWKDF_ITERATIONS_DEFAULT, PWKDF_ITERATIONS_MIN);

      // Step 5: Derive PWKey_new from new password
      const pwRawKeyNew = await importPbkdf2KeyFromPassword(newPassword);
      const pwKeyNew = await derivePwKey(pwRawKeyNew, pwkdfSaltNew, pwkdfIterationsNew);

      // Step 6: Re-encrypt KEK with PWKey_new
      const kekIvNew = randBytes(GCM_IV_BYTES);
      const kekEncNew = await aesGcmEncrypt(pwKeyNew, kekRaw, kekIvNew);

      // Step 7: Send to server
      const payload = {
        user_id: userObj.user_id,
        current_password: currentPassword,
        new_password: newPassword,
        kek_enc: b64.fromArrayBuffer(kekEncNew),
        kek_iv: b64.fromArrayBuffer(kekIvNew.buffer),
        pwkdf_salt: b64.fromArrayBuffer(pwkdfSaltNew.buffer),
        pwkdf_iterations: pwkdfIterationsNew,
      };

      const response = await fetch(`${PROJECT_BASE}api/change_password.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify(payload),
      });

      const isJson = response.headers.get("content-type")?.includes("application/json");
      const data = isJson ? await response.json() : null;

      if (!response.ok || !data?.success) {
        showMessage("error", data?.message || "Failed to change password.");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Change Password";
        }
        return;
      }

      showMessage("success", "Password changed successfully! Redirecting...");
      form.reset();

      setTimeout(() => {
        window.location.href = `${PROJECT_BASE}dashboard_user.html`;
      }, 2000);
    } catch (err) {
      console.error("Password change error:", err);
      showMessage("error", err?.message || "An error occurred. Please try again.");
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = "Change Password";
      }
    }
  });

  console.log("change_password.js initialized");
})();
