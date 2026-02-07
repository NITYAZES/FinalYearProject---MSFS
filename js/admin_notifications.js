/**
 * Admin Notifications System
 * Handles fetching, displaying, and managing admin notifications
 */

const NotificationAPI = {
  baseUrl: './api/admin_notifications.php',

  async fetchNotifications(options = {}) {
    const { filter = 'all', category = '', priority = '', limit = 50, offset = 0 } = options;
    const params = new URLSearchParams({
      filter,
      limit: limit.toString(),
      offset: offset.toString(),
    });

    if (category) params.append('category', category);
    if (priority) params.append('priority', priority);

    const res = await fetch(`${this.baseUrl}?${params.toString()}`, {
      method: 'GET',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async getUnreadCount() {
    const res = await fetch(`${this.baseUrl}?action=count`, {
      method: 'GET',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async getSummary() {
    const res = await fetch(`${this.baseUrl}?action=summary`, {
      method: 'GET',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async markAsRead(notificationIds) {
    const res = await fetch(`${this.baseUrl}?action=mark_read`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({ notification_ids: notificationIds }),
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async markAllAsRead() {
    const res = await fetch(`${this.baseUrl}?action=mark_all_read`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({}),
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async dismiss(notificationIds) {
    const res = await fetch(`${this.baseUrl}?action=dismiss`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({ notification_ids: notificationIds }),
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async delete(notificationIds) {
    const ids = notificationIds.join(',');
    const res = await fetch(`${this.baseUrl}?ids=${ids}`, {
      method: 'DELETE',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },

  async create(notification) {
    const res = await fetch(`${this.baseUrl}?action=create`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(notification),
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  },
};

class NotificationManager {
  constructor() {
    this.notifications = [];
    this.unreadCount = 0;
    this.currentFilter = 'all';
    this.currentCategory = '';
    this.currentPriority = '';
    this.selectedIds = new Set();
  }

  async init() {
    this.bindEvents();
    await this.loadNotifications();
    await this.updateUnreadCount();
    this.startPolling();
  }

  bindEvents() {
    document.querySelectorAll('.filter-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        this.currentFilter = e.target.dataset.filter || 'all';
        document.querySelectorAll('.filter-btn').forEach((b) => b.classList.remove('active'));
        e.target.classList.add('active');
        this.loadNotifications();
      });
    });

    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) {
      categoryFilter.addEventListener('change', (e) => {
        this.currentCategory = e.target.value;
        this.loadNotifications();
      });
    }

    const priorityFilter = document.getElementById('priorityFilter');
    if (priorityFilter) {
      priorityFilter.addEventListener('change', (e) => {
        this.currentPriority = e.target.value;
        this.loadNotifications();
      });
    }

    const markAllBtn = document.getElementById('markAllRead');
    if (markAllBtn) markAllBtn.addEventListener('click', () => this.markAllAsRead());

    const refreshBtn = document.getElementById('refreshNotifications');
    if (refreshBtn) refreshBtn.addEventListener('click', () => this.loadNotifications());

    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
      selectAllCheckbox.addEventListener('change', (e) => this.selectAll(e.target.checked));
    }

    const bulkMarkRead = document.getElementById('bulkMarkRead');
    if (bulkMarkRead) bulkMarkRead.addEventListener('click', () => this.bulkMarkAsRead());

    const bulkDismiss = document.getElementById('bulkDismiss');
    if (bulkDismiss) bulkDismiss.addEventListener('click', () => this.bulkDismiss());

    const bulkDelete = document.getElementById('bulkDelete');
    if (bulkDelete) bulkDelete.addEventListener('click', () => this.bulkDelete());
  }

  async loadNotifications() {
    try {
      const result = await NotificationAPI.fetchNotifications({
        filter: this.currentFilter,
        category: this.currentCategory,
        priority: this.currentPriority,
        limit: 100,
      });

      if (result.ok) {
        this.notifications = result.notifications;
        this.renderNotifications();
      } else {
        this.showError(result.error || 'Failed to load notifications');
      }
    } catch (err) {
      console.error('Load notifications error:', err);
      this.showError('Failed to load notifications');
    }
  }

  async updateUnreadCount() {
    try {
      const result = await NotificationAPI.getUnreadCount();
      if (result.ok) {
        this.unreadCount = result.unread_count;
        this.updateUnreadBadge();
      }
    } catch (err) {
      console.error('Update count error:', err);
    }
  }

  updateUnreadBadge() {
    const badge = document.getElementById('unreadCount');
    if (badge) {
      badge.textContent = this.unreadCount;
      badge.style.display = this.unreadCount > 0 ? 'inline-block' : 'none';
    }
  }

  renderNotifications() {
    const container = document.getElementById('notificationsContainer');
    if (!container) return;

    if (this.notifications.length === 0) {
      container.innerHTML = `
        <div class="no-notifications">
          <p>No notifications found</p>
        </div>
      `;
      return;
    }

    container.innerHTML = this.notifications.map((notif) => this.renderNotification(notif)).join('');
    this.bindNotificationEvents();
  }

  renderNotification(notif) {
    const priorityClass = this.getPriorityClass(notif.priority);
    const categoryIcon = this.getCategoryIcon(notif.category);
    const unreadClass = notif.is_read ? '' : 'unread';
    const timeAgo = this.getTimeAgo(notif.created_at);

    return `
      <div class="notification-item ${unreadClass} priority-${priorityClass}" data-id="${notif.id}">
        <div class="notification-select">
          <input type="checkbox" class="notif-checkbox" data-id="${notif.id}">
        </div>
        <div class="notification-icon ${priorityClass}">
          ${categoryIcon}
        </div>
        <div class="notification-content">
          <div class="notification-header">
            <span class="notification-title">${this.escapeHtml(notif.title)}</span>
            <span class="notification-priority priority-${priorityClass}">${String(notif.priority || '').toUpperCase()}</span>
          </div>
          <div class="notification-message">${this.escapeHtml(notif.message)}</div>
          <div class="notification-meta">
            <span class="notification-category">${this.escapeHtml(notif.category || '')}</span>
            <span class="notification-time">${timeAgo}</span>
          </div>
          ${notif.action_url ? `<a href="${notif.action_url}" class="notification-action">View Details →</a>` : ''}
        </div>
        <div class="notification-actions">
          ${!notif.is_read ? `<button class="btn-icon mark-read" data-id="${notif.id}" title="Mark as read">✓</button>` : ''}
          <button class="btn-icon dismiss" data-id="${notif.id}" title="Dismiss">✕</button>
        </div>
      </div>
    `;
  }

  bindNotificationEvents() {
    document.querySelectorAll('.notif-checkbox').forEach((cb) => {
      cb.addEventListener('change', (e) => {
        const id = parseInt(e.target.dataset.id, 10);
        if (e.target.checked) this.selectedIds.add(id);
        else this.selectedIds.delete(id);
        this.updateBulkActionsVisibility();
      });
    });

    document.querySelectorAll('.mark-read').forEach((btn) => {
      btn.addEventListener('click', async (e) => {
        const id = parseInt(e.target.dataset.id, 10);
        await this.markAsRead([id]);
      });
    });

    document.querySelectorAll('.dismiss').forEach((btn) => {
      btn.addEventListener('click', async (e) => {
        const id = parseInt(e.target.dataset.id, 10);
        await this.dismiss([id]);
      });
    });
  }

  async markAsRead(ids) {
    try {
      const result = await NotificationAPI.markAsRead(ids);
      if (result.ok) {
        await this.loadNotifications();
        await this.updateUnreadCount();
        this.showSuccess(`Marked ${result.updated} notification(s) as read`);
      }
    } catch (err) {
      console.error('Mark as read error:', err);
      this.showError('Failed to mark as read');
    }
  }

  async markAllAsRead() {
    if (!confirm('Mark all notifications as read?')) return;

    try {
      const result = await NotificationAPI.markAllAsRead();
      if (result.ok) {
        await this.loadNotifications();
        await this.updateUnreadCount();
        this.showSuccess(`Marked ${result.updated} notification(s) as read`);
      }
    } catch (err) {
      console.error('Mark all as read error:', err);
      this.showError('Failed to mark all as read');
    }
  }

  async dismiss(ids) {
    try {
      const result = await NotificationAPI.dismiss(ids);
      if (result.ok) {
        await this.loadNotifications();
        await this.updateUnreadCount();
        this.showSuccess(`Dismissed ${result.dismissed} notification(s)`);
      }
    } catch (err) {
      console.error('Dismiss error:', err);
      this.showError('Failed to dismiss');
    }
  }

  selectAll(checked) {
    this.selectedIds.clear();
    document.querySelectorAll('.notif-checkbox').forEach((cb) => {
      cb.checked = checked;
      if (checked) this.selectedIds.add(parseInt(cb.dataset.id, 10));
    });
    this.updateBulkActionsVisibility();
  }

  async bulkMarkAsRead() {
    if (this.selectedIds.size === 0) return;
    await this.markAsRead(Array.from(this.selectedIds));
    this.selectedIds.clear();
    this.updateBulkActionsVisibility();
  }

  async bulkDismiss() {
    if (this.selectedIds.size === 0) return;
    await this.dismiss(Array.from(this.selectedIds));
    this.selectedIds.clear();
    this.updateBulkActionsVisibility();
  }

  async bulkDelete() {
    if (this.selectedIds.size === 0) return;
    if (!confirm(`Delete ${this.selectedIds.size} notification(s)?`)) return;

    try {
      const result = await NotificationAPI.delete(Array.from(this.selectedIds));
      if (result.ok) {
        await this.loadNotifications();
        await this.updateUnreadCount();
        this.showSuccess(`Deleted ${result.deleted} notification(s)`);
        this.selectedIds.clear();
        this.updateBulkActionsVisibility();
      }
    } catch (err) {
      console.error('Delete error:', err);
      this.showError('Failed to delete');
    }
  }

  updateBulkActionsVisibility() {
    const bulkActions = document.getElementById('bulkActions');
    if (bulkActions) {
      bulkActions.style.display = this.selectedIds.size > 0 ? 'block' : 'none';
    }
  }

  startPolling() {
    setInterval(() => this.updateUnreadCount(), 30000);
  }

  getPriorityClass(priority) {
    const classes = { urgent: 'urgent', high: 'high', normal: 'normal', low: 'low' };
    return classes[priority] || 'normal';
  }

  getCategoryIcon(category) {
    const icons = { security: '🔒', user: '👤', file: '📁', system: '⚙️', audit: '📋' };
    return icons[category] || '📢';
  }

  // ✅ Matches:
  // < 1 min  -> Just now
  // < 60 min -> Xm ago
  // < 24 hrs -> X hours ago
  // >= 24 hrs -> X days Y hours Z minutes ago (but hides 0 parts)
  getTimeAgo(dateString) {
    const time = new Date(dateString).getTime();
    if (Number.isNaN(time)) return '';

    const now = Date.now();
    const diffMs = Math.max(0, now - time);

    const totalMinutes = Math.floor(diffMs / 60000);
    const totalHours = Math.floor(diffMs / 3600000);
    const totalDays = Math.floor(diffMs / 86400000);

    if (totalMinutes < 1) return 'Just now';
    if (totalMinutes < 60) return `${totalMinutes}m ago`;
    if (totalHours < 24) return `${totalHours} hours ago`;

    const remHours = totalHours % 24;
    const remMinutes = totalMinutes % 60;

    const parts = [`${totalDays} days`];
    if (remHours > 0) parts.push(`${remHours} hours`);
    if (remMinutes > 0) parts.push(`${remMinutes} minutes`);

    return `${parts.join(' ')} ago`;
  }

  escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = String(str ?? '');
    return div.innerHTML;
  }

  showSuccess(message) {
    this.showToast(message, 'success');
  }

  showError(message) {
    this.showToast(message, 'error');
  }

  showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 10);

    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const manager = new NotificationManager();
  manager.init();
  window.notificationManager = manager;
});
