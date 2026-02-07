const API_BASE = "./api/admin_manage_files.php";

// Global variables
let allFiles = [];

document.addEventListener("DOMContentLoaded", () => {
  bindStaticEvents();

  loadFiles();
  loadStatistics();              // ✅ now null-safe
  loadEncryptionDistribution();  // ✅ now null-safe
});

function bindStaticEvents() {
  // ✅ CSP-safe: logout + back
  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn) logoutBtn.addEventListener("click", logout);

  const backBtn = document.getElementById("backBtn");
  if (backBtn) backBtn.addEventListener("click", () => (window.location.href = "dashboard_admin.html"));

  // ✅ CSP-safe: search + filters
  const fileSearch = document.getElementById("fileSearch");
  const statusFilter = document.getElementById("fileStatusFilter");
  const typeFilter = document.getElementById("fileTypeFilter");
  const encFilter = document.getElementById("fileEncryptionFilter");

  if (fileSearch) fileSearch.addEventListener("input", filterFiles);
  if (statusFilter) statusFilter.addEventListener("change", filterFiles);
  if (typeFilter) typeFilter.addEventListener("change", filterFiles);
  if (encFilter) encFilter.addEventListener("change", filterFiles);

  // ✅ CSP-safe: modal close button
  const modalCloseBtn = document.getElementById("fileModalCloseBtn");
  if (modalCloseBtn) modalCloseBtn.addEventListener("click", () => closeModal("fileModal"));

  // ✅ Delegated table actions (View/Download/Delete)
  const tbody = document.getElementById("filesTableBody");
  if (tbody) {
    tbody.addEventListener("click", (e) => {
      const btn = e.target.closest("button[data-action]");
      if (!btn) return;

      const action = btn.getAttribute("data-action");
      const fileId = btn.getAttribute("data-file-id");
      const fileName = btn.getAttribute("data-file-name") || "";

      if (!fileId) return;

      if (action === "view") void viewFileDetails(fileId);
      if (action === "download") downloadFile(fileId);
      if (action === "delete") confirmDeleteFile(fileId, fileName);
    });
  }

  // ✅ Close modal when clicking outside
  document.addEventListener("click", (e) => {
    const modal = e.target;
    if (modal && modal.classList && modal.classList.contains("modal")) {
      modal.classList.remove("active");
    }
  });
}

/**
 * Load all files from the server
 */
async function loadFiles() {
  try {
    const response = await fetch(`${API_BASE}?action=list`);
    const data = await response.json();

    if (data.success) {
      allFiles = Array.isArray(data.files) ? data.files : [];
      displayFiles(allFiles);
    } else {
      showError("Failed to load files: " + data.message);
    }
  } catch (error) {
    console.error("Error loading files:", error);
    showError("Failed to load files. Please try again.");
  }
}

/**
 * Display files in the table
 */
function displayFiles(files) {
  const tbody = document.getElementById("filesTableBody");
  if (!tbody) return;

  if (!files || files.length === 0) {
    tbody.innerHTML = '<tr><td colspan="11" class="no-data">No files found</td></tr>';
    return;
  }

  tbody.innerHTML = "";

  files.forEach((file) => {
    const row = document.createElement("tr");

    const statusBadge = file.is_expired
      ? '<span class="badge badge-danger">Expired</span>'
      : `<span class="badge badge-${getStatusClass(file.status)}">${escapeHtml(file.status)}</span>`;

    const safeFileName = escapeHtml(file.file_name);

    // ✅ Removed separate "Encryption" button
    row.innerHTML = `
      <td>
        <div class="file-name-cell">
          <strong>${safeFileName}</strong>
          <small>${escapeHtml(String(file.file_id).substring(0, 8))}...</small>
        </div>
      </td>
      <td><span class="badge badge-info">${getFileTypeIcon(file.file_type_category)} ${escapeHtml(file.file_type_category)}</span></td>
      <td>${formatFileSize(file.file_size)}</td>
      <td>
        <div class="user-cell">
          <strong>${escapeHtml(file.sender_name)}</strong>
          <small>${escapeHtml(file.sender_email)}</small>
        </div>
      </td>
      <td>
        <div class="user-cell">
          <strong>${escapeHtml(file.receiver_name)}</strong>
          <small>${escapeHtml(file.receiver_email)}</small>
        </div>
      </td>
      <td>
        <div class="encryption-cell">
          <div>${escapeHtml(file.encryption_score)}</div>
          <small>${escapeHtml(file.encryption_rating)}</small>
        </div>
      </td>
      <td>${statusBadge}</td>
      <td>${formatDateTime(file.uploaded_at)}</td>
      <td>${formatDateTime(file.expiry_time)}</td>
      <td>
        <span class="badge ${file.decrypt_count >= file.max_decrypt_count ? "badge-danger" : "badge-info"}">
          ${escapeHtml(file.decrypt_count)}/${escapeHtml(file.max_decrypt_count)}
        </span>
      </td>
      <td>
        <div class="actions-cell">
          <button class="btn-view" type="button" data-action="view" data-file-id="${escapeHtml(file.file_id)}">View</button>
          <button class="btn-download" type="button" data-action="download" data-file-id="${escapeHtml(file.file_id)}">Download</button>
          <button class="btn-delete" type="button" data-action="delete" data-file-id="${escapeHtml(file.file_id)}" data-file-name="${safeFileName}">Delete</button>
        </div>
      </td>
    `;

    tbody.appendChild(row);
  });
}

/**
 * Filter files based on search and filter inputs
 */
function filterFiles() {
  const searchTerm = (document.getElementById("fileSearch")?.value || "").toLowerCase();
  const statusFilter = document.getElementById("fileStatusFilter")?.value || "";
  const typeFilter = document.getElementById("fileTypeFilter")?.value || "";
  const encryptionFilter = document.getElementById("fileEncryptionFilter")?.value || "";

  const filtered = allFiles.filter((file) => {
    const matchesSearch =
      String(file.file_name || "").toLowerCase().includes(searchTerm) ||
      String(file.sender_name || "").toLowerCase().includes(searchTerm) ||
      String(file.sender_email || "").toLowerCase().includes(searchTerm) ||
      String(file.receiver_name || "").toLowerCase().includes(searchTerm) ||
      String(file.receiver_email || "").toLowerCase().includes(searchTerm);

    let matchesStatus = true;
    if (statusFilter) {
      if (statusFilter === "expired") matchesStatus = !!file.is_expired;
      else matchesStatus = file.status === statusFilter && !file.is_expired;
    }

    const matchesType = !typeFilter || file.file_type_category === typeFilter;

    let matchesEncryption = true;
    if (encryptionFilter) {
      const score = Number(file.encryption_score || 0);
      if (encryptionFilter === "excellent") matchesEncryption = score >= 95;
      if (encryptionFilter === "good") matchesEncryption = score >= 80 && score < 95;
      if (encryptionFilter === "fair") matchesEncryption = score >= 60 && score < 80;
      if (encryptionFilter === "poor") matchesEncryption = score < 60;
    }

    return matchesSearch && matchesStatus && matchesType && matchesEncryption;
  });

  displayFiles(filtered);
}

/**
 * View detailed file information (✅ includes encryption details)
 */
async function viewFileDetails(fileId) {
  try {
    // Fetch details + encryption metrics (if endpoint exists)
    const [detailsRes, encRes] = await Promise.allSettled([
      fetch(`${API_BASE}?action=details&file_id=${encodeURIComponent(fileId)}`).then((r) => r.json()),
      fetch(`${API_BASE}?action=encryption&file_id=${encodeURIComponent(fileId)}`).then((r) => r.json()),
    ]);

    const detailsData = detailsRes.status === "fulfilled" ? detailsRes.value : null;
    if (!detailsData || !detailsData.success) {
      showError("Failed to load file details.");
      return;
    }

    const file = detailsData.file;

    // encryption metrics might fail; fallback to file fields
    const encData = encRes.status === "fulfilled" ? encRes.value : null;
    const metrics = encData && encData.success ? encData.metrics : null;

    const score = metrics?.encryption_score ?? file.encryption_score ?? "N/A";
    const rating = metrics?.encryption_rating ?? file.encryption_rating ?? "N/A";
    const algorithm = metrics?.encryption_algorithm ?? "N/A";
    const keyExchange = metrics?.key_exchange_algorithm ?? "N/A";

    const modalBody = document.getElementById("fileModalBody");
    if (!modalBody) return;

    modalBody.innerHTML = `
      <div class="detail-section">
        <h4><span class="detail-section-icon">📄</span> File Information</h4>
        <div class="detail-grid">
          <div class="detail-item">
            <div class="detail-item-label">File ID</div>
            <div class="detail-item-value" style="font-family: monospace; font-size: 0.8125rem; color: var(--text-secondary);">${escapeHtml(file.file_id)}</div>
          </div>
          <div class="detail-item">
            <div class="detail-item-label">File Name</div>
            <div class="detail-item-value">${escapeHtml(file.file_name)}</div>
          </div>
          <div class="detail-item">
            <div class="detail-item-label">File Size</div>
            <div class="detail-item-value">${formatFileSize(file.file_size)}</div>
          </div>
          <div class="detail-item">
            <div class="detail-item-label">MIME Type</div>
            <div class="detail-item-value">${escapeHtml(file.mime_type)}</div>
          </div>
          <div class="detail-item">
            <div class="detail-item-label">Status</div>
            <div class="detail-item-value">
              <span class="badge badge-${getStatusClass(file.status)}">${escapeHtml(file.status)}</span>
            </div>
          </div>
          <div class="detail-item">
            <div class="detail-item-label">Uploaded</div>
            <div class="detail-item-value">${formatDateTime(file.uploaded_at)}</div>
          </div>
          <div class="detail-item">
            <div class="detail-item-label">Expires</div>
            <div class="detail-item-value">${formatDateTime(file.expiry_time)}</div>
          </div>
          <div class="detail-item">
            <div class="detail-item-label">Downloads</div>
            <div class="detail-item-value">
              <span class="badge ${file.decrypt_count >= file.max_decrypt_count ? "badge-danger" : "badge-primary"}">
                ${escapeHtml(file.decrypt_count)} / ${escapeHtml(file.max_decrypt_count)}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="detail-section">
        <h4><span class="detail-section-icon">🔐</span> Encryption Details</h4>
        <div class="detail-grid">
          <div class="detail-item">
            <div class="detail-item-label">Encryption Score</div>
            <div class="detail-item-value">${escapeHtml(score)} / 100</div>
          </div>
          <div class="detail-item">
            <div class="detail-item-label">Rating</div>
            <div class="detail-item-value">${escapeHtml(rating)}</div>
          </div>
          <div class="detail-item">
            <div class="detail-item-label">Algorithm</div>
            <div class="detail-item-value">${escapeHtml(algorithm)}</div>
          </div>
          <div class="detail-item">
            <div class="detail-item-label">Key Exchange</div>
            <div class="detail-item-value">${escapeHtml(keyExchange)}</div>
          </div>
        </div>
      </div>

      <div class="detail-section">
        <h4><span class="detail-section-icon">👤</span> Sender Information</h4>
        <div class="detail-grid">
          <div class="detail-item">
            <div class="detail-item-label">Full Name</div>
            <div class="detail-item-value">${escapeHtml(file.sender_name)}</div>
          </div>
          <div class="detail-item">
            <div class="detail-item-label">Email</div>
            <div class="detail-item-value">${escapeHtml(file.sender_email)}</div>
          </div>
          <div class="detail-item">
            <div class="detail-item-label">Username</div>
            <div class="detail-item-value">${escapeHtml(file.sender_username)}</div>
          </div>
        </div>
      </div>

      <div class="detail-section">
        <h4><span class="detail-section-icon">👥</span> Receiver Information</h4>
        <div class="detail-grid">
          <div class="detail-item">
            <div class="detail-item-label">Full Name</div>
            <div class="detail-item-value">${escapeHtml(file.receiver_name)}</div>
          </div>
          <div class="detail-item">
            <div class="detail-item-label">Email</div>
            <div class="detail-item-value">${escapeHtml(file.receiver_email)}</div>
          </div>
          <div class="detail-item">
            <div class="detail-item-label">Username</div>
            <div class="detail-item-value">${escapeHtml(file.receiver_username)}</div>
          </div>
        </div>
      </div>
    `;

    openModal("fileModal");
  } catch (error) {
    console.error("Error loading file details:", error);
    showError("Failed to load file details. Please try again.");
  }
}

/**
 * Download encrypted file
 */
function downloadFile(fileId) {
  const downloadUrl = `${API_BASE}?action=download&file_id=${encodeURIComponent(fileId)}`;
  window.open(downloadUrl, "_blank");
  showSuccess("Download started. Note: File is encrypted and can only be decrypted by the intended recipient.");
}

/**
 * Confirm file deletion
 */
function confirmDeleteFile(fileId, fileName) {
  const name = fileName || "this file";
  if (confirm(`Are you sure you want to delete "${name}"?\n\nThis action cannot be undone.`)) {
    void deleteFile(fileId);
  }
}

/**
 * Delete file
 */
async function deleteFile(fileId) {
  try {
    const response = await fetch(`${API_BASE}?action=delete&file_id=${encodeURIComponent(fileId)}`, {
      method: "DELETE",
    });

    const data = await response.json();

    if (data.success) {
      showSuccess("File deleted successfully");
      loadFiles();
      loadStatistics();
      loadEncryptionDistribution();
    } else {
      showError("Failed to delete file: " + data.message);
    }
  } catch (error) {
    console.error("Error deleting file:", error);
    showError("Failed to delete file. Please try again.");
  }
}

/**
 * Load file statistics (✅ null-safe)
 */
async function loadStatistics() {
  try {
    // If these cards/ids are not in HTML, skip (prevents null.textContent error)
    const totalEl = document.getElementById("totalFiles");
    const activeEl = document.getElementById("activeFiles");
    const storageEl = document.getElementById("totalStorage");
    const avgEncEl = document.getElementById("avgEncryption");

    if (!totalEl && !activeEl && !storageEl && !avgEncEl) return;

    const response = await fetch(`${API_BASE}?action=stats`);
    const data = await response.json();

    if (data.success) {
      const stats = data.statistics;

      if (totalEl) totalEl.textContent = stats.total_files;
      if (activeEl) activeEl.textContent = stats.active_files;
      if (storageEl) storageEl.textContent = formatFileSize(stats.total_storage);
      if (avgEncEl) avgEncEl.textContent = Number(stats.avg_encryption).toFixed(2);
    }
  } catch (error) {
    console.error("Error loading statistics:", error);
  }
}

/**
 * Load encryption distribution (✅ null-safe)
 */
async function loadEncryptionDistribution() {
  try {
    const tbody = document.getElementById("encryptionDistributionBody");
    if (!tbody) return; // prevents null.innerHTML error

    const response = await fetch(`${API_BASE}?action=distribution`);
    const data = await response.json();

    if (data.success) {
      if (!data.distribution || data.distribution.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="no-data">No data available</td></tr>';
        return;
      }

      tbody.innerHTML = data.distribution
        .map(
          (item) => `
          <tr>
            <td><strong>${escapeHtml(item.score_range)}</strong></td>
            <td><span class="badge badge-${escapeHtml(item.rating_category)}">${capitalizeFirst(String(item.rating_category || ""))}</span></td>
            <td><strong>${escapeHtml(item.count)}</strong></td>
            <td><strong>${escapeHtml(item.percentage)}%</strong></td>
          </tr>
        `
        )
        .join("");
    }
  } catch (error) {
    console.error("Error loading distribution:", error);
  }
}

/* Utility functions */
function openModal(modalId) {
  const el = document.getElementById(modalId);
  if (el) el.classList.add("active");
}

function closeModal(modalId) {
  const el = document.getElementById(modalId);
  if (el) el.classList.remove("active");
}

function getStatusClass(status) {
  const classes = { active: "success", expired: "danger", deleted: "secondary" };
  return classes[status] || "info";
}

function getFileTypeIcon(type) {
  const icons = { image: "🖼️", audio: "🎵", video: "🎬", application: "📄", text: "📝" };
  return icons[type] || "📎";
}

function formatDateTime(dateString) {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return date.toLocaleString("en-US", {
    month: "numeric",
    day: "numeric",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: true,
  });
}

function formatFileSize(bytes) {
  const n = Number(bytes || 0);
  if (!n) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(n) / Math.log(k));
  return Math.round((n / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
}

function capitalizeFirst(str) {
  const s = String(str || "");
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : "";
}

function escapeHtml(text) {
  if (text === null || text === undefined) return "";
  return String(text).replace(/[&<>"']/g, (m) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  })[m]);
}

function showSuccess(message) {
  alert(message);
}

function showError(message) {
  alert(message);
}

function logout() {
  if (confirm("Are you sure you want to logout?")) {
    window.location.href = "indexf.html";
  }
}
