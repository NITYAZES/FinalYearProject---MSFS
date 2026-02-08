"use strict";

let sharedFiles = [];
let availableUsers = [];
let currentFileId = null;
let currentRecipientId = null;

// Initialize on page load
document.addEventListener("DOMContentLoaded", () => {
  loadSharedFiles();
  setupEventListeners();
});

function setupEventListeners() {
  // Search and filters
  const searchInput = document.getElementById("search-input");
  const sortBy = document.getElementById("sort-by");
  const statusFilter = document.getElementById("status-filter");
  const clearFilters = document.getElementById("clear-filters");

  if (searchInput) {
    searchInput.addEventListener("input", debounce(() => filterAndDisplayFiles(), 300));
  }

  if (sortBy) {
    sortBy.addEventListener("change", () => filterAndDisplayFiles());
  }

  if (statusFilter) {
    statusFilter.addEventListener("change", () => filterAndDisplayFiles());
  }

  if (clearFilters) {
    clearFilters.addEventListener("click", () => {
      if (searchInput) searchInput.value = "";
      if (sortBy) sortBy.value = "newest";
      if (statusFilter) statusFilter.value = "all";
      filterAndDisplayFiles();
    });
  }

  // ✅ CSP-safe delegated handlers for dynamically generated buttons
  setupDelegatedClickHandlers();

  setupModalListeners();
}

function setupDelegatedClickHandlers() {
  const container = document.getElementById("shared-files-container");
  if (!container) return;

  container.addEventListener("click", (e) => {
    const el = e.target.closest("[data-action]");
    if (!el) return;

    const action = el.dataset.action;
    const fileId = el.dataset.fileId || null;

    // Recipient remove needs recipient info
    const recipientEmail = el.dataset.recipientEmail || null;

    switch (action) {
      case "show-encryption-report":
        if (fileId) showEncryptionReport(fileId);
        break;

      case "show-edit-policy":
        if (fileId) showEditPolicyModal(fileId);
        break;

      case "confirm-revoke":
        if (fileId) confirmRevokeShare(fileId);
        break;

      case "confirm-reactivate":
        if (fileId) confirmReactivateShare(fileId);
        break;

      case "confirm-delete":
        if (fileId) confirmDeleteFile(fileId);
        break;

      case "show-remove-recipient":
        if (fileId && recipientEmail) {
          // We only really use email for removal; username is optional display
          const username = el.dataset.recipientUsername || "";
          showRemoveRecipientModal(fileId, recipientEmail, username);
        }
        break;

      default:
        console.warn("Unknown action:", action);
    }
  });
}

function setupModalListeners() {
  // Edit Policy Modal
  const editPolicyCancel = document.getElementById("edit-policy-cancel");
  const editPolicyConfirm = document.getElementById("edit-policy-confirm");

  if (editPolicyCancel) {
    editPolicyCancel.addEventListener("click", () => hideModal("edit-policy-modal"));
  }

  if (editPolicyConfirm) {
    editPolicyConfirm.addEventListener("click", handleEditPolicy);
  }

  // Remove Recipient Modal
  const removeCancel = document.getElementById("remove-cancel");
  const removeConfirm = document.getElementById("remove-confirm");

  if (removeCancel) {
    removeCancel.addEventListener("click", () => hideModal("remove-recipient-modal"));
  }

  if (removeConfirm) {
    removeConfirm.addEventListener("click", handleRemoveRecipient);
  }

  // Delete File Modal
  const deleteCancel = document.getElementById("delete-cancel");
  const deleteConfirm = document.getElementById("delete-confirm");

  if (deleteCancel) {
    deleteCancel.addEventListener("click", () => hideModal("delete-file-modal"));
  }

  if (deleteConfirm) {
    deleteConfirm.addEventListener("click", handleDeleteFile);
  }

  // Encryption Report Modal
  const encryptionClose = document.getElementById("encryption-report-close");
  if (encryptionClose) {
    encryptionClose.addEventListener("click", () => hideModal("encryption-report-modal"));
  }

  // ✅ Feedback close (no inline onclick)
  const feedback = document.getElementById("feedback-message");
  if (feedback) {
    feedback.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-action='hide-feedback']");
      if (!btn) return;
      hideFeedback();
    });
  }

  // Close modals on outside click
  document.querySelectorAll(".modal").forEach((modal) => {
    modal.addEventListener("click", (e) => {
      if (e.target === modal) {
        hideModal(modal.id);
      }
    });
  });
}

async function loadSharedFiles() {
  try {
    showLoading();
    const response = await fetch("api/shared_files.php?action=get_shared_files");

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const text = await response.text();
    console.log("Response text:", text);

    let data;
    try {
      data = JSON.parse(text);
    } catch (parseError) {
      console.error("JSON Parse Error:", parseError);
      console.error("Response was:", text.substring(0, 500));
      throw new Error("Server returned invalid JSON. Check console for details.");
    }

    if (data.success) {
      sharedFiles = data.files;
      updateStats(data.stats);
      filterAndDisplayFiles();
    } else {
      showFeedback("error", data.message || "Failed to load shared files");
    }
  } catch (error) {
    console.error("Error loading shared files:", error);
    showFeedback("error", error.message || "Failed to load shared files. Check console for details.");
  } finally {
    hideLoading();
  }
}

function updateStats(stats) {
  document.getElementById("total-shared").textContent = stats.total_shared || 0;
  document.getElementById("total-recipients").textContent = stats.total_recipients || 0;
  document.getElementById("total-views").textContent = stats.total_views || 0;
  document.getElementById("active-shares").textContent = stats.active_shares || 0;
}

function filterAndDisplayFiles() {
  const searchEl = document.getElementById("search-input");
  const sortEl = document.getElementById("sort-by");
  const statusEl = document.getElementById("status-filter");

  const searchTerm = (searchEl?.value || "").toLowerCase();
  const sortBy = sortEl?.value || "newest";
  const statusFilter = statusEl?.value || "all";

  let filtered = sharedFiles.filter((file) => {
    const matchesSearch = (file.file_name || "").toLowerCase().includes(searchTerm);

    const expiry = file.expiry_time ? new Date(file.expiry_time) : null;
    const isExpired = expiry ? expiry < new Date() : false;
    const isRevoked = file.status === "revoked";

    const matchesStatus =
      statusFilter === "all" ||
      (statusFilter === "active" && file.status === "active" && !isExpired) ||
      (statusFilter === "expired" && isExpired) ||
      (statusFilter === "revoked" && isRevoked);

    return matchesSearch && matchesStatus;
  });

  // Sort
  filtered.sort((a, b) => {
    switch (sortBy) {
      case "oldest":
        return new Date(a.uploaded_at) - new Date(b.uploaded_at);
      case "most-recipients":
        return (b.recipient_count || 0) - (a.recipient_count || 0);
      case "most-views":
        return (b.total_views || 0) - (a.total_views || 0);
      case "name-asc":
        return (a.file_name || "").localeCompare(b.file_name || "");
      case "name-desc":
        return (b.file_name || "").localeCompare(a.file_name || "");
      case "size-asc":
        return (a.file_size || 0) - (b.file_size || 0);
      case "size-desc":
        return (b.file_size || 0) - (a.file_size || 0);
      case "newest":
      default:
        return new Date(b.uploaded_at) - new Date(a.uploaded_at);
    }
  });

  displayFiles(filtered);
}

function displayFiles(files) {
  const container = document.getElementById("shared-files-container");
  if (!container) return;

  if (files.length === 0) {
    container.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">🔭</div>
        <h3 class="empty-title">No Shared Files</h3>
        <p class="empty-text">You haven't shared any files yet, or no files match your filters.</p>
      </div>
    `;
    return;
  }

  container.innerHTML = files.map((file) => createFileCard(file)).join("");

  // Load recipients for each file
  files.forEach((file) => {
    loadRecipients(file.file_id);
  });
}

function createFileCard(file) {
  const expiry = file.expiry_time ? new Date(file.expiry_time) : null;
  const now = new Date();
  const isExpired = expiry && expiry < now;
  const isRevoked = file.status === "revoked";

  const maxCount = parseInt(file.max_decrypt_count, 10) || 0;
  const decryptCount = parseInt(file.decrypt_count, 10) || 0;
  const isMaxedOut = maxCount > 0 && decryptCount >= maxCount;

  // Status badge
  let statusBadge = "";
  let cardClass = "";
  
  if (isRevoked) {
    statusBadge = '<span class="status-badge revoked">🚫 Revoked</span>';
    cardClass = "revoked";
  } else if (isExpired) {
    statusBadge = '<span class="status-badge expired">⏰ Expired</span>';
    cardClass = "expired";
  } else if (isMaxedOut) {
    statusBadge = '<span class="status-badge maxed">📊 Max Downloads</span>';
    cardClass = "maxed";
  } else {
    statusBadge = '<span class="status-badge active">✓ Active</span>';
    cardClass = "active";
  }

  // Encryption badge
  let encryptionBadge = "";
  if (file.encryption_rating) {
    const rating = file.encryption_rating;
    const score = file.encryption_score || 0;
    let emoji = "🔒";
    let ratingClass = "good";

    if (rating === "Excellent" || rating === "Superior") {
      emoji = "🛡️";
      ratingClass = "excellent";
    } else if (rating === "Good") {
      emoji = "🔐";
      ratingClass = "good";
    } else {
      emoji = "🔓";
      ratingClass = "fair";
    }

    encryptionBadge = `
      <button 
        type="button"
        class="encryption-badge ${ratingClass}"
        data-action="show-encryption-report"
        data-file-id="${escapeHtml(file.file_id)}"
        title="Click for encryption report"
      >
        ${emoji} ${escapeHtml(rating)} (${Math.round(score)})
      </button>
    `;
  }

  // Action buttons - different for revoked vs active files
  let actionButtons = "";
  if (isRevoked) {
    actionButtons = `
      <button
        type="button"
        class="btn btn-success btn-sm"
        data-action="confirm-reactivate"
        data-file-id="${escapeHtml(file.file_id)}"
        title="Restore access to all recipients"
      >
        ♻️ Reactivate
      </button>
      <button
        type="button"
        class="btn btn-danger btn-sm"
        data-action="confirm-delete"
        data-file-id="${escapeHtml(file.file_id)}"
        title="Permanently delete this file"
      >
        🗑️ Delete
      </button>
    `;
  } else {
    actionButtons = `
      <button
        type="button"
        class="btn btn-primary btn-sm"
        data-action="show-edit-policy"
        data-file-id="${escapeHtml(file.file_id)}"
        title="Edit expiry and download limits"
      >
        ⚙️ Edit Policy
      </button>
      <button
        type="button"
        class="btn btn-warning btn-sm"
        data-action="confirm-revoke"
        data-file-id="${escapeHtml(file.file_id)}"
        title="Revoke access for all recipients"
      >
        🔐 Revoke Access
      </button>
      <button
        type="button"
        class="btn btn-danger btn-sm"
        data-action="confirm-delete"
        data-file-id="${escapeHtml(file.file_id)}"
        title="Permanently delete this file"
      >
        🗑️ Delete
      </button>
    `;
  }

  return `
    <div class="file-card ${cardClass}" data-file-id="${escapeHtml(file.file_id)}">
      <div class="file-header">
        <div class="file-icon">${getFileIcon(file.mime_type)}</div>
        <div class="file-info">
          <h3 class="file-name">${escapeHtml(file.file_name)}</h3>
          <div class="file-meta">
            <span>${formatFileSize(file.file_size)}</span>
            <span>•</span>
            <span>Uploaded ${formatDate(file.uploaded_at)}</span>
          </div>
        </div>
        <div class="file-badges">
          ${statusBadge}
          ${encryptionBadge}
        </div>
      </div>

      <div class="file-stats">
        <div class="stat">
          <span class="stat-label">Recipients</span>
          <span class="stat-value">${file.recipient_count || 0}</span>
        </div>
        <div class="stat">
          <span class="stat-label">Downloads</span>
          <span class="stat-value">${decryptCount} / ${maxCount || "∞"}</span>
        </div>
        <div class="stat">
          <span class="stat-label">Expires</span>
          <span class="stat-value">${formatExpiryDate(file.expiry_time)}</span>
        </div>
      </div>

      <div class="recipients-container" id="recipients-${escapeHtml(file.file_id)}">
        <div class="recipients-loading">Loading recipients...</div>
      </div>

      <div class="file-actions">
        ${actionButtons}
      </div>
    </div>
  `;
}

async function loadRecipients(fileId) {
  const container = document.getElementById(`recipients-${fileId}`);
  if (!container) return;

  const file = sharedFiles.find((f) => f.file_id === fileId);
  if (!file || !file.recipients || file.recipients.length === 0) {
    container.innerHTML = '<p class="no-recipients">No recipients</p>';
    return;
  }

  const isRevoked = file.status === "revoked";

  const recipientsHtml = file.recipients
    .map((recipient) => {
      const removeButton = isRevoked ? '' : `
        <button
          type="button"
          class="recipient-remove"
          data-action="show-remove-recipient"
          data-file-id="${escapeHtml(fileId)}"
          data-recipient-email="${escapeHtml(recipient.email)}"
          data-recipient-username="${escapeHtml(recipient.username || '')}"
          title="Remove this recipient"
        >
          ×
        </button>
      `;

      return `
        <div class="recipient-item">
          <div class="recipient-avatar">${getInitials(recipient.name || recipient.username)}</div>
          <div class="recipient-info">
            <div class="recipient-name">${escapeHtml(recipient.name || recipient.username)}</div>
            <div class="recipient-email">${escapeHtml(recipient.email)}</div>
          </div>
          ${removeButton}
        </div>
      `;
    })
    .join("");

  container.innerHTML = `
    <div class="recipients-header">
      <span class="recipients-title">Recipients (${file.recipients.length})</span>
    </div>
    <div class="recipients-list">
      ${recipientsHtml}
    </div>
  `;
}

function showEditPolicyModal(fileId) {
  const file = sharedFiles.find((f) => f.file_id === fileId);
  if (!file) return;

  currentFileId = fileId;

  // Pre-fill current values
  const expiryInput = document.getElementById("edit-expiry-time");
  const maxDownloadsInput = document.getElementById("edit-max-downloads");

  if (expiryInput && file.expiry_time) {
    const expiryDate = new Date(file.expiry_time);
    const localDateTime = new Date(expiryDate.getTime() - expiryDate.getTimezoneOffset() * 60000)
      .toISOString()
      .slice(0, 16);
    expiryInput.value = localDateTime;
  }

  if (maxDownloadsInput) {
    maxDownloadsInput.value = file.max_decrypt_count || 10;
  }

  const titleEl = document.getElementById("edit-policy-title");
  if (titleEl) {
    titleEl.textContent = `Edit Policy: ${file.file_name}`;
  }

  showModal("edit-policy-modal");
}

function showRemoveRecipientModal(fileId, recipientEmail, recipientUsername) {
  currentFileId = fileId;
  currentRecipientId = recipientEmail;

  const messageEl = document.getElementById("remove-recipient-message");
  if (messageEl) {
    const displayName = recipientUsername || recipientEmail;
    messageEl.textContent = `Are you sure you want to remove ${displayName} from this file? They will no longer be able to access it.`;
  }

  showModal("remove-recipient-modal");
}

function confirmDeleteFile(fileId) {
  const file = sharedFiles.find((f) => f.file_id === fileId);
  if (!file) return;

  currentFileId = fileId;

  const messageEl = document.getElementById("delete-file-message");
  if (messageEl) {
    messageEl.textContent = `Are you sure you want to permanently delete "${file.file_name}"? This action cannot be undone and will remove the file for all ${file.recipient_count} recipient(s).`;
  }

  showModal("delete-file-modal");
}

function showEncryptionReport(fileId) {
  const file = sharedFiles.find((f) => f.file_id === fileId);
  if (!file || !file.encryption_metrics_json) {
    showFeedback("error", "No encryption metrics available for this file");
    return;
  }

  let metrics;
  try {
    metrics = JSON.parse(file.encryption_metrics_json);
  } catch (e) {
    showFeedback("error", "Failed to parse encryption metrics");
    return;
  }

  const titleEl = document.getElementById("encryption-report-title");
  const contentEl = document.getElementById("encryption-report-content");

  if (titleEl) {
    titleEl.textContent = `Encryption Report: ${file.file_name}`;
  }

  if (contentEl) {
    contentEl.innerHTML = `
      <div class="encryption-summary">
        <div class="encryption-score-large">
          <div class="score-value">${Math.round(file.encryption_score || 0)}</div>
          <div class="score-label">${file.encryption_rating || "N/A"}</div>
        </div>
        <div class="encryption-meta">
          <p><strong>Encryption Time:</strong> ${file.encryption_time_ms || 0}ms</p>
          <p><strong>Size Overhead:</strong> ${file.size_overhead_percent || 0}%</p>
        </div>
      </div>
      <div class="encryption-breakdown">
        <h4>Breakdown</h4>
        ${Object.entries(metrics)
          .map(
            ([key, value]) => `
          <div class="breakdown-item">
            <span class="breakdown-label">${escapeHtml(key)}:</span>
            <span class="breakdown-value">${escapeHtml(String(value))}</span>
          </div>
        `
          )
          .join("")}
      </div>
    `;
  }

  showModal("encryption-report-modal");
}

async function handleEditPolicy() {
  const expiryTime = document.getElementById("edit-expiry-time").value;
  const maxDownloads = document.getElementById("edit-max-downloads").value;

  if (!expiryTime || !maxDownloads) {
    showFeedback("error", "Please fill in all fields");
    return;
  }

  if (parseInt(maxDownloads, 10) < 1) {
    showFeedback("error", "Maximum downloads must be at least 1");
    return;
  }

  try {
    const response = await fetch("api/shared_files.php?action=edit_policy", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        file_id: currentFileId,
        expiry_time: expiryTime,
        max_decrypt_count: parseInt(maxDownloads, 10),
      }),
    });

    const data = await response.json();

    if (data.success) {
      showFeedback("success", "Policy updated successfully");
      hideModal("edit-policy-modal");
      loadSharedFiles();
    } else {
      showFeedback("error", data.message);
    }
  } catch (error) {
    console.error("Error editing policy:", error);
    showFeedback("error", "Failed to update policy");
  }
}

async function handleRemoveRecipient() {
  try {
    const response = await fetch("api/shared_files.php?action=remove_recipient", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        file_id: currentFileId,
        recipient_email: currentRecipientId,
      }),
    });

    const data = await response.json();

    if (data.success) {
      showFeedback("success", "Recipient removed successfully");
      hideModal("remove-recipient-modal");
      loadSharedFiles();
    } else {
      showFeedback("error", data.message);
    }
  } catch (error) {
    console.error("Error removing recipient:", error);
    showFeedback("error", "Failed to remove recipient");
  }
}

async function handleDeleteFile() {
  try {
    const response = await fetch("api/shared_files.php?action=delete_file", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ file_id: currentFileId }),
    });

    const data = await response.json();

    if (data.success) {
      showFeedback("success", "File deleted permanently");
      hideModal("delete-file-modal");
      loadSharedFiles();
    } else {
      showFeedback("error", data.message);
    }
  } catch (error) {
    console.error("Error deleting file:", error);
    showFeedback("error", "Failed to delete file");
  }
}

async function confirmRevokeShare(fileId) {
  if (
    !confirm(
      '⚠️ REVOKE ACCESS - This will:\n\n• Prevent ALL recipients from downloading this file\n• Mark the file as "Revoked"\n• Keep the file in your system (can be reactivated later)\n\nAre you sure?'
    )
  ) {
    return;
  }

  try {
    const response = await fetch("api/shared_files.php?action=revoke_share", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ file_id: fileId }),
    });

    const data = await response.json();

    if (data.success) {
      showFeedback("success", data.message);
      loadSharedFiles();
    } else {
      showFeedback("error", data.message);
    }
  } catch (error) {
    console.error("Error revoking share:", error);
    showFeedback("error", "Failed to revoke access");
  }
}

async function confirmReactivateShare(fileId) {
  if (
    !confirm(
      '✅ REACTIVATE FILE - This will:\n\n• Restore access for ALL recipients\n• Change status from "Revoked" to "Active"\n• Notify recipients that the file is available again\n\nAre you sure?'
    )
  ) {
    return;
  }

  try {
    const response = await fetch("api/shared_files.php?action=reactivate_share", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ file_id: fileId }),
    });

    const data = await response.json();

    if (data.success) {
      showFeedback("success", data.message);
      loadSharedFiles();
    } else {
      showFeedback("error", data.message);
    }
  } catch (error) {
    console.error("Error reactivating share:", error);
    showFeedback("error", "Failed to reactivate file");
  }
}

// Helper Functions
function showModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) modal.classList.add("show");
}

function hideModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) modal.classList.remove("show");
}

function showLoading() {
  const container = document.getElementById("shared-files-container");
  if (!container) return;
  container.innerHTML = `
    <div class="loading-state">
      <div class="loading-spinner"></div>
      <p class="loading-text">Loading shared files...</p>
    </div>
  `;
}

function hideLoading() {
  // Loading is replaced by content
}

function showFeedback(type, message) {
  const feedback = document.getElementById("feedback-message");
  if (!feedback) return;

  feedback.className = `show ${type}`;

  // ✅ CSP-safe: no inline onclick
  feedback.innerHTML = `
    <span class="feedback-text">${escapeHtml(String(message))}</span>
    <button type="button" class="feedback-close" data-action="hide-feedback" aria-label="Close">×</button>
  `;

  setTimeout(() => hideFeedback(), 5000);
}

function hideFeedback() {
  const feedback = document.getElementById("feedback-message");
  if (!feedback) return;
  feedback.classList.remove("show");
}

function getFileIcon(mimeType) {
  if (!mimeType) return "📄";
  if (mimeType.startsWith("image/")) return "🖼️";
  if (mimeType.startsWith("video/")) return "🎥";
  if (mimeType.startsWith("audio/")) return "🎵";
  if (mimeType.includes("pdf")) return "📕";
  if (mimeType.includes("word") || mimeType.includes("document")) return "📘";
  if (mimeType.includes("sheet") || mimeType.includes("excel")) return "📗";
  if (mimeType.includes("zip") || mimeType.includes("rar")) return "🗜️";
  return "📄";
}

function getInitials(name) {
  if (!name) return "?";
  return name
    .split(" ")
    .filter(Boolean)
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .substring(0, 2);
}

function formatFileSize(bytes) {
  const b = Number(bytes) || 0;
  if (b === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(b) / Math.log(k));
  return Math.round((b / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
}

function formatDate(dateString) {
  if (!dateString) return "-";
  const date = new Date(dateString);
  if (Number.isNaN(date.getTime())) return "-";

  const now = new Date();
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return "Just now";
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;

  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function formatExpiryDate(dateString) {
  if (!dateString) return "Never";
  const date = new Date(dateString);
  if (Number.isNaN(date.getTime())) return "-";

  const now = new Date();
  const diffMs = date - now;
  
  // If already expired
  if (diffMs < 0) {
    return "Expired";
  }

  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  // Future time formatting
  if (diffMins < 60) return `in ${diffMins}m`;
  if (diffHours < 24) return `in ${diffHours}h`;
  if (diffDays < 7) return `in ${diffDays}d`;

  // For dates further in the future, show the actual date
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = String(text ?? "");
  return div.innerHTML;
}

function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}