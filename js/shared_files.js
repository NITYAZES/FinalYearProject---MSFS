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
    const isRevoked = file.status === "deleted";

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
    loadFileRecipients(file.file_id);
  });
}

function createFileCard(file) {
  const isExpired = file.expiry_time ? new Date(file.expiry_time) < new Date() : false;
  const isRevoked = file.status === "deleted";
  const statusClass = isRevoked ? "revoked" : isExpired ? "expired" : "active";
  const fileIcon = getFileIcon(file.mime_type);

  return `
    <div class="shared-file-card ${statusClass}" data-file-id="${escapeHtml(String(file.file_id))}">
      <div class="file-header">
        <div class="file-info">
          <div class="file-icon-large">${fileIcon}</div>
          <div class="file-details">
            <h3 class="file-name">${escapeHtml(file.file_name)}</h3>
            <div class="file-meta">
              <span class="meta-item">📊 ${formatFileSize(file.file_size || 0)}</span>
              <span class="meta-item">📅 ${formatDate(file.uploaded_at)}</span>
              <span class="meta-item">⏰ Expires: ${formatExpiryDate(file.expiry_time)}</span>
              ${
                file.encryption_score
                  ? `
                <span
                  class="meta-item encryption-score"
                  role="button"
                  tabindex="0"
                  data-action="show-encryption-report"
                  data-file-id="${escapeHtml(String(file.file_id))}"
                  style="cursor: pointer;"
                >
                  🔒 ${escapeHtml(String(file.encryption_score))}% ${escapeHtml(String(file.encryption_rating || ""))}
                </span>
              `
                  : ""
              }
            </div>
            ${isExpired ? '<span class="status-badge expired">⏰ Expired</span>' : ""}
            ${isRevoked ? '<span class="status-badge revoked">🚫 Revoked</span>' : ""}
            ${!isExpired && !isRevoked ? '<span class="status-badge active">✅ Active</span>' : ""}
          </div>
        </div>
        <div class="file-actions">
          ${
            !isRevoked && !isExpired
              ? `
            <button class="action-btn btn-edit" type="button"
              data-action="show-edit-policy"
              data-file-id="${escapeHtml(String(file.file_id))}">
              ✏️ Edit Policy
            </button>
          `
              : ""
          }
          ${
            !isRevoked
              ? `
            <button class="action-btn btn-revoke" type="button"
              data-action="confirm-revoke"
              data-file-id="${escapeHtml(String(file.file_id))}">
              🚫 Revoke Access
            </button>
          `
              : ""
          }
          <button class="action-btn btn-delete" type="button"
            data-action="confirm-delete"
            data-file-id="${escapeHtml(String(file.file_id))}">
            🗑️ Delete
          </button>
        </div>
      </div>

      <div class="file-stats">
        <div class="stat-item">
          <div class="stat-label">Recipients</div>
          <div class="stat-value">${file.recipient_count || 0}</div>
        </div>
        <div class="stat-item">
          <div class="stat-label">Downloads</div>
          <div class="stat-value">${file.decrypt_count || 0}/${file.max_decrypt_count || "∞"}</div>
        </div>
      </div>

      <div class="recipients-section">
        <div class="recipients-header">
          <div class="recipients-title">
            👥 Recipients
            <span class="recipient-count">${file.recipient_count || 0}</span>
          </div>
        </div>
        <div class="recipients-list" id="recipients-${escapeHtml(String(file.file_id))}">
          <div class="loading-text">Loading recipients...</div>
        </div>
      </div>
    </div>
  `;
}

async function loadFileRecipients(fileId) {
  try {
    const file = sharedFiles.find((f) => f.file_id === fileId);
    if (file && file.recipients) {
      displayRecipients(fileId, file.recipients);
    }
  } catch (error) {
    console.error("Error loading recipients:", error);
    const recipientsContainer = document.getElementById(`recipients-${fileId}`);
    if (recipientsContainer) {
      recipientsContainer.innerHTML = '<div class="error-text">Failed to load recipients</div>';
    }
  }
}

function displayRecipients(fileId, recipients) {
  const container = document.getElementById(`recipients-${fileId}`);
  if (!container) return;

  if (!recipients || recipients.length === 0) {
    container.innerHTML = '<div class="empty-text">No recipients yet</div>';
    return;
  }

  container.innerHTML = recipients
    .map((recipient) => {
      const email = recipient.email || "";
      const username = recipient.username || "";
      const name = recipient.name || "";

      return `
      <div class="recipient-item">
        <div class="recipient-info">
          <div class="recipient-avatar">${escapeHtml(getInitials(name))}</div>
          <div class="recipient-details">
            <div class="recipient-email">${escapeHtml(email)}</div>
            <div class="recipient-meta">
              ${escapeHtml(name)} (@${escapeHtml(username)})
            </div>
          </div>
        </div>
        <div class="recipient-actions">
          <button class="recipient-btn btn-remove" type="button"
            data-action="show-remove-recipient"
            data-file-id="${escapeHtml(String(fileId))}"
            data-recipient-email="${escapeHtml(email)}"
            data-recipient-username="${escapeHtml(username)}">
            ❌ Remove
          </button>
        </div>
      </div>
    `;
    })
    .join("");
}

// Modal Functions
function showEditPolicyModal(fileId) {
  currentFileId = fileId;
  const file = sharedFiles.find((f) => f.file_id === fileId);

  if (!file) return;

  document.getElementById("edit-file-name-display").textContent = file.file_name;

  // Convert expiry_time to datetime-local format
  const expiryDate = new Date(file.expiry_time);
  const year = expiryDate.getFullYear();
  const month = String(expiryDate.getMonth() + 1).padStart(2, "0");
  const day = String(expiryDate.getDate()).padStart(2, "0");
  const hours = String(expiryDate.getHours()).padStart(2, "0");
  const minutes = String(expiryDate.getMinutes()).padStart(2, "0");
  const datetimeLocal = `${year}-${month}-${day}T${hours}:${minutes}`;

  document.getElementById("edit-expiry-time").value = datetimeLocal;
  document.getElementById("edit-max-downloads").value = file.max_decrypt_count || "";

  showModal("edit-policy-modal");
}

function showRemoveRecipientModal(fileId, email, username) {
  currentFileId = fileId;
  document.getElementById("remove-recipient-email").textContent = email;
  currentRecipientId = email;
  showModal("remove-recipient-modal");
}

function confirmDeleteFile(fileId) {
  currentFileId = fileId;
  const file = sharedFiles.find((f) => f.file_id === fileId);
  if (file) {
    document.getElementById("delete-file-name").textContent = file.file_name;
  }
  showModal("delete-file-modal");
}

async function showEncryptionReport(fileId) {
  const file = sharedFiles.find((f) => f.file_id === fileId);
  if (!file) return;

  const reportContent = document.getElementById("encryption-report-content");
  reportContent.innerHTML = `
    <div class="report-section">
      <div class="report-header">
        <div class="report-icon">📊</div>
        <div>
          <h3 class="report-title">${escapeHtml(file.file_name)}</h3>
          <p class="report-subtitle">Encryption Analysis Report</p>
        </div>
      </div>
    </div>

    <div class="report-section">
      <div class="score-display">
        <div class="score-circle ${file.encryption_rating ? escapeHtml(file.encryption_rating.toLowerCase()) : "unknown"}">
          <div class="score-number">${file.encryption_score || "N/A"}%</div>
          <div class="score-label">${escapeHtml(file.encryption_rating || "Unknown")}</div>
        </div>
      </div>
    </div>

    <div class="report-section">
      <h4 class="section-title">🔍 Encryption Details</h4>
      <div class="detail-grid">
        <div class="detail-item">
          <span class="detail-label">Algorithm:</span>
          <span class="detail-value">AES-256-GCM</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Key Length:</span>
          <span class="detail-value">256 bits</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Mode:</span>
          <span class="detail-value">Galois/Counter Mode</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Status:</span>
          <span class="detail-value status-${escapeHtml(String(file.status || ""))}">
            ${escapeHtml(String(file.status || "")).toUpperCase()}
          </span>
        </div>
      </div>
    </div>

    <div class="report-section">
      <h4 class="section-title">🛡️ Security Features</h4>
      <div class="feature-list">
        <div class="feature-item">
          <span class="feature-icon">✅</span>
          <div class="feature-text">
            <strong>End-to-End Encryption</strong>
            <p>File encrypted before upload</p>
          </div>
        </div>
        <div class="feature-item">
          <span class="feature-icon">✅</span>
          <div class="feature-text">
            <strong>Authentication Tags</strong>
            <p>Integrity verification enabled</p>
          </div>
        </div>
        <div class="feature-item">
          <span class="feature-icon">✅</span>
          <div class="feature-text">
            <strong>Secure Key Exchange</strong>
            <p>RSA-2048 key encryption</p>
          </div>
        </div>
        <div class="feature-item">
          <span class="feature-icon">✅</span>
          <div class="feature-text">
            <strong>Download Limits</strong>
            <p>${file.decrypt_count || 0}/${file.max_decrypt_count || "∞"} downloads used</p>
          </div>
        </div>
      </div>
    </div>

    <div class="report-section">
      <h4 class="section-title">📈 Risk Assessment</h4>
      <div class="risk-items">
        <div class="risk-item low">
          <span class="risk-icon">🟢</span>
          <span class="risk-text">Low: Encryption algorithm is industry standard</span>
        </div>
        <div class="risk-item low">
          <span class="risk-icon">🟢</span>
          <span class="risk-text">Low: Key management follows best practices</span>
        </div>
        ${
          file.expiry_time && new Date(file.expiry_time) < new Date()
            ? `
          <div class="risk-item high">
            <span class="risk-icon">🔴</span>
            <span class="risk-text">High: File has expired - consider deletion</span>
          </div>
        `
            : ""
        }
      </div>
    </div>
  `;

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
      '⚠️ REVOKE ACCESS - This will:\n\n• Prevent ALL recipients from downloading this file\n• Mark the file as "Revoked" (no deletions)\n• Keep the file in your system\n\nAre you sure?'
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