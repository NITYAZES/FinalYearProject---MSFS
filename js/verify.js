(() => {
  const $ = (s) => document.querySelector(s);

  const form = $("#verify-form");
  const emailEl = $("#email");
  const codeEl = $("#code");
  const msg = $("#msg");
  const submitBtn = $("#submit-btn");
  const signinBtn = $("#signin-btn");

  if (!form || !emailEl || !codeEl || !msg || !submitBtn) return;

  const PROJECT_BASE = "/FinalYearProject/";

  const API = {
    verify: PROJECT_BASE + "api/verify_email.php",
    redirectAfterVerify: PROJECT_BASE + "index.html", // ✅ root index.html
  };

  // Prefill email from query ?email=... or localStorage.pending_email
  (function prefillEmail() {
    const params = new URLSearchParams(location.search);
    const qEmail = params.get("email");
    let lsEmail = null;
    try {
      lsEmail = localStorage.getItem("pending_email");
    } catch {}
    emailEl.value = (qEmail || lsEmail || "").trim();
  })();

  function setMsg(text, type = "") {
    msg.className = "msg " + (type === "ok" ? "ok" : type === "err" ? "err" : "");
    msg.textContent = text || "";
  }

  function setLoading(loading) {
    submitBtn.disabled = loading;
    submitBtn.textContent = loading ? "Verifying..." : "Verify";
  }

  function isValidEmail(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i.test(v);
  }

  function isValidCode(v) {
    return /^\d{6}$/.test(v);
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    setMsg("");

    const email = emailEl.value.trim();
    const code = codeEl.value.trim();

    if (!isValidEmail(email)) {
      setMsg("Please enter a valid email.", "err");
      emailEl.focus();
      return;
    }

    if (!isValidCode(code)) {
      setMsg("Enter the 6-digit verification code.", "err");
      codeEl.focus();
      return;
    }

    try {
      setLoading(true);

      const res = await fetch(API.verify, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, code }),
      });

      const raw = await res.text();
      let data = null;
      try {
        data = raw ? JSON.parse(raw) : null;
      } catch {}

      if (!res.ok || !data?.ok) {
        setMsg(data?.message || `Verification failed (HTTP ${res.status})`, "err");
        return;
      }

      setMsg("Email verified! Redirecting to sign in…", "ok");
      try {
        localStorage.removeItem("pending_email");
      } catch {}

      setTimeout(() => {
        window.location.href = API.redirectAfterVerify;
      }, 800);
    } catch (err) {
      console.error("verify error:", err);
      setMsg("Network error. Please try again.", "err");
    } finally {
      setLoading(false);
    }
  });

  // UX: only digits, max 6
  codeEl.addEventListener("input", () => {
    codeEl.value = codeEl.value.replace(/\D/g, "").slice(0, 6);
  });

  // Add event listener for the back button
  if (signinBtn) {
    signinBtn.addEventListener("click", () => {
      window.location.href = PROJECT_BASE + "index.html";
    });
  }
})();