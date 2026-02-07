(() => {
  const $ = (sel) => document.querySelector(sel);

  const fileInfoContainer = $("#file-info");
  const downloadBtn = $("#download-decrypt-btn");
  const progressContainer = $("#progress-container");
  const progressBar = $("#progress-bar");
  const progressText = $("#progress-text");
  const feedback = $("#feedback-message");
  const backButton = $("#back-to-list");

  if (downloadBtn) {
    downloadBtn.disabled = true;
  }

  // --- State management ---
  let currentMode = "list"; // 'list' or 'single'
  let currentSelectedFileId = null;
  let currentFile = null;
  let downloadComplete = false;
  let allFiles = []; // Store all files for filtering

  // Persist filter state so re-renders don't wipe user input
  const filterState = {
    search: "",
    type: "all",
    size: "all",
    date: "",
  };
  // Keep focus/caret stable when we re-render the table
  let lastFocusedFilterId = "search-input";
  let lastCaretPos = 0;

  // Will hold encryption metrics for the current file (receiver-side)
  let currentEncryptionMetrics = null;

  // --- Feedback helpers ---
  let feedbackTextSpan = null;
  if (feedback) {
    feedbackTextSpan = document.createElement("span");
    feedbackTextSpan.id = "feedback-text";
    feedback.appendChild(feedbackTextSpan);

    const closeBtn = document.createElement("button");
    closeBtn.type = "button";
    closeBtn.className = "feedback-close";
    closeBtn.setAttribute("aria-label", "Close message");
    closeBtn.innerHTML = "&times;";
    feedback.appendChild(closeBtn);

    closeBtn.addEventListener("click", () => {
      feedback.classList.remove("show", "error", "success", "info");
      feedbackTextSpan.textContent = "";
    });
  }

  function showMessage(msg, type = "info") {
    if (!feedback || !feedbackTextSpan) {
      console.log(`[download] ${type}: ${msg}`);
      return;
    }
    feedback.classList.remove("show", "error", "success", "info");

    let icon = "ℹ️";
    if (type === "error") icon = "❌";
    if (type === "success") icon = "✅";

    feedbackTextSpan.innerHTML = `<span style="font-size: 18px; margin-right: 8px;">${icon}</span><span>${msg}</span>`;
    if (type) feedback.classList.add(type);
    if (msg) {
      feedback.classList.add("show");
      feedback.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }
    console.log(`[download] ${type}: ${msg}`);
  }

  function showProgress() {
    if (progressContainer) {
      progressContainer.style.display = "block";
    }
  }

  function hideProgress() {
    if (progressContainer) {
      progressContainer.style.display = "none";
    }
  }

  function updateProgress(percent, text) {
    if (progressBar) progressBar.style.width = `${percent}%`;
    if (progressText) progressText.textContent = text || "";
  }

  function showBackButton() {
    if (backButton) {
      backButton.classList.add("show");
    }
  }

  function hideBackButton() {
    if (backButton) {
      backButton.classList.remove("show");
    }
  }

  function escapeHtml(str) {
    return (str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function getFileIcon(mimeType) {
    if (!mimeType) return "📄";
    if (mimeType.startsWith("image/")) return "🖼️";
    if (mimeType.startsWith("video/")) return "🎥";
    if (mimeType.startsWith("audio/")) return "🎵";
    if (mimeType.includes("pdf")) return "📕";
    if (mimeType.includes("word") || mimeType.includes("document")) return "📝";
    if (mimeType.includes("sheet") || mimeType.includes("excel")) return "📊";
    if (mimeType.includes("zip") || mimeType.includes("rar")) return "📦";
    return "📄";
  }

  function getFileCategory(mimeType) {
    if (!mimeType) return "other";
    if (mimeType.startsWith("image/")) return "image";
    if (mimeType.startsWith("video/")) return "video";
    if (mimeType.startsWith("audio/")) return "audio";
    if (mimeType.includes("pdf")) return "pdf";
    if (mimeType.includes("word") || mimeType.includes("document"))
      return "document";
    if (mimeType.includes("sheet") || mimeType.includes("excel"))
      return "spreadsheet";
    if (
      mimeType.includes("zip") ||
      mimeType.includes("rar") ||
      mimeType.includes("compress")
    )
      return "archive";
    return "other";
  }

  // Helper function to format sender name consistently - shows username only
  function formatSenderName(sender) {
    if (!sender) return "Unknown";
    return escapeHtml(sender.username || "Unknown");
  }

  // Helper function to get plain text sender name for table - shows username only
  function getPlainSenderName(sender) {
    if (!sender) return "Unknown";
    return sender.username || "Unknown";
  }

  // Helper function for file size formatting
  function formatFileSize(bytes) {
    const n = Number(bytes) || 0;
    if (n < 1024) return `${n} B`;
    if (n < 1048576) return `${(n / 1024).toFixed(2)} KB`;
    if (n < 1073741824) return `${(n / 1048576).toFixed(2)} MB`;
    return `${(n / 1073741824).toFixed(2)} GB`;
  }

  // --- Small numeric helpers + entropy ---

  function clamp(v, min, max) {
    return v < min ? min : v > max ? max : v;
  }

  function computeEntropy(bytes, maxSample = 500000) {
    const len = bytes.length;
    if (len === 0) {
      return { entropyBitsPerByte: 0, sampleSize: 0 };
    }

    const sampleSize = Math.min(len, maxSample);
    const counts = new Uint32Array(256);

    for (let i = 0; i < sampleSize; i++) {
      counts[bytes[i]]++;
    }

    let entropy = 0;
    for (let i = 0; i < 256; i++) {
      const c = counts[i];
      if (!c) continue;
      const p = c / sampleSize;
      entropy -= p * Math.log2(p);
    }

    return {
      entropyBitsPerByte: entropy,
      sampleSize,
    };
  }

  function getSessionUserName() {
    try {
      const raw = sessionStorage.getItem("user");
      if (!raw) return null;
      const u = JSON.parse(raw);
      return u.user_fullname || u.username || null;
    } catch {
      return null;
    }
  }

  // --- Base64 validation helpers ---

  function isValidBase64(str) {
    if (!str || typeof str !== 'string') return false;
    
    // Remove whitespace
    str = str.trim();
    
    // Check if string length is valid for base64
    if (str.length % 4 !== 0) return false;
    
    // Check if string contains only valid base64 characters
    const base64Regex = /^[A-Za-z0-9+/]*={0,2}$/;
    return base64Regex.test(str);
  }

  function safeBase64Decode(base64String, fieldName = 'data') {
    if (!base64String) {
      throw new Error(`${fieldName} is empty or undefined`);
    }
    
    if (typeof base64String !== 'string') {
      throw new Error(`${fieldName} is not a string (got ${typeof base64String})`);
    }
    
    // Trim whitespace
    base64String = base64String.trim();
    
    // Validate Base64 format
    if (!isValidBase64(base64String)) {
      console.error(`Invalid Base64 in ${fieldName}:`, {
        length: base64String.length,
        firstChars: base64String.substring(0, 50),
        lastChars: base64String.substring(base64String.length - 50)
      });
      throw new Error(`${fieldName} contains invalid Base64 encoding`);
    }
    
    try {
      return Uint8Array.from(atob(base64String), (c) => c.charCodeAt(0));
    } catch (err) {
      console.error(`Base64 decode error in ${fieldName}:`, err);
      throw new Error(`Failed to decode ${fieldName}: ${err.message}`);
    }
  }

  // --- Crypto helpers ---

  async function getPrivateKeyFromSession() {
    const jwkStr = sessionStorage.getItem("unlockedPrivateKey");
    if (!jwkStr) {
      throw new Error(
        "Your encryption keys are not unlocked. Please sign in again.",
      );
    }
    let jwk;
    try {
      jwk = JSON.parse(jwkStr);
    } catch (e) {
      console.error("Failed to parse unlockedPrivateKey JWK:", e);
      throw new Error("Invalid key material in session.");
    }

    return crypto.subtle.importKey(
      "jwk",
      jwk,
      { name: "RSA-OAEP", hash: "SHA-256" },
      true,
      ["decrypt"],
    );
  }

  async function importAesKeyFromJwk(jwk) {
    return crypto.subtle.importKey("jwk", jwk, { name: "AES-GCM" }, true, [
      "encrypt",
      "decrypt",
    ]);
  }

  async function decryptWithKey(aesKey, ciphertextBytes, ivBytes) {
    return crypto.subtle.decrypt(
      { name: "AES-GCM", iv: ivBytes },
      aesKey,
      ciphertextBytes,
    );
  }

  // MAIN DECRYPTION FUNCTION - WITH VALIDATION
  async function decryptFile(
    encryptedDataB64,
    encFileKeyB64,
    hashEncB64,
    policy,
    privateKey,
  ) {
    console.log('🔐 Starting decryption process...');
    console.log('Data validation:', {
      hasEncryptedData: !!encryptedDataB64,
      encryptedDataType: typeof encryptedDataB64,
      encryptedDataLength: encryptedDataB64?.length,
      hasEncFileKey: !!encFileKeyB64,
      encFileKeyLength: encFileKeyB64?.length,
      hasHashEnc: !!hashEncB64,
      hashEncLength: hashEncB64?.length
    });
    
    updateProgress(20, "Decrypting file key...");

    // Validate and decode encrypted file key
    const encFileKeyBytes = safeBase64Decode(encFileKeyB64, 'encFileKey');
    console.log('✓ Decoded encFileKey:', encFileKeyBytes.length, 'bytes');
    
    const fileKeyBytes = await crypto.subtle.decrypt(
      { name: "RSA-OAEP" },
      privateKey,
      encFileKeyBytes,
    );
    console.log('✓ Decrypted file key');

    const fileKeyJwk = JSON.parse(new TextDecoder().decode(fileKeyBytes));
    const fileKey = await importAesKeyFromJwk(fileKeyJwk);
    console.log('✓ Imported AES key');

    updateProgress(40, "Decrypting integrity hash...");

    // Validate and decode encrypted hash
    const hashEncBytes = safeBase64Decode(hashEncB64, 'hashEnc');
    console.log('✓ Decoded hashEnc:', hashEncBytes.length, 'bytes');
    
    const hashIv = hashEncBytes.slice(0, 12);
    const hashEncData = hashEncBytes.slice(12);

    const hashBytes = await decryptWithKey(fileKey, hashEncData, hashIv);
    const hash = new Uint8Array(hashBytes);
    console.log('✓ Decrypted hash:', hash.length, 'bytes');

    updateProgress(60, "Decrypting file...");

    // Validate and decode encrypted file data
    console.log('Attempting to decode encryptedData...');
    const encDataBytes = safeBase64Decode(encryptedDataB64, 'encryptedData');
    console.log('✓ Decoded encryptedData:', encDataBytes.length, 'bytes');
    
    const fileIv = encDataBytes.slice(0, 12);
    const fileEncData = encDataBytes.slice(12);
    console.log('Split IV and ciphertext:', {
      ivLength: fileIv.length,
      ciphertextLength: fileEncData.length
    });

    const decryptedFileBuf = await decryptWithKey(fileKey, fileEncData, fileIv);
    console.log('✓ Decrypted file data:', decryptedFileBuf.byteLength, 'bytes');

    updateProgress(80, "Verifying integrity...");

    const computedHash = await crypto.subtle.digest("SHA-256", encDataBytes);
    const computedHashArray = new Uint8Array(computedHash);

    if (computedHashArray.length !== hash.length) {
      throw new Error("Hash mismatch! File integrity check failed.");
    }
    for (let i = 0; i < hash.length; i++) {
      if (computedHashArray[i] !== hash[i]) {
        throw new Error("Hash mismatch! File integrity check failed.");
      }
    }
    console.log('✓ Integrity verified');

    updateProgress(100, "Decryption complete.");
    return decryptedFileBuf;
  }

  // --- Encryption metrics builder (receiver side) ---

  function buildEncryptionMetricsForReceiver(
    file,
    plainEntropyInfo,
    cipherEntropyInfo,
  ) {
    const plainEntropy = plainEntropyInfo.entropyBitsPerByte;
    const cipherEntropy = cipherEntropyInfo.entropyBitsPerByte;

    const normalizedPlain = clamp(plainEntropy / 8, 0, 1);
    const normalizedCipher = clamp(cipherEntropy / 8, 0, 1);

    const beforeScore = clamp(Math.round(normalizedPlain * 25), 0, 25);

    let sizeOverheadPercent = null;
    let sizeOverheadBytes = null;
    try {
      if (file.encryptedData) {
        const encBytes = Uint8Array.from(atob(file.encryptedData), (c) =>
          c.charCodeAt(0),
        );
        const original = Number(file.fileSize) || 0;
        if (original > 0) {
          sizeOverheadBytes = encBytes.length - original;
          sizeOverheadPercent = (sizeOverheadBytes / original) * 100;
        }
      }
    } catch (e) {
      console.warn("Failed to compute size overhead:", e);
    }

    let overheadScore = 15;
    if (sizeOverheadPercent != null) {
      if (sizeOverheadPercent <= 2) {
        overheadScore = 15;
      } else if (sizeOverheadPercent <= 5) {
        overheadScore = 13;
      } else if (sizeOverheadPercent <= 10) {
        overheadScore = 11;
      } else if (sizeOverheadPercent <= 20) {
        overheadScore = 9;
      } else {
        overheadScore = 7;
      }
    }

    let entropyScore;
    if (normalizedCipher >= 0.99) {
      entropyScore = 25;
    } else if (normalizedCipher >= 0.97) {
      entropyScore = 23;
    } else if (normalizedCipher >= 0.94) {
      entropyScore = 20;
    } else if (normalizedCipher >= 0.9) {
      entropyScore = 17;
    } else if (normalizedCipher >= 0.85) {
      entropyScore = 14;
    } else {
      entropyScore = 10;
    }

    const policy = file.policy || {};
    const hasExpiry = !!policy.expiryTime;
    const hasDownloadLimit =
      typeof policy.maxDecryptCount === "number" && policy.maxDecryptCount > 0;

    let policyScore = 10;
    if (hasExpiry) policyScore += 5;
    if (hasDownloadLimit) policyScore += 5;

    const algorithmScore = 20;

    let afterScore =
      algorithmScore + entropyScore + overheadScore + policyScore;
    afterScore = clamp(Math.round(afterScore), 0, 100);

    let rating = "Poor";
    let ratingColor = "#dc2626";
    if (afterScore >= 90) {
      rating = "Excellent";
      ratingColor = "#16a34a";
    } else if (afterScore >= 75) {
      rating = "Very Good";
      ratingColor = "#22c55e";
    } else if (afterScore >= 60) {
      rating = "Good";
      ratingColor = "#84cc16";
    } else if (afterScore >= 40) {
      rating = "Fair";
      ratingColor = "#eab308";
    }

    const breakdown = {
      rsaKey: { score: 10, max: 10, status: "excellent" },
      aesKey: { score: 10, max: 10, status: "excellent" },
      algorithm: { score: 10, max: 10, status: "excellent" },
      keyExchange: { score: 10, max: 10, status: "excellent" },
      hash: { score: 5, max: 5, status: "excellent" },
      authEncryption: { score: 5, max: 5, status: "excellent" },
      ivQuality: { score: 5, max: 5, status: "excellent" },
      e2ee: { score: 10, max: 10, status: "enabled" },
      expiry: {
        score: hasExpiry ? 10 : 0,
        max: 10,
        status: hasExpiry ? "enabled" : "warning",
      },
      downloadLimit: {
        score: hasDownloadLimit ? 10 : 0,
        max: 10,
        status: hasDownloadLimit ? "enabled" : "warning",
      },
      cipherEntropy: {
        score: entropyScore,
        max: 25,
        status:
          normalizedCipher >= 0.97
            ? "excellent"
            : normalizedCipher >= 0.9
              ? "fair"
              : "warning",
        entropyBitsPerByte: cipherEntropy,
      },
    };

    const recommendations = [];

    if (!hasExpiry) {
      recommendations.push(
        "Consider enabling an expiry time for highly sensitive files.",
      );
    }

    if (!hasDownloadLimit) {
      recommendations.push(
        "Consider enforcing a maximum number of decryptions to reduce misuse.",
      );
    }

    if (sizeOverheadPercent != null && sizeOverheadPercent > 10) {
      recommendations.push(
        "Encryption overhead is relatively high. You may want to compress the file before encryption if size is a concern.",
      );
    }

    if (normalizedCipher < 0.9) {
      recommendations.push(
        "Ciphertext entropy is below typical levels for strong encryption. Ensure keys and IVs are never reused.",
      );
    }

    if (recommendations.length === 0) {
      recommendations.push(
        "Encryption posture looks strong with modern algorithms and high ciphertext entropy.",
      );
    }

    const receiverName = getSessionUserName();
    const senderName =
      file.sender && file.sender.username ? file.sender.username : "Unknown";

    return {
      score: afterScore,
      maxScore: 100,
      percentage: afterScore,
      rating,
      ratingColor,
      beforeScore,
      afterScore,
      plaintextEntropyBitsPerByte: plainEntropy,
      plaintextSampleSize: plainEntropyInfo.sampleSize,
      cipherEntropyBitsPerByte: cipherEntropy,
      cipherSampleSize: cipherEntropyInfo.sampleSize,
      sizeOverheadPercent,
      sizeOverheadBytes,
      breakdown,
      recommendations,
      policy,
      fileName: file.fileName,
      fileSize: file.fileSize,
      receiverName,
      senderName,
    };
  }

  // PDF Report Generation
  function downloadPdfReport(metrics) {
    console.log("📄 Starting PDF report generation...");
    console.log("Metrics data:", metrics);

    if (typeof window.jspdf === "undefined") {
      console.error("❌ jsPDF library not loaded!");
      alert(
        "PDF library is not available. Please refresh the page and try again.",
      );
      return;
    }

    const { jsPDF } = window.jspdf;
    console.log("✅ jsPDF loaded successfully");

    try {
      const doc = new jsPDF();

      const fileName = metrics.fileName || "Encrypted file";
      const receiver =
        metrics.receiverName || metrics.receiverEmail || "Receiver";
      const sender = metrics.senderName || "Unknown sender";
      const now = new Date();

      const beforeScore =
        typeof metrics.beforeScore === "number" ? metrics.beforeScore : null;
      const afterScore =
        typeof metrics.afterScore === "number"
          ? metrics.afterScore
          : (metrics.score ?? null);

      const plainEntropy =
        typeof metrics.plaintextEntropyBitsPerByte === "number"
          ? metrics.plaintextEntropyBitsPerByte
          : null;
      const cipherEntropy =
        typeof metrics.cipherEntropyBitsPerByte === "number"
          ? metrics.cipherEntropyBitsPerByte
          : null;

      let y = 15;

      doc.setFont("helvetica", "bold");
      doc.setFontSize(18);
      doc.setTextColor(30, 41, 59);
      doc.text("Encryption Effectiveness Report", 14, y);
      y += 10;

      doc.setFontSize(10);
      doc.setFont("helvetica", "normal");
      doc.setTextColor(100, 116, 139);
      doc.text(`Generated: ${now.toLocaleString()}`, 14, y);
      y += 6;
      doc.text(`File: ${fileName}`, 14, y);
      y += 6;

      if (metrics.fileSize) {
        const fileSize = formatFileSize(metrics.fileSize);
        doc.text(`Size: ${fileSize}`, 14, y);
        y += 6;
      }

      doc.text(`Sender: ${sender}`, 14, y);
      y += 5;
      doc.text(`Receiver: ${receiver}`, 14, y);
      y += 12;

      doc.setFont("helvetica", "bold");
      doc.setFontSize(14);
      doc.setTextColor(30, 41, 59);
      doc.text("Overall Security Scores", 14, y);
      y += 8;

      doc.setFont("helvetica", "normal");
      doc.setFontSize(11);
      doc.setTextColor(51, 65, 85);

      if (beforeScore !== null) {
        doc.text(`Before Encryption: ${beforeScore}/100`, 20, y);
        y += 6;
      }

      if (afterScore !== null) {
        doc.setFont("helvetica", "bold");
        doc.text(`After Encryption: ${afterScore}/100`, 20, y);
        doc.setFont("helvetica", "normal");
        y += 6;
      }

      if (beforeScore !== null && afterScore !== null) {
        const improvement = afterScore - beforeScore;
        const improvementText = `Security Improvement: ${improvement >= 0 ? "+" : ""}${improvement} points`;
        doc.setFont("helvetica", "bold");
        doc.setTextColor(22, 163, 74);
        doc.text(improvementText, 20, y);
        doc.setFont("helvetica", "normal");
        doc.setTextColor(51, 65, 85);
        y += 10;
      } else {
        y += 6;
      }

      doc.setFont("helvetica", "bold");
      doc.setFontSize(14);
      doc.setTextColor(30, 41, 59);
      doc.text("Entropy Analysis", 14, y);
      y += 8;

      doc.setFont("helvetica", "normal");
      doc.setFontSize(10);
      doc.setTextColor(71, 85, 105);

      if (plainEntropy !== null) {
        doc.text(
          `Plaintext Entropy: ${plainEntropy.toFixed(3)} bits/byte`,
          20,
          y,
        );
        y += 5;
        doc.setFontSize(9);
        doc.setTextColor(100, 116, 139);
        doc.text("(Lower values indicate structured data, 0-8 scale)", 20, y);
        y += 7;
        doc.setFontSize(10);
        doc.setTextColor(71, 85, 105);
      }

      if (cipherEntropy !== null) {
        doc.text(
          `Ciphertext Entropy: ${cipherEntropy.toFixed(3)} bits/byte`,
          20,
          y,
        );
        y += 5;
        doc.setFontSize(9);
        doc.setTextColor(100, 116, 139);
        doc.text("(Ideal encrypted data approaches 8.0 bits/byte)", 20, y);
        y += 10;
        doc.setFontSize(10);
        doc.setTextColor(71, 85, 105);
      }

      doc.setFont("helvetica", "bold");
      doc.setFontSize(14);
      doc.setTextColor(30, 41, 59);
      doc.text("Cryptographic Implementation", 14, y);
      y += 8;

      doc.setFont("helvetica", "normal");
      doc.setFontSize(10);
      doc.setTextColor(71, 85, 105);

      const cryptoDetails = [
        `Symmetric Encryption: AES-256-GCM`,
        `Key Exchange: RSA-OAEP (2048-bit)`,
        `Hash Algorithm: SHA-256`,
        `Authentication: GCM Tag (128-bit)`,
        `IV Generation: Cryptographically Random`,
      ];

      cryptoDetails.forEach((detail) => {
        doc.text(`• ${detail}`, 20, y);
        y += 5;
      });
      y += 5;

      doc.setFont("helvetica", "bold");
      doc.setFontSize(14);
      doc.setTextColor(30, 41, 59);
      doc.text("Security Policy", 14, y);
      y += 8;

      doc.setFont("helvetica", "normal");
      doc.setFontSize(10);
      doc.setTextColor(71, 85, 105);

      const hasExpiry = metrics.policy && metrics.policy.expiryTime;
      const hasDownloadLimit = metrics.policy && metrics.policy.maxDecryptCount;

      doc.text(`File Expiration: ${hasExpiry ? "Enabled" : "Not Set"}`, 20, y);
      y += 5;
      doc.text(
        `Download Limit: ${hasDownloadLimit ? metrics.policy.maxDecryptCount + " downloads" : "Unlimited"}`,
        20,
        y,
      );
      y += 5;

      if (typeof metrics.sizeOverheadPercent === "number") {
        doc.text(
          `Encryption Overhead: ${metrics.sizeOverheadPercent.toFixed(2)}%`,
          20,
          y,
        );
        y += 8;
      } else {
        y += 5;
      }

      if (metrics.recommendations && metrics.recommendations.length > 0) {
        doc.setFont("helvetica", "bold");
        doc.setFontSize(14);
        doc.setTextColor(30, 41, 59);
        doc.text("Recommendations", 14, y);
        y += 8;

        doc.setFont("helvetica", "normal");
        doc.setFontSize(9);
        doc.setTextColor(71, 85, 105);

        metrics.recommendations.forEach((rec) => {
          const lines = doc.splitTextToSize(`• ${rec}`, 170);
          doc.text(lines, 20, y);
          y += lines.length * 5;
          y += 2;
        });
      }

      y = 280;
      doc.setFontSize(8);
      doc.setTextColor(148, 163, 184);
      doc.text(
        "Generated by Secure File Share - End-to-End Encryption Platform",
        14,
        y,
      );

      const safeName =
        (fileName || "encryption_report")
          .replace(/[^\w\-\.]+/g, "_")
          .substring(0, 100) + "_report.pdf";

      console.log("💾 Saving PDF as:", safeName);
      doc.save(safeName);
      console.log("✅ PDF generated successfully!");
    } catch (error) {
      console.error("❌ PDF generation error:", error);
      alert(`Failed to generate PDF report: ${error.message}`);
    }
  }

  // --- Metrics overlay UI (receiver) ---

  function generateBreakdownHTML(breakdown) {
    const items = [
      { key: "rsaKey", label: "RSA Key (2048-bit)" },
      { key: "aesKey", label: "AES Key (256-bit)" },
      { key: "algorithm", label: "AES-GCM Encryption" },
      { key: "keyExchange", label: "RSA-OAEP Key Exchange" },
      { key: "hash", label: "SHA-256 Hashing" },
      { key: "authEncryption", label: "Authenticated Encryption" },
      { key: "ivQuality", label: "Random IV Generation" },
      { key: "cipherEntropy", label: "Ciphertext Entropy" },
      { key: "expiry", label: "File Expiry Policy" },
      { key: "downloadLimit", label: "Download Limit" },
      { key: "e2ee", label: "End-to-End Encryption" },
    ];

    return items
      .map((item) => {
        const data = breakdown[item.key];
        if (!data) return "";

        let badgeClass = "badge-enabled";
        if (data.status === "excellent") badgeClass = "badge-excellent";
        if (
          data.status === "warning" ||
          data.status === "low" ||
          data.status === "fair"
        )
          badgeClass = "badge-warning";

        const badgeText =
          data.status === "excellent"
            ? "✓ Excellent"
            : data.status === "enabled"
              ? "✓ Enabled"
              : "⚠ Check";

        const extra =
          item.key === "cipherEntropy" &&
          typeof data.entropyBitsPerByte === "number"
            ? `<span style="font-size: 12px; color:#6b7280;">
                 (${data.entropyBitsPerByte.toFixed(3)} bits/byte)
               </span>`
            : "";

        return `
        <div class="breakdown-item">
          <span class="breakdown-label">${item.label}</span>
          <div class="breakdown-score">
            <span style="color: #16a34a; font-weight: 600;">${data.score}/${data.max}</span>
            ${extra}
            <span class="breakdown-badge ${badgeClass}">${badgeText}</span>
          </div>
        </div>
      `;
      })
      .join("");
  }

  function displayEncryptionMetrics(metrics) {
    const metricsContainer = document.createElement("div");
    metricsContainer.id = "encryption-metrics-dashboard";

    const cipherEntropy =
      typeof metrics.cipherEntropyBitsPerByte === "number"
        ? metrics.cipherEntropyBitsPerByte
        : null;
    const plainEntropy =
      typeof metrics.plaintextEntropyBitsPerByte === "number"
        ? metrics.plaintextEntropyBitsPerByte
        : null;

    const entropyLine =
      typeof cipherEntropy === "number"
        ? `<p class="entropy-line">
             Ciphertext entropy: <strong>${cipherEntropy.toFixed(
               3,
             )} bits/byte</strong> (ideal ≈ 8.0)
           </p>`
        : "";

    const beforeScore =
      typeof metrics.beforeScore === "number" ? metrics.beforeScore : null;
    const afterScore =
      typeof metrics.afterScore === "number"
        ? metrics.afterScore
        : (metrics.score ?? null);

    const improvement =
      beforeScore !== null && afterScore !== null
        ? afterScore - beforeScore
        : null;

    metricsContainer.innerHTML = `
      <div class="metrics-card">
        <div class="metrics-header">
          <h3>🔐 Encryption Effectiveness Report</h3>
          ${entropyLine}
        </div>
        
        <div class="score-display">
          <div class="score-circle" style="background: conic-gradient(${
            metrics.ratingColor || "#16a34a"
          } ${metrics.percentage ?? afterScore ?? 0}%, #e5e7eb ${
            metrics.percentage ?? afterScore ?? 0
          }%);">
            <div class="score-inner">
              <div class="score-number">${afterScore ?? "?"}</div>
              <div class="score-max">/ 100</div>
            </div>
          </div>
          <div class="score-info">
            <div class="score-rating" style="color: ${
              metrics.ratingColor || "#16a34a"
            }">${metrics.rating || "Overall Score"}</div>
            <div class="score-percentage">${
              metrics.percentage ?? afterScore ?? 0
            }% Security Score</div>
            ${
              beforeScore !== null && afterScore !== null
                ? `<div class="score-before-after">
                     <div>Before: <strong>${beforeScore}/100</strong></div>
                     <div>After: <strong>${afterScore}/100</strong></div>
                     <div>Improvement: <strong>${
                       improvement >= 0 ? "+" : ""
                     }${improvement}</strong> points</div>
                   </div>`
                : ""
            }
          </div>
        </div>

        <div class="metrics-breakdown">
          <h4>Security Features</h4>
          ${generateBreakdownHTML(metrics.breakdown || {})}
        </div>

        ${
          metrics.recommendations && metrics.recommendations.length > 0
            ? `
          <div class="metrics-recommendations">
            <h4>💡 Recommendations</h4>
            ${metrics.recommendations.map((rec) => `<p>• ${rec}</p>`).join("")}
          </div>
        `
            : ""
        }

        ${
          typeof plainEntropy === "number"
            ? `
          <div class="metrics-entropy-extra">
            <h4>Before vs After Entropy</h4>
            <p>Before (plaintext): <strong>${plainEntropy.toFixed(
              3,
            )}</strong> bits/byte</p>
            <p>After (ciphertext): <strong>${cipherEntropy.toFixed(
              3,
            )}</strong> bits/byte</p>
          </div>
        `
            : ""
        }

        <div class="metrics-actions">
          <button type="button" id="download-encryption-report-btn" class="download-report-btn">
            ⬇ Download PDF Report
          </button>
          <button type="button" class="close-metrics-btn">
            Close Report
          </button>
        </div>
      </div>
    `;

    if (!document.getElementById("encryption-metrics-styles")) {
      const style = document.createElement("style");
      style.id = "encryption-metrics-styles";
      style.textContent = `
        #encryption-metrics-dashboard {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.8);
          display: flex;
          align-items: center;
          justify-content: center;
          z-index: 10000;
          animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
          from { opacity: 0; }
          to { opacity: 1; }
        }

        .metrics-card {
          background: white;
          border-radius: 16px;
          padding: 32px;
          max-width: 650px;
          width: 90%;
          max-height: 90vh;
          overflow-y: auto;
          box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
          animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
          from { transform: translateY(20px); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
        }

        .metrics-header h3 {
          margin: 0 0 8px 0;
          font-size: 24px;
          color: #1f2937;
          text-align: center;
        }

        .metrics-header .entropy-line {
          margin: 4px 0 16px;
          font-size: 14px;
          color: #4b5563;
          text-align: center;
        }

        .score-display {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 32px;
          margin-bottom: 32px;
          padding: 24px;
          background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
          border-radius: 12px;
        }

        .score-circle {
          width: 140px;
          height: 140px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          position: relative;
        }

        .score-inner {
          width: 110px;
          height: 110px;
          border-radius: 50%;
          background: white;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
        }

        .score-number {
          font-size: 36px;
          font-weight: 700;
          color: #1f2937;
        }

        .score-max {
          font-size: 14px;
          color: #6b7280;
        }

        .score-info {
          text-align: left;
        }

        .score-rating {
          font-size: 24px;
          font-weight: 700;
          margin-bottom: 4px;
        }

        .score-percentage {
          font-size: 16px;
          color: #6b7280;
        }

        .score-before-after {
          margin-top: 8px;
          font-size: 13px;
          color: #4b5563;
        }

        .metrics-breakdown {
          margin-bottom: 24px;
        }

        .metrics-breakdown h4 {
          font-size: 18px;
          margin-bottom: 16px;
          color: #1f2937;
        }

        .breakdown-item {
          display: flex;
          align-items: center;
          justify-content: space-between;
          padding: 12px;
          background: #f8f9fa;
          border-radius: 8px;
          margin-bottom: 8px;
        }

        .breakdown-label {
          font-weight: 500;
          color: #4b5563;
        }

        .breakdown-score {
          display: flex;
          align-items: center;
          gap: 8px;
        }

        .breakdown-badge {
          padding: 4px 12px;
          border-radius: 12px;
          font-size: 12px;
          font-weight: 600;
          text-transform: uppercase;
        }

        .badge-excellent {
          background: #d1fae5;
          color: #065f46;
        }

        .badge-enabled {
          background: #dbeafe;
          color: #1e40af;
        }

        .badge-warning {
          background: #fef3c7;
          color: #92400e;
        }

        .metrics-recommendations {
          background: #fffbeb;
          border-left: 4px solid #f59e0b;
          padding: 16px;
          border-radius: 8px;
          margin-bottom: 24px;
        }

        .metrics-recommendations h4 {
          font-size: 16px;
          margin: 0 0 12px 0;
          color: #92400e;
        }

        .metrics-recommendations p {
          margin: 8px 0;
          color: #78350f;
          font-size: 14px;
        }

        .metrics-entropy-extra {
          background: #f9fafb;
          border-radius: 8px;
          padding: 12px 16px;
          margin-bottom: 16px;
          font-size: 13px;
          color: #374151;
        }

        .metrics-actions {
          display: flex;
          gap: 12px;
          margin-top: 8px;
        }

        .metrics-actions button {
          flex: 1;
        }

        .close-metrics-btn,
        .download-report-btn {
          padding: 12px 16px;
          border-radius: 8px;
          border: none;
          font-size: 15px;
          font-weight: 600;
          cursor: pointer;
          transition: opacity 0.2s;
        }

        .close-metrics-btn {
          background: #e5e7eb;
          color: #111827;
        }

        .download-report-btn {
          background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
          color: white;
        }

        .close-metrics-btn:hover,
        .download-report-btn:hover {
          opacity: 0.9;
        }
      `;
      document.head.appendChild(style);
    }

    document.body.appendChild(metricsContainer);

    const closeBtn = metricsContainer.querySelector(".close-metrics-btn");
    if (closeBtn) {
      closeBtn.addEventListener("click", () => {
        metricsContainer.remove();
      });
    }

    const dlBtn = metricsContainer.querySelector(
      "#download-encryption-report-btn",
    );
    if (dlBtn) {
      dlBtn.addEventListener("click", () => {
        try {
          downloadPdfReport(metrics);
        } catch (err) {
          console.error("Failed to download encryption report PDF:", err);
          alert("Failed to generate PDF report.");
        }
      });
    }
  }

  // --- URL parsing ---
  const urlParams = new URLSearchParams(window.location.search);
  const fileIdParam = urlParams.get("fileId");

  let pathFileId = null;
  const segments = window.location.pathname.split("/").filter(Boolean);
  const lastSeg = segments[segments.length - 1] || "";

  if (/^[0-9a-fA-F]{16,64}$/.test(lastSeg)) {
    pathFileId = lastSeg;
  }

  const initialFileId = fileIdParam || pathFileId || null;
  currentSelectedFileId = initialFileId;

  // --- Filter and render files ---

  function applyFilterStateToUI() {
    const searchInput = $("#search-input");
    const typeFilter = $("#type-filter");
    const sizeFilter = $("#size-filter");
    const dateInput = $("#date-filter");

    if (searchInput) searchInput.value = filterState.search || "";
    if (typeFilter) typeFilter.value = filterState.type || "all";
    if (sizeFilter) sizeFilter.value = filterState.size || "all";
    if (dateInput) dateInput.value = filterState.date || "";

    if (lastFocusedFilterId) {
      const el = document.getElementById(lastFocusedFilterId);
      if (el) {
        el.focus();

        if (el.tagName === "INPUT" && typeof lastCaretPos === "number") {
          try {
            const pos = Math.min(lastCaretPos, (el.value || "").length);
            el.setSelectionRange(pos, pos);
          } catch (_) {}
        }
      }
    }
  }

  function filterAndRenderFiles() {
    const searchInput = $("#search-input");
    const typeFilter = $("#type-filter");
    const sizeFilter = $("#size-filter");
    const dateInput = $("#date-filter");

    if (!typeFilter || !sizeFilter) return;

    const searchTerm = (searchInput?.value || "").toLowerCase().trim();
    const selectedType = typeFilter.value || "all";
    const selectedSize = sizeFilter.value || "all";
    const selectedDate = dateInput ? dateInput.value : "";

    filterState.search = searchTerm;
    filterState.type = selectedType;
    filterState.size = selectedSize;
    filterState.date = selectedDate;

    const filtered = allFiles.filter((f) => {
      if (searchTerm) {
        const fileName = (f.filename || f.fileName || "").toLowerCase();
        const senderName = getPlainSenderName(f.sender).toLowerCase();

        if (
          !fileName.includes(searchTerm) &&
          !senderName.includes(searchTerm)
        ) {
          return false;
        }
      }

      if (selectedDate) {
        const fileDate = new Date(f.createdAt);
        fileDate.setHours(0, 0, 0, 0);

        const filterDate = new Date(selectedDate);
        filterDate.setHours(0, 0, 0, 0);

        if (fileDate.getTime() !== filterDate.getTime()) return false;
      }

      if (selectedType !== "all") {
        const category = getFileCategory(f.mimeType);
        if (category !== selectedType) return false;
      }

      const fileSize = f.sizeBytes || f.fileSize || 0;
      if (selectedSize !== "all") {
        if (selectedSize === "small" && fileSize >= 1048576) return false;
        if (
          selectedSize === "medium" &&
          (fileSize < 1048576 || fileSize >= 10485760)
        )
          return false;
        if (selectedSize === "large" && fileSize < 10485760) return false;
      }

      return true;
    });

    renderFileTable(filtered);
  }

  function clearAllFilters() {
    filterState.search = "";
    filterState.type = "all";
    filterState.size = "all";
    filterState.date = "";

    renderFileTable(allFiles);
  }

  function renderFileTable(files) {
    if (!fileInfoContainer) return;

    if (files.length === 0) {
      fileInfoContainer.innerHTML = `
        <div class="file-card">
          <div class="search-filters">
            <div class="search-bar">
              <input type="text" id="search-input" placeholder="🔍 Search by filename or sender..." />
              <button id="clear-filters-btn" class="clear-filters-btn" title="Clear all filters">✕ Clear Filters</button>
            </div>
            <div class="filters">
              <input type="date" id="date-filter" placeholder="Filter by date" />
              <select id="type-filter">
                <option value="all">All Types</option>
                <option value="image">Images</option>
                <option value="video">Videos</option>
                <option value="audio">Audio</option>
                <option value="pdf">PDF</option>
                <option value="document">Documents</option>
                <option value="spreadsheet">Spreadsheets</option>
                <option value="archive">Archives</option>
                <option value="other">Other</option>
              </select>
              <select id="size-filter">
                <option value="all">All Sizes</option>
                <option value="small">Small (&lt; 1MB)</option>
                <option value="medium">Medium (1-10MB)</option>
                <option value="large">Large (&gt; 10MB)</option>
              </select>
            </div>
          </div>

          <div class="empty-state">
            <div class="empty-icon">🔭</div>
            <h2 style="color: #64748b; font-size: 20px; margin-bottom: 8px;">No files found</h2>
            <p style="color: #94a3b8;">Try adjusting your search or filters.</p>
          </div>
        </div>
      `;

      applyFilterStateToUI();
      attachFilterListeners();
      return;
    }

    const rowsHtml = files
      .map((f) => {
        const senderName = getPlainSenderName(f.sender);
        const fileIcon = getFileIcon(f.mimeType);

        return `
          <tr>
            <td>
              <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;">${fileIcon}</span>
                <strong>${escapeHtml(f.filename || f.fileName || "")}</strong>
              </div>
            </td>
            <td>${escapeHtml(f.mimeType || "Unknown")}</td>
            <td>${formatFileSize(f.sizeBytes || f.fileSize || 0)}</td>
            <td>${escapeHtml(senderName)}</td>
            <td>${escapeHtml(new Date(f.createdAt).toLocaleDateString())}</td>
            <td>${escapeHtml(
              f.expiresAt
                ? new Date(f.expiresAt).toLocaleDateString()
                : "Never",
            )}</td>
            <td style="text-align: center;">
              ${
                f.remainingDecrypts != null
                  ? `<span style="color: ${
                      f.remainingDecrypts > 0 ? "#16a34a" : "#dc2626"
                    }; font-weight: 600;">${f.remainingDecrypts}</span>`
                  : '<span style="color: #64748b;">∞</span>'
              }
            </td>
            <td>
              <button class="file-select-btn" data-fileid="${encodeURIComponent(
                f.fileId,
              )}">
                Select
              </button>
            </td>
          </tr>
        `;
      })
      .join("");

    fileInfoContainer.innerHTML = `
      <div class="file-card">
        <h2>📥 Files Shared With You</h2>
        <p style="color: #64748b; margin-bottom: 20px;">Select a file to view details and download</p>

        <div class="search-filters">
          <div class="search-bar">
            <input type="text" id="search-input" placeholder="🔍 Search by filename or sender..." />
            <button id="clear-filters-btn" class="clear-filters-btn" title="Clear all filters">✕ Clear Filters</button>
          </div>
          <div class="filters">
            <input type="date" id="date-filter" placeholder="Filter by date" />
            <select id="type-filter">
              <option value="all">All Types</option>
              <option value="image">Images</option>
              <option value="video">Videos</option>
              <option value="audio">Audio</option>
              <option value="pdf">PDF</option>
              <option value="document">Documents</option>
              <option value="spreadsheet">Spreadsheets</option>
              <option value="archive">Archives</option>
              <option value="other">Other</option>
            </select>
            <select id="size-filter">
              <option value="all">All Sizes</option>
              <option value="small">Small (&lt; 1MB)</option>
              <option value="medium">Medium (1-10MB)</option>
              <option value="large">Large (&gt; 10MB)</option>
            </select>
          </div>
        </div>

        <div class="file-details">
          <table class="download-list-table">
            <thead>
              <tr>
                <th>File Name</th>
                <th>File Type</th>
                <th>File Size</th>
                <th>From</th>
                <th>Shared Date</th>
                <th>Expires On</th>
                <th style="text-align: center;">Downloads Left</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              ${rowsHtml}
            </tbody>
          </table>
        </div>
      </div>
    `;

    applyFilterStateToUI();
    attachFilterListeners();
    attachTableListeners();
  }

  function attachFilterListeners() {
    const searchInput = $("#search-input");
    const typeFilter = $("#type-filter");
    const sizeFilter = $("#size-filter");
    const dateInput = $("#date-filter");
    const clearBtn = $("#clear-filters-btn");

    if (searchInput) {
      searchInput.addEventListener("focus", () => {
        lastFocusedFilterId = "search-input";
      });

      searchInput.addEventListener("input", () => {
        lastFocusedFilterId = "search-input";
        lastCaretPos =
          searchInput.selectionStart ?? (searchInput.value || "").length;
        filterAndRenderFiles();
      });

      searchInput.addEventListener("keyup", () => {
        lastCaretPos =
          searchInput.selectionStart ?? (searchInput.value || "").length;
      });

      searchInput.addEventListener("click", () => {
        lastCaretPos =
          searchInput.selectionStart ?? (searchInput.value || "").length;
      });
    }

    if (typeFilter) {
      typeFilter.addEventListener("focus", () => {
        lastFocusedFilterId = "type-filter";
      });
      typeFilter.addEventListener("change", () => {
        lastFocusedFilterId = "type-filter";
        filterAndRenderFiles();
      });
    }

    if (sizeFilter) {
      sizeFilter.addEventListener("focus", () => {
        lastFocusedFilterId = "size-filter";
      });
      sizeFilter.addEventListener("change", () => {
        lastFocusedFilterId = "size-filter";
        filterAndRenderFiles();
      });
    }

    if (dateInput) {
      dateInput.addEventListener("focus", () => {
        lastFocusedFilterId = "date-filter";
      });
      dateInput.addEventListener("change", () => {
        lastFocusedFilterId = "date-filter";
        filterAndRenderFiles();
      });
    }

    if (clearBtn) {
      clearBtn.addEventListener("click", () => {
        lastFocusedFilterId = "search-input";
        lastCaretPos = 0;
        clearAllFilters();
      });
    }
  }

  function attachTableListeners() {
    const tbody = fileInfoContainer.querySelector("tbody");
    if (tbody) {
      tbody.addEventListener("click", (ev) => {
        const btn = ev.target.closest(".file-select-btn");
        if (!btn) return;

        const selectedId = btn.getAttribute("data-fileid");
        if (!selectedId) return;

        currentSelectedFileId = decodeURIComponent(selectedId);
        showMessage("Loading file details...", "info");
        loadFileInfo(currentSelectedFileId);
      });
    }
  }

  async function loadFileInfo(selectedFileId) {
    const fileId = selectedFileId || currentSelectedFileId;
    if (!fileId) {
      showMessage("No file selected.", "error");
      if (downloadBtn) downloadBtn.disabled = true;
      return;
    }

    currentSelectedFileId = fileId;
    currentMode = "single";
    downloadComplete = false;
    currentEncryptionMetrics = null;
    showBackButton();

    showProgress();
    updateProgress(10, "Loading file metadata...");

    try {
      const res = await fetch("api/get_file_info.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ fileId }),
      });

      const text = await res.text();
      console.log("get_file_info.php response:", text);

      let data;
      try {
        data = text ? JSON.parse(text) : null;
      } catch (err) {
        throw new Error("Invalid JSON from server.");
      }

      if (!data?.ok) {
        throw new Error(
          data?.message || `Failed to load file (HTTP ${res.status})`,
        );
      }

      const file = data.file;
      currentFile = file;

      hideProgress();

      const fileIcon = getFileIcon(file.mimeType);
      const senderDisplay = formatSenderName(file.sender);

      if (fileInfoContainer) {
        fileInfoContainer.innerHTML = `
          <div class="file-card">
            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
              <div class="file-icon">${fileIcon}</div>
              <div style="flex: 1;">
                <h2 style="margin: 0;">${escapeHtml(file.fileName)}</h2>
                <div class="security-badge" style="margin-top: 8px;">
                  <span>🔒</span> End-to-End Encrypted
                </div>
              </div>
            </div>
            <div class="file-details">
              <div class="detail-item">
                <div class="detail-label">From</div>
                <div class="detail-value">${senderDisplay}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">File Size</div>
                <div class="detail-value">${formatFileSize(file.fileSize)}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">File Type</div>
                <div class="detail-value">${escapeHtml(file.mimeType)}</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Expires</div>
                <div class="detail-value">${
                  file.expiryTime
                    ? escapeHtml(new Date(file.expiryTime).toLocaleDateString())
                    : "Never"
                }</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Downloads</div>
                <div class="detail-value">${
                  file.decryptCount
                }${file.maxDecryptCount ? ` / ${file.maxDecryptCount}` : ""} ${
                  file.remainingDecrypts != null
                    ? `(${file.remainingDecrypts} left)`
                    : ""
                }</div>
              </div>
              <div class="detail-item">
                <div class="detail-label">Uploaded</div>
                <div class="detail-value">${escapeHtml(
                  new Date(file.uploadedAt).toLocaleDateString(),
                )}</div>
              </div>
            </div>
          </div>
        `;
      }

      if (downloadBtn) {
        downloadBtn.disabled = false;
        downloadBtn.innerHTML = "<span>🔓</span> Decrypt & Download File";
      }

      showMessage(
        'File ready. Click "Decrypt & Download File" to proceed.',
        "success",
      );
    } catch (err) {
      console.error("Load error:", err);
      hideProgress();
      showMessage(err.message || "Failed to load file.", "error");
      if (downloadBtn) downloadBtn.disabled = true;
    }
  }

  // --- Download button handler ---
  if (downloadBtn) {
    downloadBtn.addEventListener("click", async () => {
      if (downloadComplete) {
        loadInboxList();
        return;
      }

      if (!currentFile) {
        showMessage("File metadata not loaded.", "error");
        return;
      }

      try {
        showProgress();
        updateProgress(5, "Fetching encrypted file...");

        console.log('📥 Fetching file:', currentFile.fileId);

        const res = await fetch("api/download.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "same-origin",
          body: JSON.stringify({ fileId: currentFile.fileId }),
        });

        const text = await res.text();
        console.log("download.php raw response:", text.substring(0, 500));

        let data;
        try {
          data = text ? JSON.parse(text) : null;
        } catch (err) {
          console.error("JSON parse error. Raw text:", text);
          throw new Error("Invalid JSON from server.");
        }

        if (!data?.ok) {
          throw new Error(
            data?.message || `Failed to download file (HTTP ${res.status})`,
          );
        }

        console.log('Server response structure:', {
          hasFile: !!data.file,
          hasEncryptedData: !!data.file?.encryptedData,
          hasEncFileKey: !!data.file?.encFileKey,
          hasHashEnc: !!data.file?.hashEnc,
          encryptedDataType: typeof data.file?.encryptedData,
          encryptedDataLength: data.file?.encryptedData?.length
        });

        currentFile.encryptedData = data.file.encryptedData;
        currentFile.encFileKey = data.file.encFileKey;
        currentFile.hashEnc = data.file.hashEnc;
        currentFile.sender.publicKey = data.file.sender.publicKey;

        if (!currentFile.encryptedData) {
          throw new Error("Server did not provide encrypted data");
        }
        if (!currentFile.encFileKey) {
          throw new Error("Server did not provide encrypted file key");
        }
        if (!currentFile.hashEnc) {
          throw new Error("Server did not provide encrypted hash");
        }

        updateProgress(10, "Preparing decryption...");

        const privateKey = await getPrivateKeyFromSession();

        const decryptedBuf = await decryptFile(
          currentFile.encryptedData,
          currentFile.encFileKey,
          currentFile.hashEnc,
          currentFile.policy,
          privateKey,
        );

        try {
          const plainBytes = new Uint8Array(decryptedBuf);
          const plainEntropyInfo = computeEntropy(plainBytes);

          let cipherEntropyInfo = currentFile._cipherEntropyInfo || null;

          if (!cipherEntropyInfo && currentFile.encryptedData) {
            const encBytes = Uint8Array.from(
              atob(currentFile.encryptedData),
              (c) => c.charCodeAt(0),
            );
            const cipherBody = encBytes.slice(12);
            cipherEntropyInfo = computeEntropy(cipherBody);
          }

          if (currentFile.encryptionMetrics) {
            currentEncryptionMetrics = {
              ...currentFile.encryptionMetrics,
              plaintextEntropyBitsPerByte: plainEntropyInfo?.entropyBitsPerByte,
              plaintextSampleSize: plainEntropyInfo?.sampleSize,
              cipherEntropyBitsPerByte: cipherEntropyInfo?.entropyBitsPerByte,
              cipherSampleSize: cipherEntropyInfo?.sampleSize,
              fileName: currentFile.fileName,
              fileSize: currentFile.fileSize,
              policy: currentFile.policy || {},
            };
          } else if (cipherEntropyInfo) {
            currentEncryptionMetrics = buildEncryptionMetricsForReceiver(
              currentFile,
              plainEntropyInfo,
              cipherEntropyInfo,
            );
          }
        } catch (metricsErr) {
          console.warn("Failed to compute encryption metrics:", metricsErr);
        }

        const blob = new Blob([decryptedBuf], {
          type: currentFile.mimeType || "application/octet-stream",
        });

        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = currentFile.fileName || "downloaded_file";
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);

        hideProgress();
        showMessage(
          "File decrypted and downloaded successfully! 🎉",
          "success",
        );
        showSuccessState();
      } catch (err) {
        console.error("❌ Decrypt error:", err);
        console.error("Error stack:", err.stack);
        hideProgress();
        
        let errorMessage = err.message || "Failed to decrypt file.";
        
        if (errorMessage.includes("Base64")) {
          errorMessage += "\n\nThe encrypted data received from the server appears to be corrupted. Please try again or contact support.";
        }
        
        showMessage(errorMessage, "error");
      }
    });
  }

  // --- Load inbox list ---
  async function loadInboxList() {
    if (!fileInfoContainer) return;

    currentMode = "list";
    downloadComplete = false;
    currentEncryptionMetrics = null;
    hideBackButton();

    showProgress();
    updateProgress(10, "Loading files shared with you...");

    try {
      const res = await fetch("api/inbox_list.php", {
        method: "GET",
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });

      if (!res.ok) {
        throw new Error(`Server returned ${res.status}: ${res.statusText}`);
      }

      const text = await res.text();
      console.log("inbox_list.php response:", text);

      let data;
      try {
        data = text ? JSON.parse(text) : null;
      } catch (err) {
        console.error("JSON parse error:", err);
        throw new Error("Invalid JSON from server (inbox).");
      }

      if (!data?.ok) {
        throw new Error(
          data?.message || `Failed to load inbox (HTTP ${res.status})`,
        );
      }

      allFiles = data.files || [];
      hideProgress();

      if (allFiles.length === 0) {
        fileInfoContainer.innerHTML = `
          <div class="file-card">
            <div class="empty-state">
              <div class="empty-icon">🔭</div>
              <h2 style="color: #64748b; font-size: 20px; margin-bottom: 8px;">No files yet</h2>
              <p style="color: #94a3b8;">You don't have any shared files to download right now.</p>
            </div>
          </div>
        `;
        if (downloadBtn) downloadBtn.disabled = true;
        return;
      }

      renderFileTable(allFiles);

      if (downloadBtn) {
        downloadBtn.disabled = true;
      }

      showMessage(
        `Found ${allFiles.length} shared file${
          allFiles.length !== 1 ? "s" : ""
        }`,
        "success",
      );
    } catch (err) {
      console.error("Inbox load error:", err);
      hideProgress();
      showMessage(err.message || "Failed to load your shared files.", "error");
      if (downloadBtn) downloadBtn.disabled = true;
    }
  }

  // --- Show success state after download ---
  function showSuccessState() {
    if (!fileInfoContainer || !currentFile) return;

    const fileIcon = getFileIcon(currentFile.mimeType);

    fileInfoContainer.innerHTML = `
      <div class="file-card">
        <div class="success-state">
          <div class="success-icon">✓</div>
          <h2 style="color: #16a34a; margin-bottom: 12px;">Download Complete!</h2>
          <p style="color: #64748b; margin-bottom: 24px; font-size: 15px;">
            <strong>${escapeHtml(
              currentFile.fileName,
            )}</strong> has been decrypted and saved to your device.
          </p>
          <div style="display: inline-flex; align-items: center; gap: 12px; background: #f0fdf4; padding: 16px 24px; border-radius: 12px; margin-top: 16px;">
            <span style="font-size: 32px;">${fileIcon}</span>
            <div style="text-align: left;">
              <div style="font-size: 14px; color: #166534; font-weight: 600;">${formatFileSize(
                currentFile.fileSize,
              )}</div>
              <div style="font-size: 12px; color: #22c55e;">Verified & Decrypted</div>
            </div>
          </div>

          <div class="encryption-report-preview" style="margin-top: 24px;">
            <button type="button" id="view-encryption-report-btn" class="view-report-btn">
              📊 View Encryption Effectiveness Report
            </button>
          </div>
        </div>
      </div>
    `;

    const viewReportBtn = document.getElementById("view-encryption-report-btn");
    if (viewReportBtn) {
      viewReportBtn.addEventListener("click", () => {
        if (!currentEncryptionMetrics) {
          showMessage(
            "Encryption metrics are not available for this file.",
            "error",
          );
        } else {
          displayEncryptionMetrics(currentEncryptionMetrics);
        }
      });
    }

    downloadComplete = true;

    if (downloadBtn) {
      downloadBtn.innerHTML = "<span>←</span> Back to File List";
      downloadBtn.disabled = false;
    }
  }

  // --- Back button handler ---
  if (backButton) {
    backButton.addEventListener("click", () => {
      loadInboxList();
    });
  }

  console.log(
    "Download page initialized (Enhanced E2EE with Search/Filter + Metrics).",
  );

  // --- Initialize ---
  if (initialFileId) {
    loadFileInfo(initialFileId);
  } else {
    loadInboxList();
  }
})();