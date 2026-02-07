 const API_BASE_URL = "./api/my_files.php";
      let currentFile = null;

      // Get file ID from URL
      const urlParams = new URLSearchParams(window.location.search);
      const fileId = urlParams.get("id");

      if (!fileId) {
        window.location.href = "./my_files.html";
      }

      // Format date
      function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString("en-US", {
          year: "numeric",
          month: "long",
          day: "numeric",
          hour: "2-digit",
          minute: "2-digit",
        });
      }

      // Format file size
      function formatSize(bytes) {
        const mb = bytes / (1024 * 1024);
        if (mb >= 1000) {
          return (mb / 1000).toFixed(2) + " GB";
        }
        return mb.toFixed(2) + " MB";
      }

      // Get file icon
      function getFileIcon(mimeType) {
        if (!mimeType) return "📄";
        if (mimeType.startsWith("image/")) return "🖼️";
        if (mimeType.startsWith("video/")) return "🎥";
        if (mimeType.startsWith("audio/")) return "🎵";
        if (mimeType.includes("pdf")) return "📕";
        if (mimeType.includes("word") || mimeType.includes("document"))
          return "📝";
        if (mimeType.includes("excel") || mimeType.includes("sheet"))
          return "📊";
        if (
          mimeType.includes("powerpoint") ||
          mimeType.includes("presentation")
        )
          return "📺";
        if (mimeType.includes("zip") || mimeType.includes("rar")) return "📦";
        return "📄";
      }

      // Show feedback
      function showFeedback(message, type = "info") {
        const feedback = document.getElementById("feedback-message");
        feedback.className = `show ${type}`;
        feedback.innerHTML = `
          ${message}
          <button class="feedback-close" onclick="this.parentElement.classList.remove('show')">×</button>
        `;
        setTimeout(() => feedback.classList.remove("show"), 5000);
      }

      // Load file details
      async function loadFileDetails() {
        try {
          const response = await fetch(
            `${API_BASE_URL}?action=get_file_details&file_id=${fileId}`
          );
          const data = await response.json();

          if (data.success) {
            currentFile = data.file;
            displayFileDetails(currentFile);
          } else {
            showFeedback(data.message || "Error loading file details", "error");
            setTimeout(() => (window.location.href = "./my_files.html"), 2000);
          }
        } catch (error) {
          console.error("Error:", error);
          showFeedback("Failed to load file details", "error");
          setTimeout(() => (window.location.href = "./my_files.html"), 2000);
        }
      }

      // Display file details
      function displayFileDetails(file) {
        // Hide loading, show content
        document.getElementById("loading-container").style.display = "none";
        document.getElementById("details-content").style.display = "block";

        // File preview
        const icon = getFileIcon(file.mime_type);
        document.getElementById("file-icon").textContent = icon;
        document.getElementById("file-name").textContent = file.file_name;

        // File information
        document.getElementById("detail-name").textContent = file.file_name;
        document.getElementById("detail-size").textContent = formatSize(
          file.file_size
        );
        document.getElementById("detail-type").textContent =
          file.mime_type || "Unknown";

        // Status
        const isExpired =
          new Date(file.expiry_time) < new Date() ||
          file.decrypt_count >= file.max_decrypt_count;
        const statusHtml = `
          <span class="status-badge ${
            isExpired ? "status-expired" : "status-active"
          }">
            ${isExpired ? "✕ Expired" : "✓ Active"}
          </span>
        `;
        document.getElementById("detail-status").innerHTML = statusHtml;

        // Sharing information
        document.getElementById("detail-sender").innerHTML = `
          <strong>${file.sender_name}</strong> (@${file.sender_username})
        `;
        document.getElementById("detail-receiver").innerHTML = `
          <strong>${file.receiver_name}</strong> (@${file.receiver_username})
        `;
        document.getElementById("detail-uploaded").textContent = formatDate(
          file.uploaded_at
        );
        document.getElementById("detail-expires").textContent = formatDate(
          file.expiry_time
        );

        // Download information
        const downloadPercent = Math.round(
          (file.decrypt_count / file.max_decrypt_count) * 100
        );
        document.getElementById("detail-downloads").innerHTML = `
          <strong>${file.decrypt_count}</strong> / ${file.max_decrypt_count}
        `;
        document.getElementById(
          "download-progress-text"
        ).textContent = `${downloadPercent}%`;
        document.getElementById(
          "download-progress-bar"
        ).style.width = `${downloadPercent}%`;

        // Encryption information
        if (file.encryption_score) {
          document.getElementById("encryption-section").style.display = "block";
          document.getElementById("detail-encryption-score").innerHTML = `
            <span class="encryption-badge">${file.encryption_score}/100</span>
          `;
          document.getElementById("detail-encryption-rating").textContent =
            file.encryption_rating || "N/A";
          document.getElementById("detail-encryption-time").textContent =
            file.encryption_time_ms ? `${file.encryption_time_ms}ms` : "N/A";
        }

        // Show warnings
        if (isExpired) {
          if (new Date(file.expiry_time) < new Date()) {
            document.getElementById("expired-warning").style.display = "flex";
          } else if (file.decrypt_count >= file.max_decrypt_count) {
            document.getElementById("limit-warning").style.display = "flex";
          }
          document.getElementById("download-btn").disabled = true;
        }
      }

      // Download file
      function downloadFile() {
        showFeedback(
          "Download functionality would be implemented here",
          "info"
        );

        window.location.href = `download.html?file_id=${fileId}`;
      }

      // Load details on page load
      document.addEventListener("DOMContentLoaded", loadFileDetails);