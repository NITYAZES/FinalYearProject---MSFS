const API_BASE_URL = './api/my_files.php';

let files = [];
let currentFileId = null;

// Format date
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

// Format file size
function formatSize(mb) {
  if (mb >= 1000) {
    return (mb / 1000).toFixed(2) + " GB";
  }
  return mb.toFixed(2) + " MB";
}

// Show feedback message
function showFeedback(message, type = "info") {
  const feedback = document.getElementById("feedback-message");
  if (!feedback) {
    console.error('Feedback element not found');
    return;
  }
  
  feedback.className = `show ${type}`;
  feedback.innerHTML = `
    ${message}
    <button class="feedback-close" onclick="this.parentElement.classList.remove('show')">×</button>
  `;
  
  // Auto-hide after 5 seconds
  setTimeout(() => {
    feedback.classList.remove('show');
  }, 5000);
}

// Fetch files from API
async function fetchFiles() {
  try {
    const response = await fetch(`${API_BASE_URL}?action=get_files`);
    const data = await response.json();

    if (data.success) {
      // Filter to only show received files
      files = data.files.filter(f => f.fileType === 'received');
      filterAndSortFiles();
    } else {
      showFeedback(data.message || 'Error fetching files', 'error');
    }
  } catch (error) {
    console.error('Error:', error);
    showFeedback('Failed to load files. Please try again.', 'error');
  }
}

// Fetch statistics
async function fetchStats() {
  try {
    const response = await fetch(`${API_BASE_URL}?action=get_stats`);
    const data = await response.json();
    
    if (data.success) {
      updateStats(data.stats);
    } else {
      console.error('Error fetching stats:', data.message);
    }
  } catch (error) {
    console.error('Error fetching stats:', error);
  }
}

// Update statistics display with null checks
function updateStats(stats) {
  const totalFilesEl = document.getElementById("total-files");
  const activeFilesEl = document.getElementById("active-files");
  const totalStorageEl = document.getElementById("total-storage");
  const totalDownloadsEl = document.getElementById("total-downloads");

  if (totalFilesEl) {
    totalFilesEl.textContent = stats.totalFiles || 0;
  }
  
  if (activeFilesEl) {
    activeFilesEl.textContent = stats.activeFiles || 0;
  }
  
  if (totalStorageEl) {
    totalStorageEl.textContent = formatSize(stats.totalStorage || 0);
  }
  
  if (totalDownloadsEl) {
    totalDownloadsEl.textContent = stats.totalDownloads || 0;
  }
}

// Render files table
function renderFiles(filesToRender = files) {
  const container = document.getElementById("files-container");
  
  if (!container) {
    console.error('Files container not found');
    return;
  }

  if (filesToRender.length === 0) {
    container.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">🔭</div>
        <h3 class="empty-title">No files found</h3>
        <p class="empty-text">You haven't received any files yet</p>
      </div>
    `;
    return;
  }

  const tableHTML = `
    <table class="files-table">
      <thead>
        <tr>
          <th>File Name</th>
          <th>Type</th>
          <th>Size</th>
          <th>Uploaded</th>
          <th>Expires</th>
          <th>Downloads</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        ${filesToRender
          .map(
            (file) => `
          <tr>
            <td>
              <div class="file-name-cell">
                <div class="file-icon-small">${getFileIcon(file.mime_type)}</div>
                <span class="file-name-text">${escapeHtml(file.file_name)}</span>
              </div>
            </td>
            <td>
              <span class="badge-type">${file.fileType === 'sent' ? '📤 Sent' : '📥 Received'}</span>
            </td>
            <td>${formatSize(file.size)}</td>
            <td>${formatDate(file.uploadDate)}</td>
            <td>${formatDate(file.expiryDate)}</td>
            <td>${file.downloads} / ${file.maxDownloads}</td>
            <td>
              <span class="status-badge ${
                file.status === "active"
                  ? "status-active"
                  : "status-expired"
              }">
                ${file.status === "active" ? "✓ Active" : "✕ Expired"}
              </span>
            </td>
            <td>
              <div class="action-buttons">
                <button class="action-btn btn-view" onclick="viewFile('${file.file_id}')">
                  👁️ View
                </button>
                ${
                  file.status === "active" && file.fileType === 'sent'
                    ? `
                  <button class="action-btn btn-extend" onclick="openExtendModal('${file.file_id}')">
                    ⏰ Extend
                  </button>
                `
                    : ""
                }
                ${
                  file.fileType === 'sent'
                    ? `
                  <button class="action-btn btn-delete" onclick="openDeleteModal('${file.file_id}', '${escapeHtml(file.file_name)}')">
                    🗑️ Delete
                  </button>
                `
                    : ""
                }
              </div>
            </td>
          </tr>
        `
          )
          .join("")}
      </tbody>
    </table>
  `;

  container.innerHTML = tableHTML;
}

// Get file icon based on MIME type
function getFileIcon(mimeType) {
  if (!mimeType) return '📄';
  
  if (mimeType.startsWith('image/')) return '🖼️';
  if (mimeType.startsWith('video/')) return '🎥';
  if (mimeType.startsWith('audio/')) return '🎵';
  if (mimeType.includes('pdf')) return '📕';
  if (mimeType.includes('word') || mimeType.includes('document')) return '📘';
  if (mimeType.includes('excel') || mimeType.includes('sheet')) return '📊';
  if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return '📺';
  if (mimeType.includes('zip') || mimeType.includes('rar') || mimeType.includes('7z')) return '📦';
  
  return '📄';
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, m => map[m]);
}

// Filter and sort files
function filterAndSortFiles() {
  const searchInput = document.getElementById("search-input");
  const statusFilter = document.getElementById("status-filter");
  const sortBy = document.getElementById("sort-by");
  
  if (!searchInput || !statusFilter || !sortBy) {
    console.error('Filter elements not found');
    return;
  }
  
  const searchTerm = searchInput.value.toLowerCase();
  const statusValue = statusFilter.value;
  const sortValue = sortBy.value;

  let filtered = [...files];

  // Apply search filter
  if (searchTerm) {
    filtered = filtered.filter((f) =>
      f.file_name.toLowerCase().includes(searchTerm)
    );
  }

  // Apply status filter
  if (statusValue) {
    filtered = filtered.filter((f) => f.status === statusValue);
  }

  // Apply sorting
  switch (sortValue) {
    case "newest":
      filtered.sort(
        (a, b) => new Date(b.uploadDate) - new Date(a.uploadDate)
      );
      break;
    case "oldest":
      filtered.sort(
        (a, b) => new Date(a.uploadDate) - new Date(b.uploadDate)
      );
      break;
    case "name-az":
      filtered.sort((a, b) => a.file_name.localeCompare(b.file_name));
      break;
    case "name-za":
      filtered.sort((a, b) => b.file_name.localeCompare(a.file_name));
      break;
    case "size-large":
      filtered.sort((a, b) => b.size - a.size);
      break;
    case "size-small":
      filtered.sort((a, b) => a.size - b.size);
      break;
  }

  renderFiles(filtered);
}

// Clear filters
function clearFilters() {
  const searchInput = document.getElementById("search-input");
  const statusFilter = document.getElementById("status-filter");
  const sortBy = document.getElementById("sort-by");
  
  if (searchInput) searchInput.value = "";
  if (statusFilter) statusFilter.value = "";
  if (sortBy) sortBy.value = "newest";
  
  filterAndSortFiles();
}

// View file details
function viewFile(fileId) {
  // Navigate to file details page
  window.location.href = `./file_details.html?id=${fileId}`;
}

// Open extend modal
function openExtendModal(fileId) {
  currentFileId = fileId;
  const file = files.find((f) => f.file_id === fileId);

  const expiryDateInput = document.getElementById("new-expiry-date");
  if (!expiryDateInput) {
    console.error('Expiry date input not found');
    return;
  }

  // Set minimum date to current date
  const now = new Date();
  const minDate = now.toISOString().slice(0, 16);
  expiryDateInput.min = minDate;

  // Set default value to current expiry + 30 days
  const currentExpiry = new Date(file.expiryDate);
  currentExpiry.setDate(currentExpiry.getDate() + 30);
  expiryDateInput.value = currentExpiry.toISOString().slice(0, 16);

  const modal = document.getElementById("extend-modal");
  if (modal) {
    modal.classList.add("show");
  }
}

// Open delete modal
function openDeleteModal(fileId, fileName) {
  currentFileId = fileId;
  
  const fileNameEl = document.getElementById("delete-file-name");
  if (fileNameEl) {
    fileNameEl.textContent = fileName;
  }
  
  const modal = document.getElementById("delete-modal");
  if (modal) {
    modal.classList.add("show");
  }
}

// Close modals
function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove("show");
  }
  currentFileId = null;
}

// Extend file expiry
async function extendExpiry() {
  const expiryDateInput = document.getElementById("new-expiry-date");
  if (!expiryDateInput) {
    showFeedback("Error: Date input not found", "error");
    return;
  }
  
  const newDate = expiryDateInput.value;

  if (!newDate) {
    showFeedback("Please select a new expiry date", "error");
    return;
  }

  try {
    const response = await fetch(`${API_BASE_URL}?action=extend_expiry`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        file_id: currentFileId,
        new_expiry: newDate
      })
    });

    const data = await response.json();

    if (data.success) {
      showFeedback("File expiry date extended successfully!", "success");
      closeModal("extend-modal");
      // Refresh files and stats
      await fetchFiles();
      await fetchStats();
    } else {
      showFeedback(data.message || "Failed to extend expiry", "error");
    }
  } catch (error) {
    console.error('Error:', error);
    showFeedback("An error occurred. Please try again.", "error");
  }
}

// Delete file
async function deleteFile() {
  try {
    const response = await fetch(`${API_BASE_URL}?action=delete_file`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        file_id: currentFileId
      })
    });

    const data = await response.json();

    if (data.success) {
      showFeedback("File deleted successfully!", "success");
      closeModal("delete-modal");
      // Refresh files and stats
      await fetchFiles();
      await fetchStats();
    } else {
      showFeedback(data.message || "Failed to delete file", "error");
    }
  } catch (error) {
    console.error('Error:', error);
    showFeedback("An error occurred. Please try again.", "error");
  }
}

// Event listeners
document.addEventListener("DOMContentLoaded", async () => {
  // Show loading state
  const container = document.getElementById("files-container");
  if (container) {
    container.innerHTML = `
      <div class="loading-state">
        <div class="loading-spinner"></div>
        <p class="loading-text">Loading your files...</p>
      </div>
    `;
  }

  // Load files and stats
  await Promise.all([fetchFiles(), fetchStats()]);

  // Search and filter events
  const searchInput = document.getElementById("search-input");
  const statusFilter = document.getElementById("status-filter");
  const sortBy = document.getElementById("sort-by");
  const clearFiltersBtn = document.getElementById("clear-filters");
  
  if (searchInput) {
    searchInput.addEventListener("input", filterAndSortFiles);
  }
  
  if (statusFilter) {
    statusFilter.addEventListener("change", filterAndSortFiles);
  }
  
  if (sortBy) {
    sortBy.addEventListener("change", filterAndSortFiles);
  }
  
  if (clearFiltersBtn) {
    clearFiltersBtn.addEventListener("click", clearFilters);
  }

  // Modal events
  const extendCancel = document.getElementById("extend-cancel");
  const extendConfirm = document.getElementById("extend-confirm");
  const deleteCancel = document.getElementById("delete-cancel");
  const deleteConfirm = document.getElementById("delete-confirm");
  
  if (extendCancel) {
    extendCancel.addEventListener("click", () => closeModal("extend-modal"));
  }
  
  if (extendConfirm) {
    extendConfirm.addEventListener("click", extendExpiry);
  }
  
  if (deleteCancel) {
    deleteCancel.addEventListener("click", () => closeModal("delete-modal"));
  }
  
  if (deleteConfirm) {
    deleteConfirm.addEventListener("click", deleteFile);
  }

  // Close modal when clicking outside
  document.querySelectorAll(".modal").forEach((modal) => {
    modal.addEventListener("click", (e) => {
      if (e.target === modal) {
        modal.classList.remove("show");
        currentFileId = null;
      }
    });
  });
});