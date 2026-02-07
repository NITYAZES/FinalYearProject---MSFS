(() => {
  const $ = (sel) => document.querySelector(sel);

  // Match your main app base path
  const PROJECT_BASE = "/FinalYearProject/";

  const totpForm        = $("#totp-form");
  const totpCodeInput   = $("#totp-code");
  const backupForm      = $("#backup-verify-form");
  const backupCodeInput = $("#backup-code");

  const feedback   = $("#feedback-message");
  const submitBtn  = $(".submit-btn");
  const submitText = $("#submit-text");

  const useBackupLink    = $("#use-backup-link");
  const backupSection    = $("#backup-section");
  const backToTotpLink   = $("#back-to-totp");
  const backToLoginLink  = $("#back-to-login-link");

  if (!totpForm || !totpCodeInput) {
    console.error("TOTP verify: form or code input not found.");
    return;
  }

  // ---------- UI helpers ----------

  let feedbackTextSpan = null;
  if (feedback) {
    feedbackTextSpan = document.createElement("span");
    feedbackTextSpan.id = "feedback-text";
    feedback.appendChild(feedbackTextSpan);
  }

  const showMessage = (msg, type = "info") => {
    if (!feedback || !feedbackTextSpan) {
      console.warn(`[TOTP] ${type}: ${msg}`);
      return;
    }
    feedback.classList.remove("error", "success", "info", "show");
    feedbackTextSpan.textContent = msg;
    if (type) feedback.classList.add(type);
    if (msg) {
      feedback.classList.add("show");
      feedback.scrollIntoView({ behavior: "smooth", block: "center" });
    }
    console.log(`[TOTP] ${type}: ${msg}`);
  };

  const setLoading = (loading) => {
    if (submitBtn)  submitBtn.disabled     = loading;
    if (submitText) submitText.textContent = loading ? "Verifying…" : "Verify";
  };

  
// ---------- Key material fetch (separate endpoint) ----------
async function fetchKeyMaterial() {
  const res = await fetch(PROJECT_BASE + "api/get_key_material.php", {
    method: "GET",
    credentials: "same-origin",
    headers: { "Accept": "application/json" },
  });

  const raw = await res.text();
  let data = null;
  try {
    data = raw ? JSON.parse(raw) : null;
  } catch (e) {
    console.error("Invalid JSON from get_key_material.php:", raw);
    throw new Error("Failed to fetch key material (invalid server response).");
  }

  if (!res.ok || !data?.ok) {
    const msg = data?.message || `Failed to fetch key material (HTTP ${res.status}).`;
    const err = new Error(msg);
    // surface to caller whether reauth is required
    err.requires_reauth = Boolean(data?.requires_reauth);
    throw err;
  }

  return data.keyMaterial || null; // null for admin
}

// ---------- Crypto helpers ----------

  const b64ToBytes = (b64) =>
    Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));

  async function importPbkdf2KeyFromPassword(password) {
    const enc = new TextEncoder();
    const passBytes = enc.encode(password);
    return crypto.subtle.importKey(
      "raw",
      passBytes,
      "PBKDF2",
      false,
      ["deriveKey"]
    );
  }

  async function derivePwKey(pwRawKey, saltBytes, iterations) {
    return crypto.subtle.deriveKey(
      {
        name: "PBKDF2",
        hash: "SHA-256",
        salt: saltBytes,
        iterations,
      },
      pwRawKey,
      { name: "AES-GCM", length: 256 },
      false,
      ["encrypt", "decrypt"]
    );
  }

  async function importAesKey(rawBytes) {
    return crypto.subtle.importKey(
      "raw",
      rawBytes,
      { name: "AES-GCM" },
      false,
      ["encrypt", "decrypt"]
    );
  }

  async function aesGcmDecrypt(aesKey, ciphertextBytes, ivBytes) {
    return crypto.subtle.decrypt(
      { name: "AES-GCM", iv: ivBytes },
      aesKey,
      ciphertextBytes
    );
  }

  async function importPrivateKeyPkcs8(pkcs8Buf) {
    // extractable = true so we can export to JWK
    return crypto.subtle.importKey(
      "pkcs8",
      pkcs8Buf,
      {
        name: "RSA-OAEP",
        hash: "SHA-256",
      },
      true,        // must be true to allow exportKey("jwk", ...)
      ["decrypt"]  // RSA-OAEP private key is used for decrypt / unwrap
    );
  }

  // 🔐 Unlock KEK + private key and store JWK in sessionStorage (only for users)
  async function unlockKeysWithPassword(password, keyMaterial) {
    if (!keyMaterial) {
      console.log("No key material provided - likely admin user, skipping key unlock");
      return;
    }

    const {
      pwkdf_salt,
      pwkdf_iterations,
      kek_enc,
      kek_iv,
      private_key_enc,
      private_key_iv,
    } = keyMaterial;

    if (!pwkdf_salt || !kek_enc || !kek_iv || !private_key_enc || !private_key_iv) {
      throw new Error("Incomplete key material from server.");
    }

    console.log("Unlocking encryption keys...");

    // 1) Decode base64 → Uint8Array
    const saltBytes    = b64ToBytes(pwkdf_salt);
    const kekEncBytes  = b64ToBytes(kek_enc);
    const kekIvBytes   = b64ToBytes(kek_iv);
    const privEncBytes = b64ToBytes(private_key_enc);
    const privIvBytes  = b64ToBytes(private_key_iv);

    // 2) Derive PWKey from password
    const pwRawKey = await importPbkdf2KeyFromPassword(password);
    const pwKey    = await derivePwKey(
      pwRawKey,
      saltBytes,
      Number(pwkdf_iterations || 0)
    );

    // 3) Decrypt KEK with PWKey
    const kekRawBuf   = await aesGcmDecrypt(pwKey, kekEncBytes, kekIvBytes);
    const kekRawBytes = new Uint8Array(kekRawBuf);
    const kekKey      = await importAesKey(kekRawBytes);

    // 4) Decrypt private key (PKCS#8) with KEK
    const privPkcs8Buf = await aesGcmDecrypt(kekKey, privEncBytes, privIvBytes);
    const privateKey   = await importPrivateKeyPkcs8(privPkcs8Buf);

    // 5) Export private key as JWK and store in sessionStorage
    const privJwk = await crypto.subtle.exportKey("jwk", privateKey);
    try {
      sessionStorage.setItem("unlockedPrivateKey", JSON.stringify(privJwk));
      sessionStorage.setItem("keys_unlocked", "1");
    } catch (e) {
      console.error("Failed to store unlockedPrivateKey in sessionStorage:", e);
      throw new Error("Unable to store unlocked key in this browser.");
    }

    console.log("Private key successfully unlocked and stored as JWK in sessionStorage.");
  }

  // ---------- Core: call backend & unlock keys (if user), then redirect ----------

  async function verifyCodeAndUnlock({ code, type }) {
    const pendingPassword = sessionStorage.getItem("pending_password");
    
    // Call backend for verification
    const res = await fetch(
      PROJECT_BASE + "api/totp_login_verify.php",
      {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ code, type }),
      }
    );

    const raw = await res.text();
    console.log("TOTP/backup verify response (first 500 chars):", raw.substring(0, 500));

    // Check if response looks like PHP error
    if (raw.trim().startsWith("<") || raw.includes("Warning:") || raw.includes("Fatal error:")) {
      console.error("PHP error detected in response:", raw);

      let errorMsg = "Server configuration error. Please contact support.";

      if (raw.includes("Failed to open stream: No such file or directory")) {
        errorMsg = "Server error: Missing required files. Please check server configuration.";
      } else if (raw.includes("Failed opening required")) {
        errorMsg = "Server error: Unable to load required components.";
      } else if (raw.includes("404") || raw.includes("Not Found")) {
        errorMsg = "Server error: Authentication endpoint not found.";
      }

      showMessage(errorMsg, "error");
      throw new Error("PHP configuration error detected");
    }

    // Handle HTTP errors
    if (!res.ok) {
      console.error("HTTP error from totp_login_verify.php:", res.status, raw);
      showMessage(`Verification failed (HTTP ${res.status}).`, "error");
      throw new Error(`HTTP ${res.status}`);
    }

    let data = null;
    try {
      data = raw ? JSON.parse(raw) : null;
    } catch (err) {
      console.error("Invalid JSON from totp_login_verify.php:", err);
      console.error("Raw response:", raw);
      showMessage("Server returned an invalid response. Please check server logs.", "error");
      throw err;
    }

    if (!data?.ok) {
      console.error("Verification failed:", data);
      showMessage(
        data?.message || `Verification failed (HTTP ${res.status}).`,
        "error"
      );
      throw new Error(data?.message || "Verification failed");
    }

    // ✅ Store user info in sessionStorage
    if (data.user) {
      try {
        sessionStorage.setItem("user", JSON.stringify(data.user));
      } catch (e) {
        console.warn("Failed to store user in sessionStorage:", e);
      }
    }

// 🔐 Fetch key material AFTER TOTP verification (separate endpoint)
let keyMaterial = null;
const userRoleForKeys = data.user?.role || 'user';

if (userRoleForKeys !== 'admin') {
  try {
    keyMaterial = await fetchKeyMaterial();
  } catch (kmErr) {
    console.error("Failed to fetch key material:", kmErr);
    if (kmErr?.requires_reauth) {
      showMessage("Session expired. Please log in again.", "error");
    } else {
      showMessage("Authentication succeeded but failed to fetch encryption keys.", "error");
    }
  }
}

// 🔐 Unlock keys ONLY if keyMaterial exists (non-admin)
if (keyMaterial) {
  if (!pendingPassword) {
    console.warn("Missing password context for key unlock");
    showMessage("Password context missing. Please log in again.", "error");
  } else {
    try {
      await unlockKeysWithPassword(pendingPassword, keyMaterial);
    } catch (unlockErr) {
      console.error("Failed to unlock keys:", unlockErr);
      showMessage("Failed to unlock encryption keys. " + unlockErr.message, "error");
    }
  }
} else {
  console.log("No key material (admin user or unavailable).");
}

    // Clear sensitive session items
    sessionStorage.removeItem("pending_password");
    sessionStorage.removeItem("pending_totp_user");

    // Determine user role
    const userRole = data.user?.role || 'user';
    const isAdmin = userRole === 'admin';

    if (isAdmin) {
      showMessage("Admin authentication successful ✓ Redirecting...", "success");
    } else {
      showMessage("Authentication successful. Encryption keys unlocked ✓ Redirecting...", "success");
    }

    // Role-based redirect
    const params = new URLSearchParams(window.location.search);
    const defaultRedirect = isAdmin 
      ? PROJECT_BASE + "dashboard_admin.html"
      : PROJECT_BASE + "dashboard_user.html";
    
    const redirect = params.get("redirect") || defaultRedirect;

    console.log("[TOTP] Redirecting to:", redirect);

    setTimeout(() => {
      window.location.href = redirect;
    }, 900);
  }

  // ---------- Event handlers ----------

  totpForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const code = (totpCodeInput.value || "").trim();
    if (!/^\d{6}$/.test(code)) {
      showMessage("Please enter a valid 6-digit code.", "error");
      return;
    }

    try {
      setLoading(true);
      await verifyCodeAndUnlock({ code, type: "totp" });
    } catch (err) {
      console.error("TOTP verify / key unlock error:", err);
      // Error message already shown in verifyCodeAndUnlock
    } finally {
      setLoading(false);
    }
  });

  if (backupForm && backupCodeInput) {
    backupForm.addEventListener("submit", async (e) => {
      e.preventDefault();

      const code = (backupCodeInput.value || "").trim();
      if (!code) {
        showMessage("Please enter a backup code.", "error");
        return;
      }

      try {
        setLoading(true);
        await verifyCodeAndUnlock({ code, type: "backup" });
      } catch (err) {
        console.error("Backup verify / key unlock error:", err);
        
      } finally {
        setLoading(false);
      }
    });
  }

  if (useBackupLink && backupSection) {
    useBackupLink.addEventListener("click", (e) => {
      e.preventDefault();
      backupSection.style.display = "block";
      totpForm.style.display      = "none";
      useBackupLink.style.display = "none";
      backupCodeInput && backupCodeInput.focus();
    });
  }

  if (backToTotpLink && backupSection) {
    backToTotpLink.addEventListener("click", (e) => {
      e.preventDefault();
      backupSection.style.display = "none";
      totpForm.style.display      = "block";
      useBackupLink.style.display = "inline-block";
      totpCodeInput && totpCodeInput.focus();
    });
  }

  if (backToLoginLink) {
    backToLoginLink.addEventListener("click", (e) => {
      e.preventDefault();
      window.location.href = PROJECT_BASE + "index.html";
    });
  }

  console.log("totp-verify.js initialized (admin skips key unlock, users unlock keys)");
})();