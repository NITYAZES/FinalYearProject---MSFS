(() => {
  const $ = (sel) => document.querySelector(sel);

  const PROJECT_BASE = "/FinalYearProject/";

  const form = $("#auth-form");
  const usernameInput = $("#username");
  const passwordInput = $("#password");
  const submitBtn = form ? form.querySelector(".submit-btn") : null;
  const submitText = $("#submit-text");
  const feedback = $("#feedback-message");
  const togglePwdBtn = $("#toggle-password-btn");

  if (!form || !usernameInput || !passwordInput) {
    console.error("Login: required elements not found (form/username/password).");
    return;
  }

  // ---------- Feedback setup ----------
  let feedbackTextSpan = null;
  if (feedback) {
    feedbackTextSpan = document.createElement("span");
    feedbackTextSpan.id = "feedback-text";
    feedback.appendChild(feedbackTextSpan);

    const closeBtn = document.createElement("button");
    closeBtn.type = "button";
    closeBtn.className = "feedback-close";
    closeBtn.setAttribute("aria-label", "Close message");
    closeBtn.innerHTML = "&times;";
    feedback.appendChild(closeBtn);

    const clearFeedback = () => {
      feedback.classList.remove("show", "error", "success", "info");
      feedbackTextSpan.textContent = "";
    };
    closeBtn.addEventListener("click", clearFeedback);
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") clearFeedback();
    });
  }

  const showMessage = (msg, type = "info") => {
    if (!feedback || !feedbackTextSpan) {
      console.warn(`Message [${type}]: ${msg}`);
      return;
    }
    feedback.classList.remove("error", "success", "info", "show");
    feedbackTextSpan.textContent = msg;
    if (type) feedback.classList.add(type);
    if (msg) {
      feedback.classList.add("show");
      feedback.scrollIntoView({ behavior: "smooth", block: "center" });
    }
    console.log(`Message [${type}]: ${msg}`);
  };

  const setLoading = (loading) => {
    if (submitBtn) submitBtn.disabled = loading;
    if (submitText) submitText.textContent = loading ? "Signing in…" : "Sign In";
  };

  // ---------- Get appropriate dashboard based on role ----------
  const getDashboardUrl = (role) => {
    if (role === 'admin') {
      return PROJECT_BASE + "dashboard_admin.html";
    }
    return PROJECT_BASE + "dashboard_user.html";
  };

  // ---------- Password visibility toggle ----------
  if (togglePwdBtn && passwordInput) {
    const eyeClosed = togglePwdBtn.querySelector(".eye-closed");
    const eyeOpen = togglePwdBtn.querySelector(".eye-open");
    if (eyeClosed && eyeOpen) {
      togglePwdBtn.addEventListener("click", (e) => {
        e.preventDefault();
        const isPassword = passwordInput.type === "password";
        passwordInput.type = isPassword ? "text" : "password";
        eyeClosed.style.display = isPassword ? "none" : "block";
        eyeOpen.style.display = isPassword ? "block" : "none";
      });
    }
  }

  // ---------- Validation ----------
  const usernameRe = /^[A-Za-z0-9_.-]{3,}$/;

  usernameInput.addEventListener("blur", () => {
    const v = (usernameInput.value || "").trim();
    if (v && !usernameRe.test(v)) {
      showMessage(
        "Username must be at least 3 characters (letters, numbers, . _ -).",
        "error"
      );
    } else {
      showMessage("", "info");
    }
  });

  // ---------- Submit handler ----------
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    showMessage("", "info");

    const username = (usernameInput.value || "").trim().toLowerCase();
    const password = passwordInput.value || "";

    if (!username) return showMessage("Please enter your username.", "error");
    if (!usernameRe.test(username)) {
      return showMessage(
        "Username must be at least 3 characters (letters, numbers, . _ -).",
        "error"
      );
    }
    if (!password) return showMessage("Please enter your password.", "error");
    if (password.length < 12) {
      return showMessage("Password must be at least 12 characters.", "error");
    }

    try {
      setLoading(true);

      // ✅ FIXED: Send plaintext password - HTTPS encrypts the transport
      // Server will handle secure hashing with Argon2ID/bcrypt
      console.log("Sending login request with plaintext password over HTTPS...");

      const payload = { 
        username, 
        password: password  // Send plaintext - secured by HTTPS
      };

      const res = await fetch(PROJECT_BASE + "api/index.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify(payload),
      });

      const raw = await res.text();
      console.log("Login response status:", res.status);

      let data = null;
      try {
        data = raw ? JSON.parse(raw) : null;
      } catch (parseErr) {
        console.error("Non-JSON response from api/index.php:", raw);
        console.error("Parse error:", parseErr);
        return showMessage("Login failed (invalid server response).", "error");
      }

      if (!data?.ok) {
        console.error("Login failed:", data);
        return showMessage(
          data?.message || `Login failed (HTTP ${res.status}).`,
          "error"
        );
      }

      // Get user role for routing
      const userRole = data.user?.role || 'user';
      console.log("User role:", userRole);

      // ---------- User requires TOTP login ----------
      if (data.requires_totp) {
        showMessage(
          "Password accepted. Redirecting to two-factor authentication...",
          "info"
        );

        // Store password for key derivation after TOTP verification
        sessionStorage.setItem("pending_password", password);

        if (data.user) {
          sessionStorage.setItem(
            "pending_totp_user",
            JSON.stringify(data.user)
          );
        }

        setTimeout(() => {
          const params = new URLSearchParams(window.location.search);
          const fallbackRedirect = getDashboardUrl(userRole);
          const redirectTarget = params.get("redirect") || fallbackRedirect;

          const url =
            PROJECT_BASE +
            "totp_verify.html?redirect=" +
            encodeURIComponent(redirectTarget);

          console.log("Redirecting to TOTP page:", url);
          window.location.href = url;
        }, 800);

        return;
      }

      // ---------- Suggest TOTP setup (mandatory) ----------
      if (data.suggest_totp_setup) {
        showMessage("Setting up two-factor authentication...", "info");

        if (data.user) {
          sessionStorage.setItem("user", JSON.stringify(data.user));
        }

        sessionStorage.setItem("post_totp_dashboard", getDashboardUrl(userRole));

        setTimeout(() => {
          const url = PROJECT_BASE + "totp_setup.html";
          console.log("Redirecting to TOTP setup:", url);
          window.location.href = url;
        }, 1200);
        return;
      }

      // ---------- Normal login (shouldn't happen with mandatory TOTP) ----------
      const displayName =
        data.user?.user_fullname || data.user?.username || "User";
      showMessage(`Welcome back, ${displayName}!`, "success");

      if (data.user) {
        sessionStorage.setItem("user", JSON.stringify(data.user));
      }

      setTimeout(() => {
        const url = getDashboardUrl(userRole);
        console.log("Redirecting to dashboard:", url);
        window.location.href = url;
      }, 1200);
    } catch (err) {
      console.error("Login error:", err);
      showMessage(
        err.message || "Unable to connect to server. Please check your connection and try again.",
        "error"
      );
    } finally {
      setLoading(false);
    }
  });

  console.log("Login initialized with secure server-side password verification");
})();