
class NotificationManager {
  constructor() {
    this.notifications = [];
    this.unreadCount = 0;
    this.panel = null;
    this.badge = null;
    this.isOpen = false;
    this.refreshInterval = null;
  }

  init() {
    this.createNotificationPanel();
    this.setupEventListeners();
    this.loadNotifications();
    this.startAutoRefresh();
  }

  createNotificationPanel() {
    const panel = document.createElement("div");
    panel.className = "notifications-panel";
    panel.id = "notifications-panel";
    panel.innerHTML = `
      <div class="notifications-header">
        <div class="notifications-title">
          <span>🔔</span>
          <span>Notifications</span>
        </div>
        <button class="close-notifications" id="close-notifications">×</button>
      </div>
      <div class="notifications-body" id="notifications-body">
        <div class="loading-text">Loading notifications...</div>
      </div>
      <div class="notifications-footer">
        <button id="mark-all-read-btn" class="notif-action-btn">
          <span>✓</span> Mark All Read
        </button>
        <button id="clear-all-btn" class="notif-action-btn clear-btn">
          <span>🗑️</span> Clear All
        </button>
      </div>
    `;
    document.body.appendChild(panel);
    this.panel = panel;

    const notifBtn = document.querySelector('button[title="Notifications"]');
    if (notifBtn && !notifBtn.querySelector(".notification-badge")) {
      notifBtn.style.position = "relative";
      const badge = document.createElement("span");
      badge.className = "notification-badge";
      badge.id = "notification-badge";
      badge.style.display = "none";
      notifBtn.appendChild(badge);
      this.badge = badge;
    }
  }

  setupEventListeners() {
    const notifBtn = document.querySelector('button[title="Notifications"]');
    if (notifBtn) {
      notifBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        this.togglePanel();
      });
    }

    const closeBtn = document.getElementById("close-notifications");
    if (closeBtn) closeBtn.addEventListener("click", () => this.closePanel());

    const markAllBtn = document.getElementById("mark-all-read-btn");
    if (markAllBtn) markAllBtn.addEventListener("click", () => this.markAllAsRead());

    const clearAllBtn = document.getElementById("clear-all-btn");
    if (clearAllBtn) clearAllBtn.addEventListener("click", () => this.clearAllNotifications());

    document.addEventListener("click", (e) => {
      if (
        this.isOpen &&
        this.panel &&
        !this.panel.contains(e.target) &&
        !e.target.closest('button[title="Notifications"]')
      ) {
        this.closePanel();
      }
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && this.isOpen) this.closePanel();
    });
  }

  async loadNotifications() {
    try {
      const response = await fetch("api/notifications.php?action=get_notifications", {
        cache: "no-store",
        credentials: "include",
      });
      const data = await response.json();

      if (data.success) {
        this.notifications = Array.isArray(data.notifications) ? data.notifications : [];
        this.unreadCount = Number.isFinite(data.unread_count) ? data.unread_count : 0;
        this.updateUI();
      } else {
        this.showError(data.message || "Failed to load notifications");
      }
    } catch (error) {
      console.error("Error loading notifications:", error);
      this.showError("Failed to load notifications");
    }
  }

  updateUI() {
    this.updateBadge();
    this.renderNotifications();
  }

  updateBadge() {
    if (!this.badge) return;

    if (this.unreadCount > 0) {
      this.badge.textContent = this.unreadCount > 99 ? "99+" : String(this.unreadCount);
      this.badge.style.display = "block";
    } else {
      this.badge.style.display = "none";
    }
  }

  renderNotifications() {
    const body = document.getElementById("notifications-body");
    if (!body) return;

    if (!this.notifications.length) {
      body.innerHTML = `
        <div class="empty-notifications">
          <div class="empty-notifications-icon">🔕</div>
          <div style="font-weight: 600; margin-bottom: 8px;">No notifications</div>
          <div style="font-size: 13px;">You're all caught up!</div>
        </div>
      `;
      return;
    }

    body.innerHTML = this.notifications.map((n) => this.createNotificationHTML(n)).join("");

    body.querySelectorAll(".notification-item").forEach((item, index) => {
      const notifBody = item.querySelector(".notification-content");
      if (notifBody) {
        notifBody.addEventListener("click", () => this.handleNotificationClick(this.notifications[index]));
      }

      const deleteBtn = item.querySelector(".delete-notification");
      if (deleteBtn) {
        deleteBtn.addEventListener("click", (e) => {
          e.stopPropagation();
          this.deleteNotification(this.notifications[index].id);
        });
      }
    });
  }

  createNotificationHTML(notif) {
    // ✅ FIX: for "ago" label, prefer created_at/timestamp created time, not expiry_time
    const createdTs = notif.created_at || notif.timestamp;

    // Optional: if backend sends expires_at, show expiry countdown in the message (already handled by PHP usually)
    const timeAgo = this.getTimeAgo(createdTs);

    const unreadClass = notif.is_read ? "" : "unread";
    const priorityClass = notif.priority === "high" ? "priority-high" : "";

    return `
      <div class="notification-item ${unreadClass} ${priorityClass}" data-id="${this.escapeAttr(notif.id)}">
        <div class="notification-content">
          <div class="notification-header">
            <span class="notification-icon">${this.escapeHtml(String(notif.icon || "🔔"))}</span>
            <span class="notification-title">${this.escapeHtml(String(notif.title || "Notification"))}</span>
            <span class="notification-time">${this.escapeHtml(timeAgo)}</span>
          </div>
          <div class="notification-message">${this.escapeHtml(String(notif.message || ""))}</div>
        </div>
        <button class="delete-notification" title="Delete notification">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M12 4L4 12M4 4L12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
    `;
  }

 handleNotificationClick(notif) {
  this.markAsRead(notif.id);

  const type = String(notif.type || "").toLowerCase().trim();
  const title = String(notif.title || "").toLowerCase();
  const msg = String(notif.message || "").toLowerCase();

  // ✅ Route 1: File shared TO you (by others) -> download.html
  const sharedToYou =
    type === "file_shared_with_you" ||
    type === "shared_with_you" ||
    type === "received_share" ||
    type.includes("shared_with_you") ||
    (type.includes("shared") && (msg.includes("with you") || msg.includes("to you") || title.includes("shared with you")));

  if (sharedToYou) {
    window.location.href = "download.html";
    return;
  }

  const sharedByYou =
    type === "file_shared" ||
    type === "file_shared_by_you" ||
    type === "you_shared_file" ||
    type.includes("shared_by_you") ||
    (type.includes("shared") && (msg.includes("you shared") || msg.includes("shared by you") || title.includes("file shared")));

  if (sharedByYou) {
    window.location.href = "shared_files.html";
    return;
  }


  window.location.href = "download.html";
}



  async markAsRead(notificationId) {
    try {
      await fetch("api/notifications.php?action=mark_read", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({ notification_id: notificationId }),
      });

      const notif = this.notifications.find((n) => n.id === notificationId);
      if (notif && !notif.is_read) {
        notif.is_read = true;
        this.unreadCount = Math.max(0, this.unreadCount - 1);
        this.updateUI();
      }
    } catch (error) {
      console.error("Error marking notification as read:", error);
    }
  }

  async markAllAsRead() {
    try {
      const response = await fetch("api/notifications.php?action=mark_all_read", {
        method: "POST",
        credentials: "include",
      });
      const data = await response.json();

      if (data.success) {
        this.notifications.forEach((n) => (n.is_read = true));
        this.unreadCount = 0;
        this.updateUI();
        this.showToast("✓ All notifications marked as read");
      } else {
        this.showToast("❌ Failed to mark all as read");
      }
    } catch (error) {
      console.error("Error marking all as read:", error);
      this.showToast("❌ Failed to mark all as read");
    }
  }

  async deleteNotification(notificationId) {
    try {
      const response = await fetch("api/notifications.php?action=delete_notification", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({ notification_id: notificationId }),
      });
      const data = await response.json();

      if (data.success) {
        const index = this.notifications.findIndex((n) => n.id === notificationId);
        if (index !== -1) {
          const wasUnread = !this.notifications[index].is_read;
          this.notifications.splice(index, 1);
          if (wasUnread) this.unreadCount = Math.max(0, this.unreadCount - 1);
          this.updateUI();
          this.showToast("🗑️ Notification deleted");
        }
      } else {
        this.showToast("❌ Failed to delete notification");
      }
    } catch (error) {
      console.error("Error deleting notification:", error);
      this.showToast("❌ Failed to delete notification");
    }
  }

  async clearAllNotifications() {
    if (!confirm("Are you sure you want to clear all notifications? This action cannot be undone.")) {
      return;
    }

    try {
      const response = await fetch("api/notifications.php?action=clear_all", {
        method: "POST",
        credentials: "include",
      });
      const data = await response.json();

      if (data.success) {
        this.notifications = [];
        this.unreadCount = 0;
        this.updateUI();
        this.showToast("🗑️ All notifications cleared");
      } else {
        this.showToast("❌ Failed to clear notifications");
      }
    } catch (error) {
      console.error("Error clearing all notifications:", error);
      this.showToast("❌ Failed to clear notifications");
    }
  }

  togglePanel() {
    this.isOpen ? this.closePanel() : this.openPanel();
  }

  openPanel() {
    if (!this.panel) return;
    this.panel.classList.add("open");
    this.isOpen = true;
    this.loadNotifications();
  }

  closePanel() {
    if (!this.panel) return;
    this.panel.classList.remove("open");
    this.isOpen = false;
  }

  startAutoRefresh() {
    this.refreshInterval = setInterval(() => {
      if (!this.isOpen) this.loadNotifications();
    }, 30000);
  }

  stopAutoRefresh() {
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
  }

  // ✅ Updated per your rule:
  // <24h show hours only, >=24h show days+hours+minutes, and handle future timestamps safely.
  getTimeAgo(timestamp) {
    const now = Date.now();
    const time = new Date(timestamp).getTime();

    if (Number.isNaN(time)) return "";

    let diffMs = now - time;

    // If timestamp is in the future, treat it as "Just now" (prevents weird "ago" bugs)
    if (diffMs < 0) diffMs = 0;

    const minutesTotal = Math.floor(diffMs / 60000);
    const hoursTotal = Math.floor(diffMs / 3600000);
    const days = Math.floor(diffMs / 86400000);

    if (minutesTotal < 1) return "Just now";
    if (minutesTotal < 60) return `${minutesTotal} min ago`;

    if (hoursTotal < 24) {
      return hoursTotal === 1 ? "1 hour ago" : `${hoursTotal} hours ago`;
    }

    const remainingHours = hoursTotal % 24;
    const remainingMinutes = minutesTotal % 60;

    const parts = [];
    parts.push(days === 1 ? "1 day" : `${days} days`);

    if (remainingHours > 0) {
      parts.push(remainingHours === 1 ? "1 hour" : `${remainingHours} hours`);
    }
    if (remainingMinutes > 0) {
      parts.push(remainingMinutes === 1 ? "1 minute" : `${remainingMinutes} minutes`);
    }

    return parts.join(" ") + " ago";
  }

  showError(message) {
    const body = document.getElementById("notifications-body");
    if (!body) return;

    body.innerHTML = `
      <div style="padding: 40px 20px; text-align: center; color: #ef4444;">
        <div style="font-size: 32px; margin-bottom: 12px;">⚠️</div>
        <div style="font-weight: 600; margin-bottom: 4px;">Error</div>
        <div style="font-size: 13px;">${this.escapeHtml(message)}</div>
      </div>
    `;
  }

  showToast(message) {
    const toast = document.createElement("div");
    toast.style.cssText = `
      position: fixed;
      bottom: 24px;
      right: 24px;
      background: #1f2933;
      color: white;
      padding: 16px 24px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      z-index: 10001;
      animation: slideIn 0.3s ease;
      font-size: 14px;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
      toast.style.animation = "slideOut 0.3s ease";
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = String(text ?? "");
    return div.innerHTML;
  }

  // for attributes like data-id="..."
  escapeAttr(text) {
    return String(text ?? "").replace(/"/g, "&quot;");
  }

  destroy() {
    this.stopAutoRefresh();
    if (this.panel) this.panel.remove();
    if (this.badge) this.badge.remove();
  }
}

// Initialize notification manager
let notificationManager;

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    notificationManager = new NotificationManager();
    notificationManager.init();
  });
} else {
  notificationManager = new NotificationManager();
  notificationManager.init();
}

// Add CSS animations and styles
const style = document.createElement("style");
style.textContent = `
  @keyframes slideIn {
    from { transform: translateX(400px); opacity: 0; }
    to   { transform: translateX(0); opacity: 1; }
  }

  @keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to   { transform: translateX(400px); opacity: 0; }
  }

  .notification-item {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 12px;
    transition: background-color 0.2s;
  }

  .notification-item:hover { background-color: rgba(0,0,0,0.02); }

  .notification-content { flex: 1; cursor: pointer; }

  .delete-notification {
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    opacity: 0;
  }

  .notification-item:hover .delete-notification { opacity: 1; }

  .delete-notification:hover {
    background-color: #fee2e2;
    color: #dc2626;
  }

  .notifications-footer {
    display: flex;
    gap: 8px;
    padding: 12px;
    border-top: 1px solid #e2e8f0;
  }

  .notif-action-btn {
    flex: 1;
    padding: 8px 16px;
    border: 1px solid #e2e8f0;
    background: white;
    color: #475569;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s;
  }

  .notif-action-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
  }

  .notif-action-btn.clear-btn:hover {
    background: #fee2e2;
    border-color: #fecaca;
    color: #dc2626;
  }

  .mark-all-read { display: none; }
`;
document.head.appendChild(style);
