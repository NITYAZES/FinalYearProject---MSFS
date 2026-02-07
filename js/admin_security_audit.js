// admin_security_audit.js (CSP-safe: no inline handlers)

let allLogs = [];
let filteredLogs = [];

// Load audit data on page load
document.addEventListener("DOMContentLoaded", () => {
  bindStaticEvents();
  checkSessionAndLoad();
});

function bindStaticEvents() {
  // Logout
  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn) logoutBtn.addEventListener("click", logout);

  // Back to dashboard
  const backBtn = document.getElementById("backBtn");
  if (backBtn) backBtn.addEventListener("click", () => (window.location.href = "dashboard_admin.html"));

  // Export CSV
  const exportCsvBtn = document.getElementById("exportCsvBtn");
  if (exportCsvBtn) exportCsvBtn.addEventListener("click", () => exportAuditLogs("csv"));

  // Search + filters (replace onkeyup/onchange)
  const auditSearch = document.getElementById("auditSearch");
  const categoryFilter = document.getElementById("auditCategoryFilter");
  const severityFilter = document.getElementById("auditSeverityFilter");
  const timeFilter = document.getElementById("auditTimeFilter");

  if (auditSearch) auditSearch.addEventListener("input", filterAudit);
  if (categoryFilter) categoryFilter.addEventListener("change", filterAudit);
  if (severityFilter) severityFilter.addEventListener("change", filterAudit);
  if (timeFilter) timeFilter.addEventListener("change", filterAudit);

  // Modal close button
  const closeBtn = document.getElementById("logModalCloseBtn");
  if (closeBtn) closeBtn.addEventListener("click", () => closeModal("logModal"));

  // Close modal by clicking outside
  document.addEventListener("click", (e) => {
    const modal = document.getElementById("logModal");
    if (modal && e.target === modal) closeModal("logModal");
  });

  // ESC to close modal
  document.addEventListener("keydown", (e) => {
    const modal = document.getElementById("logModal");
    if (e.key === "Escape" && modal && modal.style.display === "flex") {
      closeModal("logModal");
    }
  });

  // ✅ Row click -> open details (delegation)
  const tbody = document.getElementById("auditTableBody");
  if (tbody) {
    tbody.addEventListener("click", (e) => {
      const tr = e.target.closest("tr[data-audit-id]");
      if (!tr) return;
      const auditId = Number(tr.getAttribute("data-audit-id"));
      if (!Number.isFinite(auditId)) return;
      viewLogDetails(auditId);
    });
  }
}

async function checkSessionAndLoad() {
  try {
    const sessionResponse = await fetch("./api/check_session.php", {
      method: "GET",
      credentials: "include",
    });

    const sessionData = await sessionResponse.json();
    console.log("Session check:", sessionData);

    if (!sessionData.logged_in) {
      alert("You are not logged in. Redirecting to login page...");
      window.location.href = "./index.html";
      return;
    }

    const userSpan = document.getElementById("navUsername") || document.querySelector(".navbar-user span");
    if (userSpan && sessionData.username) {
      userSpan.textContent = sessionData.username;
    }

    await loadAuditData();
  } catch (error) {
    console.error("Session check error:", error);
    showError("Authentication error. Please login again.");
    setTimeout(() => {
      window.location.href = "./index.html";
    }, 2000);
  }
}

async function loadAuditData() {
  try {
    console.log("Fetching audit data from:", "./api/admin_security_audit.php");

    const response = await fetch("./api/admin_security_audit.php", {
      method: "GET",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
    });

    const data = await response.json();

    if (!response.ok) {
      if (response.status === 401) {
        alert("Session expired. Please login again.");
        window.location.href = "./index.html";
        return;
      }
      throw new Error(data.error || "Failed to fetch audit data");
    }

    if (data.success) {
      allLogs = data.logs || [];
      filteredLogs = [...allLogs];

      renderAuditTable(filteredLogs);

      // These sections may or may not exist in your HTML; functions already guard with null checks.
      renderCategoryBreakdown(data.categoryBreakdown || []);
      renderRecentActivity(data.recentActivity || []);
      renderEventTimeline(data.timelineData || []);
    } else {
      showError("Failed to load audit data: " + (data.error || "Unknown error"));
    }
  } catch (error) {
    console.error("Error loading audit data:", error);
    showError("Error loading audit data: " + error.message);
  }
}

function renderAuditTable(logs) {
  const tbody = document.getElementById("auditTableBody");
  if (!tbody) return;

  if (!logs || logs.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="no-data">No audit logs found</td></tr>';
    return;
  }

  tbody.innerHTML = logs
    .map((log) => {
      const id = Number(log.audit_id);
      const safeCategory = escapeHtml(log.event_category);
      const safeSeverity = escapeHtml(log.severity);

      return `
        <tr data-audit-id="${escapeHtml(id)}" style="cursor:pointer;">
          <td>${escapeHtml(log.audit_id)}</td>
          <td>${formatDateTime(log.created_at)}</td>
          <td>${escapeHtml(log.username)}</td>
          <td>${escapeHtml(log.event_type)}</td>
          <td><span class="badge badge-${safeCategory}">${safeCategory}</span></td>
          <td><span class="badge badge-${safeSeverity}">${safeSeverity}</span></td>
          <td>${escapeHtml(log.description)}</td>
          <td title="${escapeHtml(log.user_agent)}">${truncate(log.user_agent, 30)}</td>
        </tr>
      `;
    })
    .join("");
}

function renderCategoryBreakdown(breakdown) {
  const tbody = document.getElementById("categoryBreakdownBody");
  if (!tbody) return;

  if (!breakdown || breakdown.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="no-data">No data available</td></tr>';
    return;
  }

  tbody.innerHTML = breakdown
    .map(
      (cat) => `
        <tr>
          <td><span class="badge badge-${escapeHtml(cat.category)}">${escapeHtml(cat.category)}</span></td>
          <td><strong>${escapeHtml(cat.total)}</strong></td>
          <td>${escapeHtml(cat.info)}</td>
          <td>${escapeHtml(cat.warning)}</td>
          <td>${escapeHtml(cat.error)}</td>
          <td>${escapeHtml(cat.critical)}</td>
        </tr>
      `
    )
    .join("");
}

function renderRecentActivity(activities) {
  const tbody = document.getElementById("recentActivityBody");
  if (!tbody) return;

  if (!activities || activities.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="no-data">No user activity data</td></tr>';
    return;
  }

  tbody.innerHTML = activities
    .map((activity) => {
      const statusClass =
        activity.status === "active"
          ? "success"
          : activity.status === "suspended"
          ? "error"
          : "warning";

      const mfaIcon = activity.totp_enabled ? "🔒" : "🔓";

      return `
        <tr>
          <td>${escapeHtml(activity.username)}<br><small>${escapeHtml(activity.user_email)}</small></td>
          <td><span class="badge badge-${escapeHtml(activity.role)}">${escapeHtml(activity.role)}</span></td>
          <td><span class="badge badge-${statusClass}">${escapeHtml(activity.status)}</span></td>
          <td title="${activity.totp_enabled ? "Enabled" : "Disabled"}">${mfaIcon}</td>
          <td><strong>${escapeHtml(activity.event_count)}</strong></td>
          <td>${escapeHtml(activity.login_count)} logins<br>${escapeHtml(activity.file_count)} file ops</td>
          <td>${formatDateTime(activity.last_activity)}</td>
        </tr>
      `;
    })
    .join("");
}

function renderEventTimeline(timelineData) {
  const canvas = document.getElementById("timelineChart");
  if (!canvas) return;

  const ctx = canvas.getContext("2d");

  if (!timelineData || timelineData.length === 0) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = "#666";
    ctx.font = "14px Arial";
    ctx.textAlign = "center";
    ctx.fillText("No timeline data available", canvas.width / 2, canvas.height / 2);
    return;
  }

  canvas.width = canvas.offsetWidth;
  canvas.height = 300;

  const padding = 60;
  const chartWidth = canvas.width - padding * 2;
  const chartHeight = canvas.height - padding * 2;

  const maxValue = Math.max(...timelineData.map((d) => Number(d.total || 0)));
  const yScale = chartHeight / (maxValue * 1.1 || 1);
  const xStep = timelineData.length > 1 ? chartWidth / (timelineData.length - 1) : chartWidth;

  ctx.clearRect(0, 0, canvas.width, canvas.height);

  // Grid + Y labels
  ctx.strokeStyle = "#e0e0e0";
  ctx.lineWidth = 1;
  for (let i = 0; i <= 5; i++) {
    const y = padding + (chartHeight / 5) * i;
    ctx.beginPath();
    ctx.moveTo(padding, y);
    ctx.lineTo(canvas.width - padding, y);
    ctx.stroke();

    const value = Math.round(maxValue * (1 - i / 5));
    ctx.fillStyle = "#666";
    ctx.font = "12px Arial";
    ctx.textAlign = "right";
    ctx.fillText(String(value), padding - 10, y + 4);
  }

  const severities = ["info", "warning", "error", "critical"];
  const colors = {
    info: "#4CAF50",
    warning: "#FF9800",
    error: "#F44336",
    critical: "#9C27B0",
  };

  severities.forEach((severity) => {
    ctx.strokeStyle = colors[severity];
    ctx.lineWidth = 2;
    ctx.beginPath();

    timelineData.forEach((point, index) => {
      const x = padding + index * xStep;
      const y = padding + chartHeight - Number(point[severity] || 0) * yScale;
      if (index === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });

    ctx.stroke();

    ctx.fillStyle = colors[severity];
    timelineData.forEach((point, index) => {
      const x = padding + index * xStep;
      const y = padding + chartHeight - Number(point[severity] || 0) * yScale;
      ctx.beginPath();
      ctx.arc(x, y, 4, 0, 2 * Math.PI);
      ctx.fill();
    });
  });

  // X labels
  ctx.fillStyle = "#666";
  ctx.font = "11px Arial";
  ctx.textAlign = "center";
  const step = Math.max(1, Math.ceil(timelineData.length / 7));
  timelineData.forEach((point, index) => {
    if (index % step === 0) {
      const x = padding + index * xStep;
      const date = new Date(point.date);
      const label = `${date.getMonth() + 1}/${date.getDate()}`;
      ctx.fillText(label, x, canvas.height - padding + 20);
    }
  });

  // Legend
  const legendY = 20;
  let legendX = padding;
  severities.forEach((severity) => {
    ctx.fillStyle = colors[severity];
    ctx.fillRect(legendX, legendY, 15, 15);
    ctx.fillStyle = "#333";
    ctx.font = "12px Arial";
    ctx.textAlign = "left";
    ctx.fillText(severity.charAt(0).toUpperCase() + severity.slice(1), legendX + 20, legendY + 12);
    legendX += 100;
  });
}

function filterAudit() {
  const searchText = document.getElementById("auditSearch")?.value.toLowerCase() || "";
  const category = document.getElementById("auditCategoryFilter")?.value || "";
  const severity = document.getElementById("auditSeverityFilter")?.value || "";
  const timeFilter = document.getElementById("auditTimeFilter")?.value || "";

  filteredLogs = allLogs.filter((log) => {
    const matchesSearch =
      !searchText ||
      String(log.description || "").toLowerCase().includes(searchText) ||
      String(log.username || "").toLowerCase().includes(searchText) ||
      String(log.event_type || "").toLowerCase().includes(searchText);

    const matchesCategory = !category || log.event_category === category;
    const matchesSeverity = !severity || log.severity === severity;
    const matchesTime = !timeFilter || filterByTime(log.created_at, timeFilter);

    return matchesSearch && matchesCategory && matchesSeverity && matchesTime;
  });

  renderAuditTable(filteredLogs);
}

function filterByTime(dateString, filter) {
  const logDate = new Date(dateString);
  const now = new Date();

  switch (filter) {
    case "today":
      return logDate.toDateString() === now.toDateString();
    case "week": {
      const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
      return logDate >= weekAgo;
    }
    case "month": {
      const monthAgo = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
      return logDate >= monthAgo;
    }
    default:
      return true;
  }
}

async function exportAuditLogs(format) {
  if (format !== "csv") {
    showError("Only CSV export is supported");
    return;
  }

  try {
    const response = await fetch("./api/admin_security_audit.php?export=" + format, {
      method: "GET",
      credentials: "include",
    });

    if (!response.ok) throw new Error("Export failed");

    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);

    const a = document.createElement("a");
    a.href = url;
    a.download = `audit_logs_${new Date().toISOString().split("T")[0]}.${format}`;
    document.body.appendChild(a);
    a.click();

    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);

    showSuccess("Audit logs exported successfully");
  } catch (error) {
    console.error("Export error:", error);
    showError("Failed to export audit logs: " + error.message);
  }
}

function viewLogDetails(auditId) {
  const log = allLogs.find((l) => Number(l.audit_id) === Number(auditId));

  if (!log) {
    showError("Log details not found");
    return;
  }

  const modalBody = document.getElementById("userModalBody");
  if (!modalBody) {
    showError("Modal container not found in HTML");
    return;
  }

  let metadataHtml = "";
  if (log.metadata_json) {
    try {
      const metadata = JSON.parse(log.metadata_json);
      metadataHtml = `
        <div class="detail-group">
          <label>Metadata:</label>
          <pre>${escapeHtml(JSON.stringify(metadata, null, 2))}</pre>
        </div>
      `;
    } catch {
      metadataHtml = `
        <div class="detail-group">
          <label>Metadata:</label>
          <p>${escapeHtml(log.metadata_json)}</p>
        </div>
      `;
    }
  }

  modalBody.innerHTML = `
    <div class="log-details">
      <div class="detail-group"><label>Audit ID:</label><p>${escapeHtml(log.audit_id)}</p></div>
      <div class="detail-group"><label>Timestamp:</label><p>${formatDateTime(log.created_at)}</p></div>
      <div class="detail-group"><label>User:</label><p>${escapeHtml(log.username)} (${escapeHtml(log.user_email)})</p></div>
      <div class="detail-group"><label>Event Type:</label><p>${escapeHtml(log.event_type)}</p></div>
      <div class="detail-group"><label>Category:</label><p><span class="badge badge-${escapeHtml(log.event_category)}">${escapeHtml(log.event_category)}</span></p></div>
      <div class="detail-group"><label>Severity:</label><p><span class="badge badge-${escapeHtml(log.severity)}">${escapeHtml(log.severity)}</span></p></div>
      <div class="detail-group"><label>Description:</label><p>${escapeHtml(log.description)}</p></div>
      <div class="detail-group"><label>User Agent:</label><p style="word-break: break-all;">${escapeHtml(log.user_agent)}</p></div>
      ${metadataHtml}
    </div>
  `;

  const modal = document.getElementById("logModal");
  if (modal) modal.style.display = "flex";
}

function closeModal(modalId) {
  const el = document.getElementById(modalId);
  if (el) el.style.display = "none";
}

function formatDateTime(dateString) {
  if (!dateString || dateString === "Never") return dateString || "N/A";
  const date = new Date(dateString);
  return date.toLocaleString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  });
}

function escapeHtml(text) {
  if (text === null || text === undefined) return "";
  const div = document.createElement("div");
  div.textContent = String(text);
  return div.innerHTML;
}

function truncate(text, length) {
  if (text === null || text === undefined) return "";
  const s = String(text);
  if (s.length <= length) return escapeHtml(s);
  return escapeHtml(s.substring(0, length)) + "...";
}

function showError(message) {
  alert(message);
}

function showSuccess(message) {
  alert(message);
}

function logout() {
  if (confirm("Are you sure you want to logout?")) {
    window.location.href = "./logout.php";
  }
}
