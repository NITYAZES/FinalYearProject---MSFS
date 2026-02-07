const API_BASE = "./api";


async function enforceSession() {
  try {
    const res = await fetch(`${API_BASE}/check_session.php`, {
      method: "GET",
      credentials: "include",
      headers: { Accept: "application/json" },
      cache: "no-store",
    });

    if (!res.ok) {
      window.location.replace("index.html");
      return null;
    }

    const s = await res.json();

    // must be logged in
    if (!s || !s.logged_in || !s.user_id) {
      window.location.replace("index.html");
      return null;
    }

    return s;
  } catch (e) {
    console.error("Session enforcement failed:", e);
    window.location.replace("index.html");
    return null;
  }
}


window.addEventListener("pageshow", () => {
  void enforceSession().then((s) => {
    if (s) {
      const fullName = s.user_fullname || s.username || "User";
      showApp();
      setUserUI(fullName);
    }
  });
});



function showApp() {
  const loading = document.getElementById("loading");
  const mainApp = document.getElementById("main-app");

  if (loading) loading.style.display = "none";
  if (mainApp) mainApp.style.display = "block";
}

function setUserUI(fullName) {
  const userFirstNameEl = document.getElementById("user-first-name");
  const userNameEl = document.getElementById("user-name");
  const userAvatar = document.getElementById("user-avatar");

  const safeName = String(fullName || "User").trim() || "User";
  const firstName = safeName.split(/\s+/)[0] || "User";
  const initial = safeName.charAt(0).toUpperCase() || "U";

  if (userNameEl) userNameEl.textContent = safeName;
  if (userFirstNameEl) userFirstNameEl.textContent = firstName;
  if (userAvatar) userAvatar.textContent = initial;
}



async function initializeDashboard() {
  const loading = document.getElementById("loading");

  // ✅ Always validate with server (fixes back button + storage bypass)
  const session = await enforceSession();
  if (!session) return;

  try {
    const fullName = session.user_fullname || session.username || "User";

    // Keep your smooth loading effect
    setTimeout(() => {
      showApp();
      setUserUI(fullName);
      console.log("✅ Dashboard initialized for:", fullName);
    }, 600);
  } catch (error) {
    console.error("Initialization error:", error);
    if (loading) loading.textContent = "Error loading dashboard";
  }
}



async function handleLogout() {
  if (!confirm("Are you sure you want to logout?")) return;

  try {
    // ✅ Call your real logout endpoint
    await fetch(`${API_BASE}/logout.php`, {
      method: "POST",
      credentials: "include",
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
  } catch (error) {
    console.error("Logout error:", error);
  } finally {
    // Clear client-side storage
    sessionStorage.clear();
    localStorage.clear();

    // ✅ Replace history so back button won't return to dashboard
    window.location.replace("homepage.html");
  }
}



const ACTION_ROUTES = Object.freeze({
  upload: "upload.html",
  download: "download.html",
  "my-files": "my_files.html",
  "shared-files": "shared_files.html",
  "shared-notification": "download.html", 
  activity: "activity_log.html",
  stats: "usage_statistics.html",
});


function navigateToAction(action) {
  const route = ACTION_ROUTES[action];
  if (!route) {
    console.warn("Unknown action:", action);
    return;
  }
  window.location.href = route;
}


function handleKeyboardShortcuts(e) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "u") {
    e.preventDefault();
    navigateToAction("upload");
    return;
  }

  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "d") {
    e.preventDefault();
    navigateToAction("download");
    return;
  }

  if (e.key === "Escape") {
    const settingsDropdown = document.getElementById("settings-dropdown");
    if (settingsDropdown) settingsDropdown.classList.remove("show");
  }
}



function setupSettingsDropdown() {
  const settingsBtn = document.getElementById("settings-btn");
  const settingsDropdown = document.getElementById("settings-dropdown");

  if (!settingsBtn || !settingsDropdown) return;

  settingsBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    settingsDropdown.classList.toggle("show");
  });

  document.addEventListener("click", () => {
    settingsDropdown.classList.remove("show");
  });
}



function setupActionCards() {
  document.querySelectorAll(".action-card").forEach((card) => {
    card.addEventListener("click", function () {
      const action = this.getAttribute("data-action");
      if (action) navigateToAction(action);
    });
  });

  document.querySelectorAll(".card").forEach((card) => {
    card.addEventListener("click", function () {
      const action = this.getAttribute("data-action");
      if (action) navigateToAction(action);
    });
  });
}



function setupNavItemsActiveState() {
  const navItems = document.querySelectorAll(".nav-item");
  if (!navItems.length) return;

  navItems.forEach((item) => {
    item.addEventListener("click", (e) => {
      e.preventDefault();
      navItems.forEach((i) => i.classList.remove("active"));
      item.classList.add("active");
    });
  });
}



document.addEventListener("DOMContentLoaded", () => {
  void initializeDashboard();

  setupSettingsDropdown();
  setupActionCards();
  setupNavItemsActiveState();

  const logoutBtn = document.getElementById("logout-btn");
  if (logoutBtn) logoutBtn.addEventListener("click", handleLogout);

  document.addEventListener("keydown", handleKeyboardShortcuts);

  console.log("✅ Dashboard initialized securely!");
});
