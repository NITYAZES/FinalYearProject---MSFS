(() => {
  const $ = (sel) => document.querySelector(sel);

  // Elements
  const form = $("#auth-form");
  const nameInput = $("#name");
  const usernameInput = $("#username");
  const phoneInput = $("#phone");
  const emailInput = $("#email");
  const pwdInput = $("#password");
  const confirmPwdInput = $("#confirm-password");
  const submitBtn = form?.querySelector(".submit-btn");
  const submitText = $("#submit-text");
  const feedback = $("#feedback-message");
  const usernameStatus = $("#username-status");
  const requirementsContainer = $("#password-requirements");

  // API endpoints
  const API = {
    checkUsername: "./api/check_username.php",
    checkPhone: "./api/check_phone.php",
    register: "./api/register.php",
    redirectAfterRegister: "./index.html",
    verifyPage: "./verify.html",
  };

  // Validation regexes & policy
  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i;
  const MSIA_PHONE_RE = /^\+?60\d{8,10}$/;
  const USERNAME_RE = /^[a-zA-Z0-9._-]{3,20}$/; // ✅ enforce safe username format

  const PWD = {
    MIN_LEN: 12,
    RE_UPPER: /[A-Z]/,
    RE_LOWER: /[a-z]/,
    RE_NUM: /[0-9]/,
    RE_SPECIAL: /[^A-Za-z0-9]/, // ✅ at least one non-alphanumeric
  };

  // Small common-password blocklist (expand on server side ideally)
  const COMMON_PASSWORDS = new Set(
    [
      "password",
      "password123",
      "password@123",
      "qwerty",
      "qwerty123",
      "admin",
      "admin123",
      "welcome",
      "letmein",
      "iloveyou",
      "123456",
      "12345678",
      "123456789",
      "1234567890",
      "abc123",
      "000000",
      "111111",
      "passw0rd",
      "p@ssw0rd",
      "Password@123", 
    ].map((s) => s.toLowerCase())
  );

  // Crypto constants
  const PWKDF_ITERATIONS = 150000;
  const RKDF_ITERATIONS = 150000;
  const RSA_ALGO = {
    name: "RSA-OAEP",
    modulusLength: 2048,
    publicExponent: new Uint8Array([0x01, 0x00, 0x01]),
    hash: "SHA-256",
  };
  const AES_KEY_LENGTH = 256;
  const GCM_IV_BYTES = 12;
  const KEK_IV_BYTES = 12;
  const RECOVERY_IV_BYTES = 16;
  const SALT_BYTES = 16;
  const RECOVERY_KEY_BYTES = 32;

  // Utilities
  const debounce = (fn, delay = 500) => {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), delay);
    };
  };

  const enc = new TextEncoder();

  // Base64 + Base64URL helpers
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
    toUrlSafe(b64str) {
      return b64str.replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
    },
    fromUrlSafe(b64url) {
      const padLen = (4 - (b64url.length % 4)) % 4;
      const padded = b64url + "=".repeat(padLen);
      return padded.replace(/-/g, "+").replace(/_/g, "/");
    },
  };

  const normalizePhone = (p) => (p || "").replace(/\s+/g, "");
  const normalizeEmail = (e) => (e || "").trim().toLowerCase();

  // Feedback system
  const ensureFeedbackShell = () => {
    if (!feedback) return { textSpan: null, closeBtn: null, clear: () => {} };

    let textSpan = feedback.querySelector("#feedback-text");
    if (!textSpan) {
      textSpan = document.createElement("span");
      textSpan.id = "feedback-text";
      feedback.appendChild(textSpan);
    }

    let closeBtn = feedback.querySelector(".feedback-close");
    if (!closeBtn) {
      closeBtn = document.createElement("button");
      closeBtn.type = "button";
      closeBtn.className = "feedback-close";
      closeBtn.setAttribute("aria-label", "Close message");
      closeBtn.textContent = "×";
      feedback.appendChild(closeBtn);
    }

    const clear = () => {
      feedback.classList.remove("show", "error", "success", "info");
      textSpan.textContent = "";
    };

    // Rebind click safely
    closeBtn.replaceWith(closeBtn.cloneNode(true));
    closeBtn = feedback.querySelector(".feedback-close");
    closeBtn.addEventListener("click", clear);

    if (!document._feedbackEscBound) {
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") clear();
      });
      document._feedbackEscBound = true;
    }

    return { textSpan, closeBtn, clear };
  };

  const { textSpan: feedbackTextSpan, clear: clearFeedback } = ensureFeedbackShell();

  const showMessage = (msg, type = "info") => {
    if (!feedback || !feedbackTextSpan) return;
    feedback.classList.remove("error", "success", "info", "show");
    feedbackTextSpan.textContent = msg || "";
    if (msg) {
      if (type) feedback.classList.add(type);
      feedback.classList.add("show");
    }
  };

  const setLoading = (loading) => {
    if (!submitBtn) return;
    submitBtn.disabled = loading;
    if (submitText) submitText.textContent = loading ? "Please wait…" : "Sign Up";
  };

  // Username check
  let isUsernameValid = false;

  const renderUsernameStatus = (state) => {
    if (!usernameStatus || !usernameInput) return;
    const map = {
      idle: { text: "", cls: "username-status" },
      checking: { text: "Checking availability...", cls: "username-status checking" },
      available: { text: "✓ Username is available", cls: "username-status available" },
      taken: { text: "✗ Username is already taken", cls: "username-status taken" },
      invalid: { text: "✗ Invalid username format", cls: "username-status error" },
      error: { text: "⚠ Could not check availability", cls: "username-status error" },
    };
    const { text, cls } = map[state] ?? map.idle;
    usernameStatus.textContent = text;
    usernameStatus.className = cls;

    usernameInput.classList.remove("success", "error");
    if (state === "available") usernameInput.classList.add("success");
    if (state === "taken" || state === "error" || state === "invalid")
      usernameInput.classList.add("error");
  };

  const checkUsernameAvailability = async (username) => {
    try {
      const res = await fetch(API.checkUsername, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ username }),
      });

      if (!res.ok) {
        renderUsernameStatus("error");
        isUsernameValid = false;
        return;
      }

      const data = await res.json();

      // Accept either {ok:true, available:true} or {available:true}
      const available = data?.ok ? !!data?.available : data?.available !== undefined ? !!data.available : null;

      if (available === true) {
        renderUsernameStatus("available");
        isUsernameValid = true;
      } else if (available === false) {
        renderUsernameStatus("taken");
        isUsernameValid = false;
      } else {
        renderUsernameStatus("error");
        isUsernameValid = false;
      }
    } catch (err) {
      console.error("Username check error:", err);
      renderUsernameStatus("error");
      isUsernameValid = false;
    }
  };

  if (usernameInput && usernameStatus) {
    const debouncedCheck = debounce((value) => checkUsernameAvailability(value), 500);
    usernameInput.addEventListener("input", () => {
      const username = usernameInput.value.trim();

      if (username.length < 3) {
        renderUsernameStatus("idle");
        isUsernameValid = false;
        return;
      }

      if (!USERNAME_RE.test(username)) {
        renderUsernameStatus("invalid");
        isUsernameValid = false;
        return;
      }

      renderUsernameStatus("checking");
      debouncedCheck(username);
    });
  }

  // Phone check
  const isPhoneAvailable = async (phone) => {
    try {
      const res = await fetch(API.checkPhone, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ phone }),
      });

      if (!res.ok) {
        console.error("Phone check HTTP error:", res.status);
        return false;
      }

      const data = await res.json();
      return !!(data?.ok ? data.available : data?.available);
    } catch (err) {
      console.error("Phone check error:", err);
      return false;
    }
  };

  // Password similarity check
const isPasswordTooSimilar = (pwd, name, username, email) => {
  const pwdLower = (pwd || "").toLowerCase().trim();
  if (pwdLower.length < 3) return false;

  const nameLower = (name || "").toLowerCase().trim();
  const usernameLower = (username || "").toLowerCase().trim();
  const emailLower = (email || "").toLowerCase().trim();
  const emailPrefix = (emailLower.split("@")[0] || "").trim();

  // tokens: each name part + username + email prefix
  const nameParts = nameLower.split(/\s+/).map(s => s.trim()).filter(Boolean);
  const tokens = [...nameParts, usernameLower, emailPrefix].filter(t => t.length >= 3);

  for (const token of tokens) {
    // 1) Block full token containment
    if (pwdLower.includes(token)) return true;

    // 2) Block any 3+ prefix of the token (Nit, Nity, ...)
    for (let i = 3; i <= token.length; i++) {
      const prefix = token.slice(0, i);
      if (pwdLower.includes(prefix)) return true;
    }
  }

  return false;
};


  // Password policy
  const meetsPolicy = (pwd) => ({
    length: (pwd || "").length >= PWD.MIN_LEN,
    uppercase: PWD.RE_UPPER.test(pwd || ""),
    lowercase: PWD.RE_LOWER.test(pwd || ""),
    number: PWD.RE_NUM.test(pwd || ""),
    special: PWD.RE_SPECIAL.test(pwd || ""),
  });

  const policyIssues = (pwd) => {
    const p = meetsPolicy(pwd);
    const texts = {
      length: `be at least ${PWD.MIN_LEN} characters`,
      lowercase: "include a lowercase letter",
      uppercase: "include an uppercase letter",
      number: "include a number",
      special: "include a special character",
    };
    return Object.entries(p)
      .filter(([, ok]) => !ok)
      .map(([k]) => texts[k]);
  };

  const isCommonPassword = (pwd) => COMMON_PASSWORDS.has((pwd || "").toLowerCase());

  const setReqState = (itemEl, met) => {
    if (!itemEl) return;
    itemEl.classList.toggle("met", !!met);

    const svg = itemEl.querySelector("svg");
    if (svg) {
      svg.innerHTML = met
        ? '<polyline points="20 6 9 17 4 12" stroke-width="2" fill="none"></polyline>'
        : '<circle cx="12" cy="12" r="10"></circle>';
      svg.style.stroke = met ? "#27ae60" : "#e74c3c";
    }

    const indicator = itemEl.querySelector(".indicator");
    if (indicator) indicator.style.backgroundColor = met ? "#27ae60" : "#e74c3c";
  };

  const updatePasswordRequirements = (pwd, confirmPwd) => {
    if (!requirementsContainer) return;
    requirementsContainer.classList.add("show");

    const res = meetsPolicy(pwd);
    for (const key of Object.keys(res)) {
      const item = requirementsContainer.querySelector(`[data-requirement="${key}"]`);
      setReqState(item, res[key]);
    }

    // Match
    const matchItem = requirementsContainer.querySelector(`[data-requirement="match"]`);
    if (matchItem) {
      const hasConfirm = (confirmPwd || "").length > 0;
      const matches = hasConfirm && (pwd || "") === (confirmPwd || "");
      setReqState(matchItem, matches);
      const textSpan = matchItem.querySelector("span:last-child");
      if (textSpan) textSpan.textContent = matches ? "Passwords match" : "Passwords do not match";
    }

    // Not similar
    const similarityItem = requirementsContainer.querySelector(`[data-requirement="notSimilar"]`);
    if (similarityItem && pwd) {
      const name = nameInput?.value || "";
      const username = usernameInput?.value || "";
      const email = emailInput?.value || "";
      const notSimilar = !isPasswordTooSimilar(pwd, name, username, email);
      setReqState(similarityItem, notSimilar);
    }

    // Common password (optional UI item if you add it in HTML: data-requirement="notCommon")
    const commonItem = requirementsContainer.querySelector(`[data-requirement="notCommon"]`);
    if (commonItem) setReqState(commonItem, pwd ? !isCommonPassword(pwd) : false);
  };

  const wirePwdUI = () => {
    if (!requirementsContainer) return;
    const handler = () =>
      updatePasswordRequirements(pwdInput?.value || "", confirmPwdInput?.value || "");
    pwdInput?.addEventListener("focus", handler);
    pwdInput?.addEventListener("input", handler);
    confirmPwdInput?.addEventListener("focus", handler);
    confirmPwdInput?.addEventListener("input", handler);
    nameInput?.addEventListener("input", handler);
    usernameInput?.addEventListener("input", handler);
    emailInput?.addEventListener("input", handler);
    handler();
  };

  const setupPasswordToggle = (toggleButton, inputField) => {
    if (!toggleButton || !inputField) return;
    const eyeClosed = toggleButton.querySelector(".eye-closed");
    const eyeOpen = toggleButton.querySelector(".eye-open");
    if (!eyeClosed || !eyeOpen) return;

    toggleButton.addEventListener("click", (e) => {
      e.preventDefault();
      const isPwd = inputField.type === "password";
      inputField.type = isPwd ? "text" : "password";
      eyeClosed.style.display = isPwd ? "none" : "block";
      eyeOpen.style.display = isPwd ? "block" : "none";
    });
  };

  // WebCrypto helpers
  function randBytes(n) {
    const b = new Uint8Array(n);
    crypto.getRandomValues(b);
    return b;
  }

  async function generateRsaKeyPair() {
    return crypto.subtle.generateKey(RSA_ALGO, true, ["encrypt", "decrypt"]);
  }

  async function exportPublicJwk(publicKey) {
    return crypto.subtle.exportKey("jwk", publicKey);
  }

  async function exportPrivatePkcs8(privateKey) {
    return crypto.subtle.exportKey("pkcs8", privateKey);
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
      { name: "AES-GCM", length: AES_KEY_LENGTH },
      false,
      ["encrypt", "decrypt"]
    );
  }

  async function importAesKey(rawKeyBytes) {
    return crypto.subtle.importKey("raw", rawKeyBytes, { name: "AES-GCM" }, false, [
      "encrypt",
      "decrypt",
    ]);
  }

  async function aesGcmEncrypt(key, plaintextBytes, ivBytes) {
    return crypto.subtle.encrypt({ name: "AES-GCM", iv: ivBytes }, key, plaintextBytes);
  }

  // Recovery key display modal
  function showRecoveryKeyModal(recoveryKey) {
    return new Promise((resolve) => {
      const modal = document.createElement("div");
      modal.style.cssText = `
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
      `;

      modal.innerHTML = `
        <div style="background: white; padding: 40px; border-radius: 16px; max-width: 600px; width: 90%;">
          <h2 style="margin: 0 0 20px 0; color: #1f2937; text-align: center;">🔑 Save Your Recovery Key</h2>
          <div style="background: #fef3c7; border: 2px solid #f59e0b; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
            <p style="margin: 0 0 8px 0; color: #92400e; font-weight: 600;">⚠️ IMPORTANT:</p>
            <p style="margin: 0; color: #78350f; font-size: 14px;">
              This recovery key allows you to reset your password if you forget it.
              <strong>Store it securely</strong> - it will only be shown once!
            </p>
          </div>
          <div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            <div style="font-family: monospace; font-size: 18px; font-weight: 700; letter-spacing: 2px; color: #1f2937; word-break: break-all;">
              ${recoveryKey}
            </div>
          </div>
          <div style="display: flex; gap: 12px; margin-bottom: 16px;">
            <button id="copy-recovery-key" style="flex: 1; padding: 12px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
              📋 Copy Key
            </button>
            <button id="download-recovery-key" style="flex: 1; padding: 12px; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
              💾 Download Key
            </button>
          </div>
          <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px; cursor: pointer;">
            <input type="checkbox" id="confirm-saved" style="width: 18px; height: 18px; cursor: pointer;">
            <span style="color: #4b5563; font-size: 14px;">I have saved my recovery key in a secure location</span>
          </label>
          <button id="continue-btn" disabled style="width: 100%; padding: 14px; background: #9ca3af; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: not-allowed; font-size: 16px;">
            Continue to Registration
          </button>
        </div>
      `;

      document.body.appendChild(modal);

      const copyBtn = modal.querySelector("#copy-recovery-key");
      const downloadBtn = modal.querySelector("#download-recovery-key");
      const checkbox = modal.querySelector("#confirm-saved");
      const continueBtn = modal.querySelector("#continue-btn");

      copyBtn.addEventListener("click", async () => {
        try {
          await navigator.clipboard.writeText(recoveryKey);
          copyBtn.textContent = "✅ Copied!";
          setTimeout(() => {
            copyBtn.textContent = "📋 Copy Key";
          }, 2000);
        } catch {
          alert("Failed to copy. Please copy manually.");
        }
      });

      downloadBtn.addEventListener("click", () => {
        const username = usernameInput?.value?.trim() || "user";
        const blob = new Blob(
          [
            `RECOVERY KEY FOR SECURE FILE SHARE\n\n${recoveryKey}\n\nStore this key securely. You will need it to reset your password.\n`,
          ],
          { type: "text/plain" }
        );
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `${username}-recovery-key.txt`;
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
        if (checkbox.checked) {
          modal.remove();
          resolve();
        }
      });
    });
  }

  // Initialize UI
  wirePwdUI();
  setupPasswordToggle($("#toggle-password-btn"), pwdInput);
  setupPasswordToggle($("#toggle-confirm-password-btn"), confirmPwdInput);

  // Form submission
  form?.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearFeedback();

    const name = (nameInput?.value || "").trim();
    const username = (usernameInput?.value || "").trim();
    const phone = normalizePhone(phoneInput?.value || "");
    const email = normalizeEmail(emailInput?.value || "");
    const password = pwdInput?.value || "";
    const confirmPassword = confirmPwdInput?.value || "";

    // Validations
    if (!name) return showMessage("Please enter your full name.", "error");

    if (!username) return showMessage("Please enter a username.", "error");
    if (!USERNAME_RE.test(username))
      return showMessage(
        "Username must be 3–20 characters and only use letters, numbers, dot, underscore, or hyphen.",
        "error"
      );
    if (!isUsernameValid) return showMessage("Please choose an available username.", "error");

    if (!phone || !MSIA_PHONE_RE.test(phone))
      return showMessage("Phone format should be like +60XXXXXXXXX.", "error");

    if (!email || !EMAIL_RE.test(email))
      return showMessage("Please enter a valid email address.", "error");

    const phoneAvailable = await isPhoneAvailable(phone);
    if (!phoneAvailable) return showMessage("This phone number is already registered.", "error");

    if (!password) return showMessage("Please enter a password.", "error");
    if (password.length < PWD.MIN_LEN)
      return showMessage(`Password must be at least ${PWD.MIN_LEN} characters.`, "error");
    if (password !== confirmPassword) return showMessage("Passwords do not match.", "error");

    if (isCommonPassword(password))
      return showMessage("Password is too common. Please choose a stronger one.", "error");

    if (isPasswordTooSimilar(password, name, username, email)) {
      return showMessage(
        "Password cannot be the same as or contain your name, username, or email.",
        "error"
      );
    }

    const issues = policyIssues(password);
    if (issues.length) return showMessage(`Password must ${issues.join(", ")}.`, "error");

    try {
      setLoading(true);

      // 1) Generate recovery key (256-bit random), show URL-safe form (easier to store)
      const recoveryKeyBytes = randBytes(RECOVERY_KEY_BYTES);
      const recoveryKey = b64.toUrlSafe(b64.fromArrayBuffer(recoveryKeyBytes.buffer));

      await showRecoveryKeyModal(recoveryKey);

      // 2) Generate RSA keypair
      const { publicKey, privateKey } = await generateRsaKeyPair();

      // 3) Generate KEK (256-bit random)
      const kekRaw = randBytes(32);
      const kekCryptoKey = await importAesKey(kekRaw);

      // 4) Export and encrypt private key with KEK
      const privatePkcs8 = await exportPrivatePkcs8(privateKey);
      const privIv = randBytes(GCM_IV_BYTES);
      const privateKeyEncBuf = await aesGcmEncrypt(kekCryptoKey, privatePkcs8, privIv);

      // 5) Derive password-based key and encrypt KEK
      const pwSalt = randBytes(SALT_BYTES);
      const pwRawKey = await importPbkdf2KeyFromPassword(password);
      const pwKey = await derivePwKey(pwRawKey, pwSalt, PWKDF_ITERATIONS);

      const kekPwIv = randBytes(KEK_IV_BYTES);
      const kekEncPwBuf = await aesGcmEncrypt(pwKey, kekRaw, kekPwIv);

      // 6) Derive recovery key-based key and encrypt KEK
      //    NOTE: recoveryKey is base64url; use it directly as password material (string).
      const rkSalt = randBytes(SALT_BYTES);
      const rkRawKey = await importPbkdf2KeyFromPassword(recoveryKey);
      const rkKey = await derivePwKey(rkRawKey, rkSalt, RKDF_ITERATIONS);

      const kekRkIv = randBytes(RECOVERY_IV_BYTES);
      const kekEncRkBuf = await aesGcmEncrypt(rkKey, kekRaw, kekRkIv);

      // 7) Export public key
      const pubJwk = await exportPublicJwk(publicKey);

      // 8) Prepare payload
      // IMPORTANT: enforce the SAME password rules on the server too.
      const payload = {
        name,
        username,
        phone,
        email,
        password, // send over HTTPS; server MUST hash (bcrypt/argon2)

        // Crypto fields
        public_key_jwk: pubJwk,
        private_key_enc: b64.fromArrayBuffer(privateKeyEncBuf),
        private_key_iv: b64.fromArrayBuffer(privIv.buffer),

        // Password-based KEK encryption
        kek_enc: b64.fromArrayBuffer(kekEncPwBuf),
        kek_iv: b64.fromArrayBuffer(kekPwIv.buffer),
        pwkdf_salt: b64.fromArrayBuffer(pwSalt.buffer),
        pwkdf_iterations: PWKDF_ITERATIONS,

        // Recovery key-based KEK encryption
        kek_enc_rk: b64.fromArrayBuffer(kekEncRkBuf),
        kek_rk_iv: b64.fromArrayBuffer(kekRkIv.buffer),
        rkdf_salt: b64.fromArrayBuffer(rkSalt.buffer),
        rkdf_iterations: RKDF_ITERATIONS,

        key_version: 1,
      };

      const response = await fetch(API.register, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const raw = await response.text();
      let data;
      try {
        data = raw ? JSON.parse(raw) : null;
      } catch {
        throw new Error(`Invalid JSON from server (HTTP ${response.status})`);
      }

      if (!response.ok || !data?.ok) {
        const msg = data?.message || `HTTP ${response.status}`;
        showMessage(msg, "error");
        return;
      }

      try {
        localStorage.setItem("pending_email", email);
      } catch {}

      if (data.needs_verification) {
        showMessage("Registration successful! Redirecting to email verification…", "success");
        setTimeout(() => {
          const url = new URL(API.verifyPage, window.location.href);
          url.searchParams.set("email", email);
          window.location.href = url.toString();
        }, 800);
      } else {
        showMessage("Registration successful! Redirecting to sign in…", "success");
        setTimeout(() => {
          window.location.href = API.redirectAfterRegister;
        }, 800);
      }
    } catch (err) {
      console.error("Registration error:", err);
      showMessage("Something went wrong during registration. Please try again.", "error");
    } finally {
      setLoading(false);
    }
  });

  console.log("register.js initialized (username regex + stronger special-char rule + common-password block)");
})();
