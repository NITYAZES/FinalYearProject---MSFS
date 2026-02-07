(() => {
  const PROJECT_BASE = "/FinalYearProject/";

  // Form elements
  const step1Form = document.getElementById("step1Form");
  const step2Form = document.getElementById("step2Form");
  const step1Container = document.getElementById("step1Container");
  const step2Container = document.getElementById("step2Container");

  // Step 1 fields
  const tokenInput = document.getElementById("token");
  const emailOrUsernameInput = document.getElementById("emailOrUsername");
  const recoveryKeyInput = document.getElementById("recoveryKey");
  const passwordInput = document.getElementById("password");
  const confirmInput = document.getElementById("password_confirmation");
  const step1SubmitBtn = document.getElementById("step1SubmitBtn");

  // Step 2 fields
  const otpInput = document.getElementById("otp");
  const step2SubmitBtn = document.getElementById("step2SubmitBtn");
  const resendOtpBtn = document.getElementById("resendOtpBtn");

  const messageDiv = document.getElementById("message");

  if (!step1Form || !step2Form || !step1Container || !step2Container) return;

  // Password policy (same as registration)
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

  function passwordPolicyIssues(pwd) {
    const p = pwd || "";
    const issues = [];
    if (p.length < PWD.MIN_LEN) issues.push(`at least ${PWD.MIN_LEN} characters`);
    if (!PWD.RE_UPPER.test(p)) issues.push("an uppercase letter");
    if (!PWD.RE_LOWER.test(p)) issues.push("a lowercase letter");
    if (!PWD.RE_NUM.test(p)) issues.push("a number");
    if (!PWD.RE_SPECIAL.test(p)) issues.push("a special character");
    return issues;
  }

  // Similarity check (identifier-based): blocks full token AND any 3+ prefix.
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

  // Crypto constants
  const PWKDF_ITERATIONS = 150000;
  const RKDF_ITERATIONS = 150000;
  const GCM_IV_BYTES = 12;
  const SALT_BYTES = 16;

  // State
  let currentToken = "";
  let newRecoveryKeyBase64 = "";
  let cachedMaterials = null;
  let cachedKekRawBytes = null;
  let cachedRecoveryKey = "";
  let csrfToken = "";
  let step2Shown = false;

  // Load token from URL
  const urlParams = new URLSearchParams(window.location.search);
  const token = urlParams.get("token") || "";
  if (tokenInput) tokenInput.value = token;
  currentToken = token;

  function showMessage(type, text) {
    if (!messageDiv) return;
    messageDiv.textContent = text;
    messageDiv.className =
      "message show " +
      (type === "success" ? "success" : type === "info" ? "info" : "error");
  }

  function hideMessage() {
    if (!messageDiv) return;
    messageDiv.className = "message";
  }

  function showStep2() {
    // Hide Step 1
    step1Container.classList.add("hidden");
    step1Container.style.display = "none";

    // Show Step 2 (REMOVES hidden so CSS !important won't block)
    step2Container.classList.remove("hidden");
    step2Container.style.display = "block";

    step2Shown = true;
    otpInput?.focus?.();
  }

  if (!token) {
    showMessage("error", "Missing or invalid password reset link.");
    if (step1SubmitBtn) step1SubmitBtn.disabled = true;
    return;
  }

  // CSRF (fetch on-demand to avoid race)
  async function ensureCsrfToken() {
    if (csrfToken) return csrfToken;
    const res = await fetch(`${PROJECT_BASE}api/get_csrf_token.php`);
    const data = await res.json();
    if (data?.csrf_token) {
      csrfToken = data.csrf_token;
      return csrfToken;
    }
    throw new Error("Failed to fetch CSRF token");
  }

  // Crypto helpers
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

  function randBytes(n) {
    const b = new Uint8Array(n);
    crypto.getRandomValues(b);
    return b;
  }

  function normalizeIterations(val, fallback) {
    const n = Number(val);
    if (!Number.isFinite(n) || n < 10000 || n > 5000000) return fallback;
    return Math.floor(n);
  }

  async function importPbkdf2KeyFromPassword(password) {
    return crypto.subtle.importKey("raw", enc.encode(password), { name: "PBKDF2" }, false, [
      "deriveKey",
    ]);
  }

  async function derivePwKey(pwRawKey, salt, iterations) {
    return crypto.subtle.deriveKey(
      { name: "PBKDF2", salt, iterations, hash: "SHA-256" },
      pwRawKey,
      { name: "AES-GCM", length: 256 },
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

  // Backward compatible decrypt (accepts legacy 16-byte IV by slicing to 12)
  async function aesGcmDecryptCompat(key, ciphertextBytes, ivBytes) {
    try {
      return await aesGcmDecrypt(key, ciphertextBytes, ivBytes);
    } catch (e) {
      if (ivBytes instanceof Uint8Array && ivBytes.length === 16) {
        return await aesGcmDecrypt(key, ciphertextBytes, ivBytes.slice(0, GCM_IV_BYTES));
      }
      throw e;
    }
  }

  async function fetchRecoveryKeyMaterials(token) {
    const csrf = await ensureCsrfToken();
    const headers = { "Content-Type": "application/json", "X-CSRF-Token": csrf };

    const res = await fetch(`${PROJECT_BASE}api/fetch_crypto_materials.php`, {
      method: "POST",
      headers,
      body: JSON.stringify({ token }),
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);

    const data = await res.json();
    if (!data.success) throw new Error(data.message || "Failed to fetch crypto materials");

    return {
      kek_enc_rk: data.kek_enc_rk,
      kek_rk_iv: data.kek_rk_iv,
      rkdf_salt: data.rkdf_salt,
      rkdf_iterations: data.rkdf_iterations,
    };
  }

  function showNewRecoveryKeyModal(recoveryKey) {
    return new Promise((resolve) => {
      const modal = document.createElement("div");
      modal.style.cssText = `
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.7); display: flex;
        align-items: center; justify-content: center; z-index: 10000;
      `;

      modal.innerHTML = `
        <div style="background: white; padding: 40px; border-radius: 12px;
                    max-width: 600px; width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
          <div style="text-align: center; margin-bottom: 24px;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#10b981" 
                 stroke-width="2" style="margin: 0 auto 12px;">
              <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
              <path d="m9 12 2 2 4-4"/>
            </svg>
            <h2 style="color: #1f2937; margin: 0 0 8px;">Password Reset Successful!</h2>
            <p style="color: #6b7280; margin: 0;">Your new recovery key has been generated</p>
          </div>

          <div style="background: #fef3c7; border: 2px solid #fbbf24; border-radius: 8px;
                      padding: 16px; margin-bottom: 24px;">
            <div style="display: flex; align-items: start; gap: 12px;">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f59e0b"
                   stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
                <path d="M12 9v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
              </svg>
              <div>
                <h3 style="color: #92400e; margin: 0 0 4px; font-size: 14px; font-weight: 600;">
                  Important: Save Your New Recovery Key
                </h3>
                <p style="color: #78350f; margin: 0; font-size: 13px; line-height: 1.5;">
                  This is your NEW recovery key. Store it securely - you'll need it if you forget
                  your password again. Your old recovery key will no longer work.
                </p>
              </div>
            </div>
          </div>

          <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;
                      padding: 16px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <span style="font-weight: 600; color: #374151; font-size: 13px;">Your New Recovery Key:</span>
              <button id="copyKeyBtn" style="background: #3b82f6; color: white; border: none;
                      padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;
                      display: flex; align-items: center; gap: 4px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
                Copy
              </button>
            </div>
            <code style="display: block; background: white; padding: 12px; border-radius: 4px;
                        font-family: 'Courier New', monospace; font-size: 13px; color: #1f2937;
                        word-break: break-all; border: 1px solid #e5e7eb;">${recoveryKey}</code>
          </div>

          <button id="downloadKeyBtn" style="width: 100%; background: #10b981; color: white;
                  border: none; padding: 12px; border-radius: 6px; cursor: pointer;
                  font-size: 14px; font-weight: 500; margin-bottom: 12px;
                  display: flex; align-items: center; justify-content: center; gap: 8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>
            </svg>
            Download Recovery Key
          </button>

          <div style="margin-bottom: 20px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;
                          padding: 12px; background: #f9fafb; border-radius: 6px;">
              <input type="checkbox" id="confirmSaved" style="width: 18px; height: 18px; cursor: pointer;">
              <span style="color: #374151; font-size: 14px;">
                I have saved my new recovery key in a secure location
              </span>
            </label>
          </div>

          <button id="continueBtn" disabled style="width: 100%; background: #9ca3af; color: white;
                  border: none; padding: 14px; border-radius: 6px; cursor: not-allowed;
                  font-size: 15px; font-weight: 600;">
            Continue to Login
          </button>
        </div>
      `;

      document.body.appendChild(modal);

      const checkbox = modal.querySelector("#confirmSaved");
      const continueBtn = modal.querySelector("#continueBtn");
      const copyBtn = modal.querySelector("#copyKeyBtn");
      const downloadBtn = modal.querySelector("#downloadKeyBtn");

      copyBtn.addEventListener("click", async () => {
        await navigator.clipboard.writeText(recoveryKey);
        copyBtn.innerHTML = `
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          Copied!
        `;
        setTimeout(() => {
          copyBtn.innerHTML = `
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
            Copy
          `;
        }, 2000);
      });

      downloadBtn.addEventListener("click", () => {
        const blob = new Blob([recoveryKey], { type: "text/plain" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `recovery-key-${Date.now()}.txt`;
        a.click();
        URL.revokeObjectURL(url);
      });

      checkbox.addEventListener("change", () => {
        if (checkbox.checked) {
          continueBtn.disabled = false;
          continueBtn.style.background = "#2563eb";
          continueBtn.style.cursor = "pointer";
        } else {
          continueBtn.disabled = true;
          continueBtn.style.background = "#9ca3af";
          continueBtn.style.cursor = "not-allowed";
        }
      });

      continueBtn.addEventListener("click", () => {
        if (!checkbox.checked) return;
        modal.remove();
        resolve();
      });
    });
  }

  // -------------------- STEP 1 (shared function for submit + resend) --------------------
  async function runStep1({ isResend = false } = {}) {
    const emailOrUsername = emailOrUsernameInput?.value?.trim() || "";
    const recoveryKey = recoveryKeyInput?.value?.trim() || "";
    const newPassword = passwordInput?.value || "";
    const confirmPassword = confirmInput?.value || "";

    if (!emailOrUsername) throw new Error("Please enter your email or username.");
    if (!recoveryKey) throw new Error("Please enter your recovery key.");

    const issues = passwordPolicyIssues(newPassword);
    if (issues.length) throw new Error(`Password must include ${issues.join(", ")}.`);

    if (COMMON_PW.has((newPassword || "").toLowerCase().trim())) {
      throw new Error("Password is too common. Please choose a stronger password.");
    }

    if (isPasswordTooSimilar(newPassword, emailOrUsername)) {
      throw new Error("Password is too similar to your email/username.");
    }

    if (newPassword !== confirmPassword) throw new Error("Passwords do not match.");

    if (!isResend && step1SubmitBtn) {
      step1SubmitBtn.disabled = true;
      step1SubmitBtn.innerHTML = '<span class="loading-spinner"></span>Processing...';
    }

    showMessage("info", "Fetching encryption materials...");
    const materials = await fetchRecoveryKeyMaterials(currentToken);

    cachedMaterials = materials;
    cachedRecoveryKey = recoveryKey;

    const kekEncRkBytes = new Uint8Array(b64.toArrayBuffer(materials.kek_enc_rk));
    const kekRkIvBytes = new Uint8Array(b64.toArrayBuffer(materials.kek_rk_iv));
    const rkdfSaltBytes = new Uint8Array(b64.toArrayBuffer(materials.rkdf_salt));
    const rkdfIterations = normalizeIterations(materials.rkdf_iterations, RKDF_ITERATIONS);

    showMessage("info", "Verifying recovery key...");
    const rkRawKey = await importPbkdf2KeyFromPassword(recoveryKey);
    const rkKey = await derivePwKey(rkRawKey, rkdfSaltBytes, rkdfIterations);

    showMessage("info", "Decrypting master key...");
    let kekRawBytes;
    try {
      const kekRawBuf = await aesGcmDecryptCompat(rkKey, kekEncRkBytes, kekRkIvBytes);
      kekRawBytes = new Uint8Array(kekRawBuf);
      cachedKekRawBytes = kekRawBytes;
    } catch {
      throw new Error("Invalid recovery key. Please check your recovery key and try again.");
    }

    showMessage("info", "Generating new password encryption...");
    const newPwSalt = randBytes(SALT_BYTES);
    const newPwRawKey = await importPbkdf2KeyFromPassword(newPassword);
    const newPwKey = await derivePwKey(newPwRawKey, newPwSalt, PWKDF_ITERATIONS);

    const newKekIv = randBytes(GCM_IV_BYTES);
    const newKekEncBuf = await aesGcmEncrypt(newPwKey, kekRawBytes, newKekIv);

    showMessage("info", isResend ? "Resending confirmation code..." : "Sending confirmation request...");
    const csrf = await ensureCsrfToken();
    const headers = { "Content-Type": "application/json", "X-CSRF-Token": csrf };

    const payload = {
      token: currentToken,
      email_or_username: emailOrUsername,
      new_password: newPassword,
      kek_enc: b64.fromArrayBuffer(newKekEncBuf),
      kek_iv: b64.fromArrayBuffer(newKekIv.buffer),
      pwkdf_salt: b64.fromArrayBuffer(newPwSalt.buffer),
      pwkdf_iterations: PWKDF_ITERATIONS,
    };

    const res = await fetch(`${PROJECT_BASE}api/initiate_password_reset.php`, {
      method: "POST",
      headers,
      body: JSON.stringify(payload),
    });

    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch {
      throw new Error("Server returned a non-JSON response.");
    }

    if (!res.ok || !data?.success) throw new Error(data?.message || "Failed to initiate password reset.");

    showMessage("success", data.message || "Confirmation code sent! Check your email.");

    // Only switch to step 2 the first time
    if (!step2Shown) {
      setTimeout(() => {
        showStep2();
        hideMessage();
      }, 800);
    }
  }

  // STEP 1 submit
  step1Form.addEventListener("submit", async (e) => {
    e.preventDefault();
    try {
      await runStep1({ isResend: false });
    } catch (err) {
      showMessage("error", err?.message || "Failed to process request. Please try again.");
      if (step1SubmitBtn) {
        step1SubmitBtn.disabled = false;
        step1SubmitBtn.textContent = "Continue";
      }
    }
  });

  // STEP 2: Confirm with OTP
  step2Form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const otp = otpInput?.value?.trim() || "";
    if (!/^\d{6}$/.test(otp)) {
      showMessage("error", "Please enter the 6-digit confirmation code.");
      return;
    }

    if (!cachedKekRawBytes || !cachedMaterials || !cachedRecoveryKey) {
      showMessage("error", "Session expired. Please restart the password reset process.");
      setTimeout(() => window.location.reload(), 800);
      return;
    }

    if (step2SubmitBtn) {
      step2SubmitBtn.disabled = true;
      step2SubmitBtn.innerHTML = '<span class="loading-spinner"></span>Confirming...';
    }

    try {
      showMessage("info", "Generating new recovery key...");
      const newRecoveryKeyBytes = randBytes(32);
      newRecoveryKeyBase64 = b64.fromArrayBuffer(newRecoveryKeyBytes.buffer);

      showMessage("info", "Encrypting with new recovery key...");
      const newRkdfSalt = randBytes(SALT_BYTES);
      const newRkRawKey = await importPbkdf2KeyFromPassword(newRecoveryKeyBase64);
      const newRkKey = await derivePwKey(newRkRawKey, newRkdfSalt, RKDF_ITERATIONS);

      const newKekRkIv = randBytes(GCM_IV_BYTES);
      const newKekEncRkBuf = await aesGcmEncrypt(newRkKey, cachedKekRawBytes, newKekRkIv);

      showMessage("info", "Confirming reset...");
      const csrf = await ensureCsrfToken();
      const headers = { "Content-Type": "application/json", "X-CSRF-Token": csrf };

      const payload = {
        token: currentToken,
        otp,
        new_kek_enc_rk: b64.fromArrayBuffer(newKekEncRkBuf),
        new_kek_rk_iv: b64.fromArrayBuffer(newKekRkIv.buffer),
        new_rkdf_salt: b64.fromArrayBuffer(newRkdfSalt.buffer),
        new_rkdf_iterations: RKDF_ITERATIONS,
      };

      // DEBUG: Log payload details
      console.log('[DEBUG] Confirm reset payload:', {
        token_length: payload.token?.length,
        otp_length: payload.otp?.length,
        otp_valid: /^\d{6}$/.test(payload.otp),
        new_kek_enc_rk_length: payload.new_kek_enc_rk?.length,
        new_kek_rk_iv_length: payload.new_kek_rk_iv?.length,
        new_rkdf_salt_length: payload.new_rkdf_salt?.length,
        new_rkdf_iterations: payload.new_rkdf_iterations,
        csrf_token_present: !!csrf,
      });

      // Validate payload before sending
      if (!payload.token) {
        throw new Error('CLIENT ERROR: Missing token');
      }
      if (!payload.otp || !/^\d{6}$/.test(payload.otp)) {
        throw new Error('CLIENT ERROR: Invalid OTP');
      }
      if (!payload.new_kek_enc_rk) {
        throw new Error('CLIENT ERROR: Missing new_kek_enc_rk');
      }
      if (!payload.new_kek_rk_iv) {
        throw new Error('CLIENT ERROR: Missing new_kek_rk_iv');
      }
      if (!payload.new_rkdf_salt) {
        throw new Error('CLIENT ERROR: Missing new_rkdf_salt');
      }
      if (!payload.new_rkdf_iterations || payload.new_rkdf_iterations < 100000) {
        throw new Error('CLIENT ERROR: Invalid new_rkdf_iterations');
      }

      console.log('[DEBUG] Payload validation passed, sending request...');

      const res = await fetch(`${PROJECT_BASE}api/confirm_password_reset.php`, {
        method: "POST",
        headers,
        body: JSON.stringify(payload),
      });

      console.log('[DEBUG] Response status:', res.status);
      console.log('[DEBUG] Response headers:', Object.fromEntries(res.headers.entries()));

      const text = await res.text();
      console.log('[DEBUG] Response text:', text.substring(0, 500));

      let data;
      try {
        data = JSON.parse(text);
        console.log('[DEBUG] Parsed response:', data);
      } catch (parseErr) {
        console.error('[DEBUG] JSON parse error:', parseErr);
        console.error('[DEBUG] Full response text:', text);
        throw new Error("Server returned a non-JSON response. Check console for details.");
      }

      if (!res.ok || !data?.success) {
        console.error('[DEBUG] Request failed:', {
          status: res.status,
          success: data?.success,
          message: data?.message,
          full_response: data
        });
        throw new Error(data?.message || "Failed to confirm password reset.");
      }

      console.log('[DEBUG] Password reset confirmed successfully!');
      showMessage("success", "Password reset successful!");
      await showNewRecoveryKeyModal(newRecoveryKeyBase64);
      window.location.href = `${PROJECT_BASE}index.html`;
    } catch (err) {
      console.error('[DEBUG] Error in step 2:', err);
      showMessage("error", err?.message || "Failed to confirm reset. Please try again.");
      if (step2SubmitBtn) {
        step2SubmitBtn.disabled = false;
        step2SubmitBtn.textContent = "Confirm Reset";
      }
    }
  });

  // Resend OTP
  if (resendOtpBtn) {
    resendOtpBtn.addEventListener("click", async () => {
      try {
        await runStep1({ isResend: true });
      } catch (err) {
        showMessage("error", err?.message || "Failed to resend code. Please try again.");
        if (step1SubmitBtn) {
          step1SubmitBtn.disabled = false;
          step1SubmitBtn.textContent = "Continue";
        }
      }
    });
  }

  console.log("reset_password_2step.js initialized (DEBUG VERSION)");
})();