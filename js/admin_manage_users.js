const API_BASE = "./api/admin_manage_users.php";
let allUsers = [];
let currentUser = null;
let currentAdminId = null; // ✅ NEW

document.addEventListener("DOMContentLoaded", () => {
  bindStaticEvents();
  loadUsers();

  if (document.getElementById("totalUsers")) {
    loadStatistics();
  }
});

function bindStaticEvents() {
  // Logout
  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn) logoutBtn.addEventListener("click", logout);

  // Back to dashboard
  const backBtn = document.getElementById("backBtn");
  if (backBtn)
    backBtn.addEventListener(
      "click",
      () => (window.location.href = "dashboard_admin.html"),
    );

  // Search & filters
  const userSearch = document.getElementById("userSearch");
  const statusFilter = document.getElementById("userStatusFilter");
  const roleFilter = document.getElementById("userRoleFilter");
  const twoFAFilter = document.getElementById("user2FAFilter");

  if (userSearch) userSearch.addEventListener("input", filterUsers);
  if (statusFilter) statusFilter.addEventListener("change", filterUsers);
  if (roleFilter) roleFilter.addEventListener("change", filterUsers);
  if (twoFAFilter) twoFAFilter.addEventListener("change", filterUsers);

  // Modal close button
  const closeBtn = document.getElementById("userModalCloseBtn");
  if (closeBtn)
    closeBtn.addEventListener("click", () => closeModal("userModal"));

  // Delegated actions in the table
  const tbody = document.getElementById("usersTableBody");
  if (tbody) {
    tbody.addEventListener("click", (e) => {
      const btn = e.target.closest("button[data-action]");
      if (!btn) return;

      // ✅ If button disabled, do nothing (extra safety)
      if (btn.disabled) return;

      const action = btn.getAttribute("data-action");
      const userId = Number(btn.getAttribute("data-user-id"));
      const username = btn.getAttribute("data-username") || "";

      if (!Number.isFinite(userId)) return;

      // ✅ Block self dangerous actions at UI level
      if (currentAdminId !== null && userId === currentAdminId) {
        if (
          action === "suspend" ||
          action === "activate" ||
          action === "delete"
        ) {
          showError("You cannot perform this action on your own account.");
          return;
        }
      }

      if (action === "view") void viewUserDetails(userId);
      else if (action === "edit") editUser(userId);
      else if (action === "suspend") confirmSuspendUser(userId, username);
      else if (action === "activate") void activateUser(userId, username);
      else if (action === "delete") confirmDeleteUser(userId, username);
    });
  }

  // Click outside modal to close
  document.addEventListener("click", (e) => {
    const modal = e.target;
    if (modal && modal.classList && modal.classList.contains("modal")) {
      modal.classList.remove("active");
      document.body.style.overflow = "auto";
    }
  });
}

async function loadUsers() {
  try {
    const response = await fetch(`${API_BASE}?action=list`);
    const text = await response.text(); // read raw first

    let data;
    try {
      data = JSON.parse(text);
    } catch {
      console.error("Server returned non-JSON:", text);
      showError(
        "Server error: API returned non-JSON (check PHP error output).",
      );
      return;
    }

    if (data.success) {
      allUsers = Array.isArray(data.users) ? data.users : [];
      currentAdminId = Number.isFinite(Number(data.current_admin_id))
        ? Number(data.current_admin_id)
        : null;

      displayUsers(allUsers);
      if (document.getElementById("totalUsers")) updateStatistics();
    } else {
      showError("Failed to load users: " + data.message);
    }
  } catch (error) {
    console.error("Error loading users:", error);
    showError("Failed to load users. Please try again.");
  }
}

function displayUsers(users) {
  const tbody = document.getElementById("usersTableBody");
  if (!tbody) return;

  if (!users || users.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="11" class="no-data">No users found</td></tr>';
    return;
  }

  tbody.innerHTML = "";

  users.forEach((user) => {
    const row = document.createElement("tr");
    const safeUsername = escapeHtml(user.username);

    const isSelf = currentAdminId !== null && user.user_id === currentAdminId; // ✅ NEW

    const roleBadge = `<span class="badge badge-${user.role === "admin" ? "admin" : "user"}">${String(user.role).toUpperCase()}</span>`;
    const youBadge = isSelf ? ` <span class="badge badge-info">YOU</span>` : ""; // ✅ NEW (optional styling)

    const suspendOrActivateBtn =
      user.status === "suspended"
        ? `<button class="btn-activate" type="button" data-action="activate" data-user-id="${user.user_id}" data-username="${safeUsername}" ${
            isSelf
              ? 'disabled title="You cannot activate your own account"'
              : ""
          }>Activate</button>`
        : `<button class="btn-suspend" type="button" data-action="suspend" data-user-id="${user.user_id}" data-username="${safeUsername}" ${
            isSelf ? 'disabled title="You cannot suspend your own account"' : ""
          }>Suspend</button>`;

    const deleteBtn = `<button class="btn-delete" type="button" data-action="delete" data-user-id="${user.user_id}" data-username="${safeUsername}" ${
      isSelf ? 'disabled title="You cannot delete your own account"' : ""
    }>Delete</button>`;

    row.innerHTML = `
      <td>${user.user_id}</td>
      <td>${escapeHtml(user.user_fullname)}</td>
      <td>${safeUsername}${youBadge}</td>
      <td>${escapeHtml(user.user_email)}</td>
      <td>${escapeHtml(user.user_phone)}</td>
      <td>${roleBadge}</td>
      <td><span class="badge badge-${getStatusClass(user.status)}">${String(user.status).toUpperCase()}</span></td>
      <td><span class="badge badge-${user.totp_enabled ? "success" : "danger"}">${user.totp_enabled ? "ENABLED" : "DISABLED"}</span></td>
      <td><span class="badge badge-${user.email_verified ? "success" : "warning"}">${user.email_verified ? "VERIFIED" : "NOT VERIFIED"}</span></td>
      <td>${formatDateTime(user.created_at)}</td>
      <td>
        <div class="actions-cell">
          <button class="btn-view" type="button" data-action="view" data-user-id="${user.user_id}">View</button>
          <button class="btn-edit" type="button" data-action="edit" data-user-id="${user.user_id}">Edit</button>
          ${suspendOrActivateBtn}
          ${deleteBtn}
        </div>
      </td>
    `;

    tbody.appendChild(row);
  });
}

function filterUsers() {
  const searchTerm = (
    document.getElementById("userSearch")?.value || ""
  ).toLowerCase();
  const statusFilter = document.getElementById("userStatusFilter")?.value || "";
  const roleFilter = document.getElementById("userRoleFilter")?.value || "";
  const twoFAFilter = document.getElementById("user2FAFilter")?.value || "";

  const filtered = allUsers.filter((user) => {
    const matchesSearch =
      String(user.user_fullname || "")
        .toLowerCase()
        .includes(searchTerm) ||
      String(user.username || "")
        .toLowerCase()
        .includes(searchTerm) ||
      String(user.user_email || "")
        .toLowerCase()
        .includes(searchTerm) ||
      String(user.user_phone || "")
        .toLowerCase()
        .includes(searchTerm);

    const matchesStatus = !statusFilter || user.status === statusFilter;
    const matchesRole = !roleFilter || user.role === roleFilter;
    const matches2FA =
      !twoFAFilter ||
      (twoFAFilter === "enabled" && !!user.totp_enabled) ||
      (twoFAFilter === "disabled" && !user.totp_enabled);

    return matchesSearch && matchesStatus && matchesRole && matches2FA;
  });

  displayUsers(filtered);
}

async function viewUserDetails(userId) {
  try {
    const response = await fetch(
      `${API_BASE}?action=details&user_id=${userId}`,
    );
    const data = await response.json();

    if (data.success) {
      const user = data.user;
      currentUser = user;

      const modalBody = document.getElementById("userModalBody");
      modalBody.innerHTML = `
        <div class="user-details-header">
          <div class="user-details-avatar">👤</div>
          <h2 class="user-details-name">${escapeHtml(user.user_fullname)}</h2>
          <p class="user-details-username">@${escapeHtml(user.username)} ${
            currentAdminId !== null && user.user_id === currentAdminId
              ? `<span class="badge badge-info">YOU</span>`
              : ""
          }</p>
          <div class="user-details-badges">
            <span class="badge badge-${user.role === "admin" ? "admin" : "user"}">${String(user.role).toUpperCase()}</span>
            <span class="badge badge-${getStatusClass(user.status)}">${String(user.status).toUpperCase()}</span>
          </div>
        </div>

        <div class="info-section">
          <div class="info-section-header">
            <span class="info-section-icon">📋</span>
            <h3 class="info-section-title">Contact Information</h3>
          </div>
          <div class="info-grid">
            <div class="info-item">
              <div class="info-label">User ID</div>
              <div class="info-value">#${user.user_id}</div>
            </div>
            <div class="info-item">
              <div class="info-label">Email Address</div>
              <div class="info-value">${escapeHtml(user.user_email)}</div>
            </div>
            <div class="info-item">
              <div class="info-label">Phone Number</div>
              <div class="info-value">${escapeHtml(user.user_phone)}</div>
            </div>
            <div class="info-item">
              <div class="info-label">Account Created</div>
              <div class="info-value">${formatDateTime(user.created_at)}</div>
            </div>
          </div>
        </div>

        <div class="info-section">
          <div class="info-section-header">
            <span class="info-section-icon">🔐</span>
            <h3 class="info-section-title">Security & Verification</h3>
          </div>
          <div class="info-grid">
            <div class="info-item">
              <div class="info-label">Two-Factor Authentication</div>
              <div class="info-value">
                <span class="badge badge-${user.totp_enabled ? "success" : "danger"}">
                  ${user.totp_enabled ? "✓ ENABLED" : "✗ DISABLED"}
                </span>
              </div>
            </div>
            <div class="info-item">
              <div class="info-label">Email Verification</div>
              <div class="info-value">
                <span class="badge badge-${user.email_verified ? "success" : "warning"}">
                  ${user.email_verified ? "✓ VERIFIED" : "⚠ NOT VERIFIED"}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="info-section">
          <div class="info-section-header">
            <span class="info-section-icon">📊</span>
            <h3 class="info-section-title">Activity Statistics</h3>
          </div>
          <div class="stats-cards">
            <div class="stat-card">
              <div class="stat-icon">📤</div>
              <p class="stat-value">${user.file_stats.files_sent}</p>
              <p class="stat-label">Files Sent</p>
            </div>
            <div class="stat-card">
              <div class="stat-icon">📥</div>
              <p class="stat-value">${user.file_stats.files_received}</p>
              <p class="stat-label">Files Received</p>
            </div>
            <div class="stat-card">
              <div class="stat-icon">💾</div>
              <p class="stat-value">${formatFileSize(user.file_stats.storage_used)}</p>
              <p class="stat-label">Storage Used</p>
            </div>
          </div>
        </div>
      `;

      document.getElementById("modalTitle").textContent = "User Details";
      openModal("userModal");
    } else {
      showError("Failed to load user details: " + data.message);
    }
  } catch (error) {
    console.error("Error loading user details:", error);
    showError("Failed to load user details. Please try again.");
  }
}

function editUser(userId) {
  const user = allUsers.find((u) => u.user_id === userId);
  if (!user) {
    showError("User not found");
    return;
  }

  currentUser = user;
  const isSelf = currentAdminId !== null && user.user_id === currentAdminId; // ✅ NEW

  const modalBody = document.getElementById("userModalBody");
  modalBody.innerHTML = `
    <form id="editUserForm">
      <input type="hidden" id="editUserId" value="${user.user_id}">
      <input type="hidden" id="originalEmail" value="${escapeHtml(user.user_email)}">

      <div class="detail-section">
        <h4><span class="detail-section-icon">📝</span>Personal Information</h4>

        <div class="form-row">
          <div class="form-group">
            <label for="editFullName">Full Name *</label>
            <input type="text" class="form-control" id="editFullName" value="${escapeHtml(user.user_fullname)}" required placeholder="Enter full name">
          </div>

          <div class="form-group">
            <label for="editEmail">Email Address *</label>
            <input type="email" class="form-control" id="editEmail" value="${escapeHtml(user.user_email)}" required placeholder="user@example.com">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="editPhone">Phone Number *</label>
            <input type="tel" class="form-control" id="editPhone" value="${escapeHtml(user.user_phone)}" required placeholder="+60123456789">
          </div>

          <div class="form-group">
            <label for="editUsername">Username</label>
            <input type="text" class="form-control" id="editUsername" value="${escapeHtml(user.username)}" disabled>
            <small style="color:#6b7280;display:block;margin-top:4px;">Username cannot be changed</small>
          </div>
        </div>

        <div id="emailChangeWarning" style="display:none;">
          <div class="warning-box" style="margin-top:1rem;">
            <span class="warning-box-icon">⚠️</span>
            <div class="warning-box-content">
              <strong>Email Change Detected</strong>
              <p>The user will be notified about any changes made to their account.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="detail-section">
        <h4><span class="detail-section-icon">⚙️</span>Account Settings</h4>

        <div class="form-row">
          <div class="form-group">
            <label for="editStatus">Account Status *</label>
            <select class="form-control" id="editStatus" required ${isSelf ? 'disabled title="You cannot change your own account status"' : ""}>
              <option value="active" ${user.status === "active" ? "selected" : ""}>✓ Active - Full access</option>
              <option value="inactive" ${user.status === "inactive" ? "selected" : ""}>○ Inactive - Dormant account</option>
              <option value="suspended" ${user.status === "suspended" ? "selected" : ""}>✕ Suspended - Access blocked</option>
            </select>
            ${isSelf ? `<small style="color:#6b7280;display:block;margin-top:4px;">You cannot change your own status.</small>` : ""}
          </div>

          <div class="form-group">
            <label for="displayRole">User Role</label>
            <input type="text" class="form-control" id="displayRole" value="${user.role === "admin" ? "👑 Admin" : "👤 User"}" disabled>
            <small style="color:#6b7280;display:block;margin-top:4px;">User role cannot be changed through this form</small>
          </div>
        </div>

        <div class="info-box">
          <span class="info-box-icon">ℹ️</span>
          <div class="info-box-content">
            <strong>Important Notes</strong>
            <br>
            • The user will be notified about any changes made to their account<br>
            • Suspending an account will immediately revoke all access<br>
            • Changes are logged in the security audit log</p>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn-secondary" id="cancelEditBtn">Cancel</button>
        <button type="submit" class="btn btn-primary">💾 Save Changes</button>
      </div>
    </form>
  `;

  const form = document.getElementById("editUserForm");
  if (form) form.addEventListener("submit", saveUser);

  const cancelBtn = document.getElementById("cancelEditBtn");
  if (cancelBtn)
    cancelBtn.addEventListener("click", () => closeModal("userModal"));

  const editEmail = document.getElementById("editEmail");
  if (editEmail) {
    editEmail.addEventListener("input", function () {
      const originalEmail = document.getElementById("originalEmail").value;
      const newEmail = this.value;
      const warning = document.getElementById("emailChangeWarning");

      if (warning) {
        warning.style.display =
          newEmail !== originalEmail && newEmail.includes("@")
            ? "block"
            : "none";
      }
    });
  }

  document.getElementById("modalTitle").textContent = "Edit User";
  openModal("userModal");
}

async function saveUser(event) {
  event.preventDefault();

  const userId = Number(document.getElementById("editUserId").value);

  // ✅ If editing self, status is disabled, so don't send status at all
  const isSelf = currentAdminId !== null && userId === currentAdminId;

  const originalEmail = document.getElementById("originalEmail").value;
  const newEmail = document.getElementById("editEmail").value;

  const userData = {
    user_id: userId,
    user_fullname: document.getElementById("editFullName").value,
    user_email: newEmail,
    user_phone: document.getElementById("editPhone").value,
  };

  if (!isSelf) {
    userData.status = document.getElementById("editStatus").value;
  }

  const emailChanged = originalEmail !== newEmail;

  if (emailChanged) {
    const confirmChange = confirm(
      `⚠️ EMAIL CHANGE (REQUIRES VERIFICATION)\n\n` +
        `You are changing the email from:\n` +
        `${originalEmail}\n\n` +
        `To:\n` +
        `${newEmail}\n\n` +
        `IMPORTANT:\n` +
        `• The email will NOT be updated immediately.\n` +
        `• The user must confirm the new email using the verification link sent to ${newEmail}.\n` +
        `• Until confirmed, login will be blocked.\n`,
    );

    if (!confirmChange) return;

    userData.email_changed = true;
    userData.send_verification = true;
  }

  try {
    const response = await fetch(`${API_BASE}?action=update`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(userData),
    });

    const data = await response.json();

    if (data.success) {
      if (emailChanged) {
        alert(
          "✅ User updated. Email change is pending confirmation.\n\n" +
            `📧 A verification link was sent to: ${newEmail}\n` +
            "The email will only update after the user confirms it.",
        );
      } else {
        alert(
          "✅ User updated successfully!\n\n📧 The user will be notified about the changes made to their account.",
        );
      }
      closeModal("userModal");
      loadUsers();
    } else {
      showError("Failed to update user: " + data.message);
    }
  } catch (error) {
    console.error("Error updating user:", error);
    showError("Failed to update user. Please try again.");
  }
}

function confirmDeleteUser(userId, username) {
  if (
    confirm(
      `Are you sure you want to delete user "${username}"?\n\nThis action cannot be undone and all user data will be permanently removed.`,
    )
  ) {
    void deleteUser(userId);
  }
}

function confirmSuspendUser(userId, username) {
  const reason = prompt(
    `You are about to suspend user "${username}".\n\nPlease enter a reason for suspension (optional):`,
  );
  if (reason !== null) {
    void suspendUser(userId, reason || "No reason provided");
  }
}

async function suspendUser(userId, reason) {
  // ✅ UI-level safety
  if (currentAdminId !== null && userId === currentAdminId) {
    showError("You cannot suspend your own account.");
    return;
  }

  try {
    const response = await fetch(`${API_BASE}?action=update`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ user_id: userId, status: "suspended" }),
    });

    const data = await response.json();

    if (data.success) {
      alert(`✅ User suspended successfully.\nReason: ${reason}`);
      loadUsers();
      if (document.getElementById("totalUsers")) loadStatistics();
    } else {
      showError("Failed to suspend user: " + data.message);
    }
  } catch (error) {
    console.error("Error suspending user:", error);
    showError("Failed to suspend user. Please try again.");
  }
}

async function activateUser(userId, username) {
  // ✅ UI-level safety
  if (currentAdminId !== null && userId === currentAdminId) {
    showError("You cannot activate your own account.");
    return;
  }

  if (
    !confirm(
      `Activate user "${username}"?\n\nThis will restore their account access.`,
    )
  )
    return;

  try {
    const response = await fetch(`${API_BASE}?action=update`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ user_id: userId, status: "active" }),
    });

    const data = await response.json();

    if (data.success) {
      alert("✅ User activated successfully");
      loadUsers();
      if (document.getElementById("totalUsers")) loadStatistics();
    } else {
      showError("Failed to activate user: " + data.message);
    }
  } catch (error) {
    console.error("Error activating user:", error);
    showError("Failed to activate user. Please try again.");
  }
}

async function deleteUser(userId) {
  // ✅ UI-level safety
  if (currentAdminId !== null && userId === currentAdminId) {
    showError("You cannot delete your own account.");
    return;
  }

  try {
    const response = await fetch(
      `${API_BASE}?action=delete&user_id=${userId}`,
      { method: "DELETE" },
    );
    const data = await response.json();

    if (data.success) {
      alert("✅ User deleted successfully");
      loadUsers();
      if (document.getElementById("totalUsers")) loadStatistics();
    } else {
      showError("Failed to delete user: " + data.message);
    }
  } catch (error) {
    console.error("Error deleting user:", error);
    showError("Failed to delete user. Please try again.");
  }
}

async function loadStatistics() {
  try {
    const response = await fetch(`${API_BASE}?action=stats`);
    const data = await response.json();

    if (data.success) {
      updateStatisticsDisplay(data.statistics);
    }
  } catch (error) {
    console.error("Error loading statistics:", error);
  }
}

function updateStatistics() {
  const totalUsers = allUsers.length;
  const activeUsers = allUsers.filter((u) => u.status === "active").length;
  const suspendedUsers = allUsers.filter(
    (u) => u.status === "suspended",
  ).length;
  const twoFAUsers = allUsers.filter((u) => u.totp_enabled).length;

  const totalEl = document.getElementById("totalUsers");
  const activeEl = document.getElementById("activeUsers");
  const suspendedEl = document.getElementById("suspendedUsers");
  const twoFAEl = document.getElementById("twoFAUsers");

  if (totalEl) totalEl.textContent = totalUsers;
  if (activeEl) activeEl.textContent = activeUsers;
  if (suspendedEl) suspendedEl.textContent = suspendedUsers;
  if (twoFAEl) twoFAEl.textContent = twoFAUsers;
}

function updateStatisticsDisplay(stats) {
  const totalEl = document.getElementById("totalUsers");
  const activeEl = document.getElementById("activeUsers");
  const suspendedEl = document.getElementById("suspendedUsers");
  const twoFAEl = document.getElementById("twoFAUsers");

  if (totalEl) totalEl.textContent = stats.total_users;
  if (activeEl) activeEl.textContent = stats.active_users;
  if (suspendedEl) suspendedEl.textContent = stats.suspended_users;
  if (twoFAEl) twoFAEl.textContent = stats.users_with_2fa;
}

function openModal(modalId) {
  document.getElementById(modalId).classList.add("active");
  document.body.style.overflow = "hidden";
}

function closeModal(modalId) {
  document.getElementById(modalId).classList.remove("active");
  document.body.style.overflow = "auto";
}

function getStatusClass(status) {
  const classes = {
    active: "success",
    inactive: "warning",
    suspended: "danger",
  };
  return classes[status] || "info";
}

function formatDateTime(dateString) {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return date.toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: true,
  });
}

function formatFileSize(bytes) {
  if (!bytes || bytes === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
}

function escapeHtml(text) {
  if (text === null || text === undefined) return "";
  return String(text).replace(
    /[&<>"']/g,
    (m) =>
      ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
      })[m],
  );
}

function showError(message) {
  alert("❌ " + message);
}

function logout() {
  if (confirm("Are you sure you want to logout?")) {
    window.location.href = "index.html";
  }
}
