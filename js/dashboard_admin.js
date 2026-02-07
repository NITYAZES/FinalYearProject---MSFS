/* dashboard_admin.js (CSP-safe: no inline handlers, no element.onclick) */

const API_BASE = "./api";

let usersData = [];
let filesData = [];
let auditData = [];
let notifications = [];
let currentFilter = "all";

function byId(id) {
  return document.getElementById(id);
}

function setText(id, value) {
  const el = byId(id);
  if (el) el.textContent = value;
}

/**
 * ✅ Prevent "back button still shows dashboard" (BFCache)
 * This runs when the page is restored from back/forward cache.
 */
window.addEventListener("pageshow", () => {
  void enforceAdminSession();
});

document.addEventListener("DOMContentLoaded", () => {
  // ✅ Always enforce session at page entry
  void enforceAdminSession();

  checkSession();

  initDashboard().catch((err) => {
    console.error(err);
    alert("Failed to load admin dashboard data. Check console/network and API auth.");
  });

  initNotificationPanel();
  updateNotificationBadge();
  startNotificationPolling();

  // ✅ CSP-safe: LOGOUT BUTTON BINDING
  const logoutBtn = byId("logout-btn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", (e) => {
      e.preventDefault();
      void logout();
    });
  } else {
    console.error("Logout button not found (#logout-btn)");
  }

  // ✅ CSP-safe: Quick actions (replace any inline onclick in HTML with data-href)
  document.querySelectorAll(".action-card[data-href]").forEach((card) => {
    card.addEventListener("click", () => {
      const href = card.getAttribute("data-href");
      if (href) window.location.href = href;
    });
  });
});

/**
 * ✅ Hard guard for admin pages.
 * If session is gone (or not admin), immediately redirect.
 * Also uses cache: "no-store" to avoid cached auth responses.
 */
async function enforceAdminSession() {
  try {
    const res = await fetch(`${API_BASE}/check_session.php`, {
      method: "GET",
      credentials: "include",
      headers: { Accept: "application/json" },
      cache: "no-store",
    });

    if (!res.ok) {
      window.location.replace("index.html");
      return;
    }

    const s = await res.json();

    // Expecting: { logged_in: bool, user_id: ..., role: "admin", username: ... }
    if (!s?.logged_in || !s?.user_id || s?.role !== "admin") {
      window.location.replace("index.html");
    }
  } catch (err) {
    console.error("enforceAdminSession error:", err);
    window.location.replace("index.html");
  }
}

async function checkSession() {
  try {
    const res = await fetch(`${API_BASE}/check_session.php`, {
      method: "GET",
      credentials: "include",
      headers: { Accept: "application/json" },
      cache: "no-store",
    });

    if (res.ok) {
      const sessionData = await res.json();
      if (sessionData.logged_in && sessionData.username) {
        setText("adminUsername", sessionData.username);
      } else {
        setText("adminUsername", "Admin");
      }
    } else {
      setText("adminUsername", "Admin");
    }
  } catch (err) {
    console.error("Session check error:", err);
    setText("adminUsername", "Admin");
  }
}

async function initDashboard() {
  const res = await fetch(`${API_BASE}/dashboard_admin.php`, {
    method: "GET",
    headers: { Accept: "application/json" },
    credentials: "include",
    cache: "no-store",
  });

  if (!res.ok) {
    // If unauthorized, force redirect
    if (res.status === 401 || res.status === 403) {
      window.location.replace("index.html");
      return;
    }
    throw new Error(`API error ${res.status}`);
  }

  const data = await res.json();
  if (!data.ok) {
    if (data.error && /not logged in|unauthorized|admin/i.test(String(data.error))) {
      window.location.replace("index.html");
      return;
    }
    throw new Error(data.error || "API returned ok=false");
  }

  usersData = Array.isArray(data.users) ? data.users : [];
  filesData = Array.isArray(data.files) ? data.files : [];
  auditData = Array.isArray(data.audit) ? data.audit : [];

  loadDashboard(data.stats);
}

function loadDashboard(statsFromApi) {
  const stats = statsFromApi || {};

  const totalUsers = stats.totalUsers ?? usersData.length;
  const activeUsers = stats.activeUsers ?? usersData.filter((u) => u.status === "active").length;
  const totalFiles = stats.totalFiles ?? filesData.length;
  const avgEncryption = stats.avgEncryption ?? 0;

  setText("totalUsers", totalUsers);
  setText("activeUsers", activeUsers);
  setText("totalFiles", totalFiles);
  setText("avgEncryption", Number(avgEncryption).toFixed(0));

  setText("totalUsersMeta", `${totalUsers} registered`);
  setText("activeUsersMeta", `${((activeUsers / (totalUsers || 1)) * 100).toFixed(0)}% active rate`);
  setText("totalFilesMeta", `${totalFiles} uploaded`);
  setText("avgEncryptionMeta", "Excellent security");
}

// ========== NOTIFICATION PANEL ==========

function initNotificationPanel() {
  const notifBtn = byId("notificationBtn");
  const closeBtn = byId("closeNotifications");
  const overlay = byId("panelOverlay");
  const markAllBtn = byId("markAllReadBtn");
  const viewAllBtn = byId("viewAllBtn");
  const notifBody = byId("notificationsBody");

  // ✅ CSP-safe: use addEventListener (not element.onclick)
  if (notifBtn) notifBtn.addEventListener("click", openNotificationPanel);
  if (closeBtn) closeBtn.addEventListener("click", closeNotificationPanel);
  if (overlay) overlay.addEventListener("click", closeNotificationPanel);
  if (markAllBtn) markAllBtn.addEventListener("click", markAllNotificationsRead);
  if (viewAllBtn) viewAllBtn.addEventListener("click", () => (window.location.href = "admin_notifications.html"));

  // ✅ CSP-safe: delegated click for dynamically-rendered notification items
  if (notifBody) {
    notifBody.addEventListener("click", (e) => {
      const item = e.target.closest(".notification-item[data-id]");
      if (!item) return;
      const id = Number(item.getAttribute("data-id"));
      if (!Number.isFinite(id)) return;
      void handleNotificationClick(id);
    });
  }

  // Filter chips
  document.querySelectorAll(".filter-chip").forEach((chip) => {
    chip.addEventListener("click", (e) => {
      document.querySelectorAll(".filter-chip").forEach((c) => c.classList.remove("active"));
      const target = e.currentTarget;
      target.classList.add("active");
      currentFilter = target.dataset.filter || "all";
      renderNotifications();
    });
  });

  // ESC key to close
  document.addEventListener("keydown", (e) => {
    const panel = byId("notificationsPanel");
    if (e.key === "Escape" && panel && panel.classList.contains("open")) {
      closeNotificationPanel();
    }
  });
}

function openNotificationPanel() {
  const panel = byId("notificationsPanel");
  const overlay = byId("panelOverlay");
  if (panel && overlay) {
    panel.classList.add("open");
    overlay.classList.add("active");
    document.body.style.overflow = "hidden";
    void loadNotifications();
  }
}

function closeNotificationPanel() {
  const panel = byId("notificationsPanel");
  const overlay = byId("panelOverlay");
  if (panel && overlay) {
    panel.classList.remove("open");
    overlay.classList.remove("active");
    document.body.style.overflow = "";
  }
}

async function loadNotifications() {
  const container = byId("notificationsBody");

  // Show loading state
  if (container) {
    container.innerHTML = `
      <div class="empty-notifications">
        <div class="empty-notifications-icon">⏳</div>
        <p>Loading notifications...</p>
      </div>
    `;
  }

  try {
    const res = await fetch(`${API_BASE}/admin_notifications.php?filter=all&limit=50`, {
      method: "GET",
      credentials: "include",
      headers: { Accept: "application/json" },
      cache: "no-store",
    });

    if (!res.ok) {
      if (res.status === 401 || res.status === 403) {
        window.location.replace("index.html");
        return;
      }
      throw new Error(`HTTP ${res.status}`);
    }

    const data = await res.json();

    if (data.ok) {
      notifications = data.notifications || [];
      renderNotifications();
      void updateNotificationBadge();
    } else {
      throw new Error(data.error || "Failed to load notifications");
    }
  } catch (err) {
    console.error("Failed to load notifications:", err);
    if (container) {
      container.innerHTML = `
        <div class="empty-notifications">
          <div class="empty-notifications-icon">⚠️</div>
          <p>Failed to load notifications</p>
          <p style="font-size: 12px; margin-top: 8px; color: #999;">${escapeHtml(err?.message || "Unknown error")}</p>
        </div>
      `;
    }
  }
}

function renderNotifications() {
  const container = byId("notificationsBody");
  if (!container) return;

  let filtered = notifications;

  // Apply filters
  if (currentFilter === "unread") {
    filtered = filtered.filter((n) => !n.is_read);
  } else if (currentFilter === "urgent") {
    filtered = filtered.filter((n) => n.priority === "urgent");
  } else if (currentFilter === "high") {
    filtered = filtered.filter((n) => n.priority === "high");
  }

  if (filtered.length === 0) {
    container.innerHTML = `
      <div class="empty-notifications">
        <div class="empty-notifications-icon">🔔</div>
        <p>No notifications</p>
      </div>
    `;
    return;
  }

  // ✅ CSP-safe: no inline onclick in generated HTML
  const html = filtered
    .map((notif) => {
      const icon = getCategoryIcon(notif.category);
      const timeAgo = getTimeAgo(notif.created_at);
      const unreadClass = notif.is_read ? "" : "unread";
      const priorityClass = `priority-${notif.priority}`;

      return `
        <div class="notification-item ${unreadClass} ${priorityClass}" data-id="${escapeHtml(notif.id)}">
          <div class="notification-header-item">
            <span class="notification-icon">${icon}</span>
            <span class="notification-title-text">${escapeHtml(notif.title)}</span>
            <span class="notification-time">${timeAgo}</span>
          </div>
          <div class="notification-message">${escapeHtml(notif.message)}</div>
          <div class="notification-meta">
            <span class="notification-category">${escapeHtml(notif.category)}</span>
            <span class="notification-priority-badge ${escapeHtml(notif.priority)}">${escapeHtml(notif.priority)}</span>
          </div>
        </div>
      `;
    })
    .join("");

  container.innerHTML = html;
}

async function handleNotificationClick(id) {
  const notif = notifications.find((n) => Number(n.id) === Number(id));
  if (!notif) return;

  // Mark as read if unread
  if (!notif.is_read) {
    await markNotificationRead(id);
  }

  // Navigate to action URL if present
  if (notif.action_url) {
    window.location.href = notif.action_url;
  }
}

async function markNotificationRead(id) {
  try {
    const res = await fetch(`${API_BASE}/admin_notifications.php?action=mark_read`, {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      cache: "no-store",
      body: JSON.stringify({ notification_ids: [id] }),
    });

    if (res.ok) {
      await loadNotifications();
    }
  } catch (err) {
    console.error("Failed to mark as read:", err);
  }
}

async function markAllNotificationsRead() {
  if (notifications.filter((n) => !n.is_read).length === 0) {
    showToast("No unread notifications", "info");
    return;
  }

  try {
    const res = await fetch(`${API_BASE}/admin_notifications.php?action=mark_all_read`, {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      cache: "no-store",
      body: JSON.stringify({}),
    });

    if (res.ok) {
      await loadNotifications();
      showToast("All notifications marked as read", "success");
    }
  } catch (err) {
    console.error("Failed to mark all as read:", err);
    showToast("Failed to mark all as read", "error");
  }
}

async function updateNotificationBadge() {
  try {
    const res = await fetch(`${API_BASE}/admin_notifications.php?action=count`, {
      method: "GET",
      credentials: "include",
      headers: { Accept: "application/json" },
      cache: "no-store",
    });

    if (res.ok) {
      const data = await res.json();
      if (data.ok) {
        const count = data.unread_count || 0;
        updateBadge("navUnreadCount", count);
        updateBadge("panelUnreadCount", count);
      }
    }
  } catch (err) {
    console.error("Failed to update notification badge:", err);
  }
}

function updateBadge(elementId, count) {
  const badge = byId(elementId);
  if (badge) {
    badge.textContent = count;
    badge.style.display = count > 0 ? "inline-block" : "none";
  }
}

function startNotificationPolling() {
  // Poll for notification updates every 30 seconds
  setInterval(() => {
    void updateNotificationBadge();
    // Auto-refresh if panel is open
    const panel = byId("notificationsPanel");
    if (panel && panel.classList.contains("open")) {
      void loadNotifications();
    }
  }, 30000);
}

function getCategoryIcon(category) {
  const icons = {
    security: "🔒",
    user: "👤",
    file: "📁",
    system: "⚙️",
    audit: "📋",
  };
  return icons[category] || "📢";
}

function getTimeAgo(dateString) {
  const date = new Date(dateString);
  const now = new Date();
  const seconds = Math.floor((now - date) / 1000);

  if (seconds < 60) return "Just now";
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
  if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`;
  return date.toLocaleDateString();
}

function showToast(message, type = "info") {
  const toast = document.createElement("div");
  const bgColors = {
    success: "linear-gradient(135deg, #10b981 0%, #059669 100%)",
    error: "linear-gradient(135deg, #ef4444 0%, #dc2626 100%)",
    info: "linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)",
  };

  toast.style.cssText = `
    position: fixed;
    bottom: 24px;
    right: 24px;
    padding: 18px 28px;
    background: ${bgColors[type] || bgColors.info};
    color: white;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    font-size: 15px;
    font-weight: 600;
    z-index: 10001;
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  `;
  toast.textContent = message;
  document.body.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = "1";
    toast.style.transform = "translateY(0)";
  }, 10);

  setTimeout(() => {
    toast.style.opacity = "0";
    toast.style.transform = "translateY(20px)";
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// ========== UTILITY FUNCTIONS ==========

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


async function logout() {
  const ok = window.confirm("Are you sure you want to logout?");
  if (!ok) return;

  try {
    await fetch(`${API_BASE}/logout.php`, {
      method: "POST",
      credentials: "include",
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
  } catch (err) {
    console.error("Logout request failed:", err);
  }

  window.location.replace("homepage.html");
}
