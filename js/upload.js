(() => {
  const receiverUsername = document.getElementById("receiverUsername");
  const emailDisplay = document.getElementById("emailDisplay");
  const fileUploadArea = document.getElementById("fileUploadArea");
  const fileInput = document.getElementById("fileInput");
  const fileInfo = document.getElementById("fileInfo");
  const fileName = document.getElementById("fileName");
  const fileSize = document.getElementById("fileSize");
  const removeFile = document.getElementById("removeFile");
  const expiryTimeInput = document.getElementById("expiryTime");
  const maxDecryptCountInput = document.getElementById("maxDecryptCount");
  const encryptBtn = document.getElementById("encryptBtn");
  const uploadForm = document.getElementById("uploadForm");
  const progressContainer = document.getElementById("progressContainer");
  const successMessage = document.getElementById("successMessage");
  const errorMessage = document.getElementById("errorMessage");
  const errorText = document.getElementById("errorText");

  let selectedFile = null;
  let users = [];
  let selectedRecipients = [];
  let cachedPrivateKey = null;
  let encryptionMetrics = {};

  const PROJECT_BASE = "/FinalYearProject/";
  const API_BASE = PROJECT_BASE + "api/";

  console.log("[upload] Multi-recipient hybrid encryption script loaded");

  if (!uploadForm) {
    console.log("[upload] uploadForm not found; skipping initialization.");
    return;
  }

  const unlockedKeyJwkStr = sessionStorage.getItem("unlockedPrivateKey");
  if (!unlockedKeyJwkStr) {
    if (
      confirm(
        "Your encryption keys are not unlocked. Please sign in again.\n\nClick OK to go to login page."
      )
    ) {
      sessionStorage.clear();
      window.location.href = "index.html";
    } else {
      window.location.href = "dashboard_user.html";
    }
    return;
  }

  async function fetchUsersFromDatabase() {
    try {
      const url = `${API_BASE}upload.php?action=users`;
      const response = await fetch(url);
      if (!response.ok)
        throw new Error(`HTTP error! status: ${response.status}`);
      const data = await response.json();
      if (!data.success)
        throw new Error(data.message || "Failed to load users");
      return data.users;
    } catch (error) {
      console.error("Error fetching users:", error);
      throw error;
    }
  }

  async function loadUsers() {
    try {
      users = await fetchUsersFromDatabase();
      receiverUsername.innerHTML =
        '<option value="">-- Select recipients --</option>';
      users.forEach((user) => {
        const option = document.createElement("option");
        option.value = user.username;
        option.textContent = `${user.username} (${user.user_fullname})`;
        option.dataset.email = user.user_email;
        option.dataset.userId = user.user_id;
        option.dataset.fullname = user.user_fullname;
        receiverUsername.appendChild(option);
      });
      console.log("✓ Loaded", users.length, "users");
    } catch (error) {
      receiverUsername.innerHTML =
        '<option value="">Error loading users</option>';
      showError("Failed to load users: " + error.message);
    }
  }

  function createRecipientChip(recipient) {
    const chip = document.createElement("div");
    chip.className = "recipient-chip";
    chip.dataset.email = recipient.email;
    chip.innerHTML = `
      <span class="chip-name">${recipient.fullname || recipient.username}</span>
      <span class="chip-email">${recipient.email}</span>
      <button type="button" class="chip-remove" aria-label="Remove recipient">×</button>
    `;

    chip.querySelector(".chip-remove").addEventListener("click", () => {
      selectedRecipients = selectedRecipients.filter(
        (r) => r.email !== recipient.email
      );
      chip.remove();
      updateEmailDisplay();
      updateEncryptButton();
    });

    return chip;
  }

  function updateEmailDisplay() {
    let container = document.getElementById("selectedRecipientsContainer");
    if (!container) {
      container = document.createElement("div");
      container.id = "selectedRecipientsContainer";
      container.className = "recipients-container";
      emailDisplay.appendChild(container);
    }

    container.innerHTML = "";

    if (selectedRecipients.length > 0) {
      emailDisplay.classList.add("active");
      selectedRecipients.forEach((recipient) => {
        container.appendChild(createRecipientChip(recipient));
      });
    } else {
      emailDisplay.classList.remove("active");
    }
  }

  receiverUsername.addEventListener("change", (e) => {
    const selectedOption = e.target.options[e.target.selectedIndex];
    if (selectedOption.dataset.email) {
      const email = selectedOption.dataset.email;

      if (selectedRecipients.some((r) => r.email === email)) {
        showError("This recipient has already been added.");
        receiverUsername.value = "";
        return;
      }

      const recipient = {
        userId: selectedOption.dataset.userId,
        username: selectedOption.value,
        email: email,
        fullname: selectedOption.dataset.fullname,
      };

      selectedRecipients.push(recipient);
      updateEmailDisplay();
      updateEncryptButton();

      receiverUsername.value = "";
    }
  });

  if (fileUploadArea && fileInput) {
    fileUploadArea.addEventListener("click", (e) => {
      if (e.target === fileInput) return;
      e.preventDefault();
      fileInput.click();
    });
  }

  fileUploadArea.addEventListener("dragover", (e) => {
    e.preventDefault();
    fileUploadArea.classList.add("dragover");
  });

  fileUploadArea.addEventListener("dragleave", () => {
    fileUploadArea.classList.remove("dragover");
  });

  fileUploadArea.addEventListener("drop", (e) => {
    e.preventDefault();
    fileUploadArea.classList.remove("dragover");
    const files = e.dataTransfer.files;
    if (files.length > 0) handleFileSelect(files[0]);
  });

  fileInput.addEventListener("change", (e) => {
    if (e.target.files.length > 0) handleFileSelect(e.target.files[0]);
  });

  function handleFileSelect(file) {
    const maxSize = 100 * 1024 * 1024;
    if (file.size > maxSize) {
      showError("File size exceeds 100MB limit. Please select a smaller file.");
      return;
    }

    if (errorMessage) errorMessage.classList.remove("active");
    selectedFile = file;
    fileName.textContent = file.name;
    fileSize.textContent = formatFileSize(file.size);

    const fileIcon = document.querySelector(".file-icon");
    const fileTypeElement = document.getElementById("fileType");
    const fileType = file.type;
    const fileExt = file.name.split(".").pop().toLowerCase();

    let typeLabel = "";
    if (fileType.startsWith("image/")) {
      fileIcon.textContent = "🖼️";
      typeLabel = "Image File";
    } else if (fileType.startsWith("video/")) {
      fileIcon.textContent = "🎬";
      typeLabel = "Video File";
    } else if (fileType.startsWith("audio/")) {
      fileIcon.textContent = "🎵";
      typeLabel = "Audio File";
    } else if (fileType === "application/pdf" || fileExt === "pdf") {
      fileIcon.textContent = "📕";
      typeLabel = "PDF Document";
    } else if (
      fileType.includes("word") ||
      fileExt === "doc" ||
      fileExt === "docx"
    ) {
      fileIcon.textContent = "📘";
      typeLabel = "Word Document";
    } else if (
      fileType.includes("excel") ||
      fileType.includes("spreadsheet") ||
      ["xls", "xlsx", "csv"].includes(fileExt)
    ) {
      fileIcon.textContent = "📊";
      typeLabel = "Spreadsheet";
    } else if (
      fileType.includes("presentation") ||
      fileExt === "ppt" ||
      fileExt === "pptx"
    ) {
      fileIcon.textContent = "📽️";
      typeLabel = "Presentation";
    } else if (["zip", "rar", "7z", "tar", "gz"].includes(fileExt)) {
      fileIcon.textContent = "📦";
      typeLabel = "Archive File";
    } else if (fileType.includes("text") || fileExt === "txt") {
      fileIcon.textContent = "📃";
      typeLabel = "Text File";
    } else {
      fileIcon.textContent = "📄";
      typeLabel = "Document";
    }

    if (fileTypeElement) fileTypeElement.textContent = typeLabel;
    fileInfo.classList.add("active");
    updateEncryptButton();
  }

  if (removeFile && fileInput && fileInfo) {
    removeFile.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      selectedFile = null;
      fileInput.value = "";
      if (fileName) fileName.textContent = "No file selected";
      if (fileSize) fileSize.textContent = "0 MB";
      const fileTypeElement = document.getElementById("fileType");
      if (fileTypeElement) fileTypeElement.textContent = "";
      fileInfo.classList.remove("active");
      updateEncryptButton();
    });
  }

  function updateEncryptButton() {
    const hasExpiryTime = expiryTimeInput.value && expiryTimeInput.value.trim() !== "";
    const hasDownloadLimit = maxDecryptCountInput.value && parseInt(maxDecryptCountInput.value) > 0;
    
    const isValid = selectedFile && 
                    selectedRecipients.length > 0 && 
                    hasExpiryTime && 
                    hasDownloadLimit;
    
    encryptBtn.disabled = !isValid;
    
    if (!selectedFile || selectedRecipients.length === 0) {
      encryptBtn.textContent = "🔒 Select Recipients & File";
    } else if (!hasExpiryTime || !hasDownloadLimit) {
      encryptBtn.textContent = "🔒 Set Expiry & Download Limit";
    } else {
      encryptBtn.textContent = `🔒 Encrypt & Send to ${
        selectedRecipients.length
      } Recipient${selectedRecipients.length > 1 ? "s" : ""}`;
    }
  }

  function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1048576) return (bytes / 1024).toFixed(2) + " KB";
    return (bytes / 1048576).toFixed(2) + " MB";
  }

  async function getPrivateKey() {
    if (cachedPrivateKey) return cachedPrivateKey;
    if (!window.crypto || !crypto.subtle) {
      throw new Error("This browser does not support required cryptography.");
    }

    const keyJwkStr = sessionStorage.getItem("unlockedPrivateKey");
    if (!keyJwkStr) {
      throw new Error(
        "Your encryption keys are not unlocked. Please sign in again."
      );
    }

    let keyJwk;
    try {
      keyJwk = JSON.parse(keyJwkStr);
    } catch (e) {
      console.error("Failed to parse unlockedPrivateKey JWK:", e);
      throw new Error(
        "Invalid encryption keys in session. Please log in again."
      );
    }

    try {
      cachedPrivateKey = await crypto.subtle.importKey(
        "jwk",
        keyJwk,
        { name: "RSA-OAEP", hash: "SHA-256" },
        true,
        ["decrypt"]
      );
      console.log("✓ Private key imported");
      return cachedPrivateKey;
    } catch (e) {
      console.error("Failed to import private key:", e);
      throw new Error(
        "Invalid encryption keys in session. Please log in again."
      );
    }
  }

  async function getReceiverPublicKey(email) {
    const res = await fetch(`${API_BASE}get_publicKey.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email }),
    });

    if (!res.ok)
      throw new Error(`get_publicKey.php returned HTTP ${res.status}`);
    const data = await res.json();
    if (!data.ok || !data.publicKeyJwk) {
      throw new Error(data.message || "Failed to get receiver's public key");
    }

    return {
      publicKeyJwk: data.publicKeyJwk,
      userId: data.userId,
      userName: data.userName,
    };
  }

  function computeEntropy(bytes, maxSample = 500000) {
    const len = bytes.length;
    if (len === 0) return { entropyBitsPerByte: 0, sampleSize: 0 };

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

  // ✅ APPROACH 2: Hybrid Encryption - Encrypt file ONCE, wrap key for each recipient
  async function encryptFileOnce(fileData) {
    console.log("  → Generating single AES-256 key for file encryption...");

    // Generate ONE AES key for the entire file
    const fileKey = await crypto.subtle.generateKey(
      { name: "AES-GCM", length: 256 },
      true,
      ["encrypt", "decrypt"]
    );

    // Generate ONE IV for this file
    const iv = crypto.getRandomValues(new Uint8Array(12));
    console.log("  → Encrypting file data ONCE with AES-GCM...");

    // Encrypt the file data ONCE
    const encryptedFile = await crypto.subtle.encrypt(
      { name: "AES-GCM", iv },
      fileKey,
      fileData
    );

    // Combine IV + encrypted data
    const fileEnc = new Uint8Array(iv.length + encryptedFile.byteLength);
    fileEnc.set(iv, 0);
    fileEnc.set(new Uint8Array(encryptedFile), iv.length);

    // Hash the encrypted data
    const hashBuffer = await crypto.subtle.digest("SHA-256", fileEnc);
    const hashArray = new Uint8Array(hashBuffer);

    // Encrypt hash with the same AES key
    const hashIv = crypto.getRandomValues(new Uint8Array(12));
    const hashEnc = await crypto.subtle.encrypt(
      { name: "AES-GCM", iv: hashIv },
      fileKey,
      hashArray
    );

    const hashEncFull = new Uint8Array(hashIv.length + hashEnc.byteLength);
    hashEncFull.set(hashIv, 0);
    hashEncFull.set(new Uint8Array(hashEnc), hashIv.length);

    console.log("  ✓ File encrypted ONCE:", fileEnc.length, "bytes");

    return {
      fileEnc: fileEnc,
      hashEnc: hashEncFull,
      fileKey: fileKey, // Return the key for wrapping
    };
  }

  // ✅ Wrap the AES key with recipient's RSA public key
  async function wrapKeyForRecipient(fileKey, receiverPublicKey, recipientEmail) {
    console.log(`  → Wrapping AES key for ${recipientEmail}...`);
    
    // Export the AES key
    const fileKeyJwk = await crypto.subtle.exportKey("jwk", fileKey);
    const fileKeyBytes = new TextEncoder().encode(JSON.stringify(fileKeyJwk));

    // Encrypt AES key with recipient's RSA public key
    const encFileKey = await crypto.subtle.encrypt(
      { name: "RSA-OAEP" },
      receiverPublicKey,
      fileKeyBytes
    );

    console.log(`  ✓ Key wrapped for ${recipientEmail}`);

    return btoa(String.fromCharCode(...new Uint8Array(encFileKey)));
  }

  function updateProgressStep(stepId, status, text) {
    const step = document.getElementById(stepId);
    if (!step) return;

    const stepText = step.querySelector("span");
    step.classList.remove("active", "completed", "error");

    if (status === "active") {
      step.classList.add("active");
    } else if (status === "completed") {
      step.classList.add("completed");
    } else if (status === "error") {
      step.classList.add("error");
    }

    if (text && stepText) {
      const originalText =
        stepText.getAttribute("data-original") || stepText.textContent;
      stepText.setAttribute("data-original", originalText);
      stepText.textContent = text;
    }
  }

  function showError(message) {
    if (!errorMessage || !errorText) {
      alert(message);
      return;
    }
    errorText.textContent = message;
    errorMessage.classList.add("active");
    setTimeout(() => errorMessage.classList.remove("active"), 5000);
  }

  function showSuccess(message) {
    if (!successMessage) {
      alert(message);
      return;
    }
    successMessage.textContent = "✓ " + message;
    successMessage.classList.add("active");
  }

  uploadForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    successMessage?.classList.remove("active");
    errorMessage?.classList.remove("active");
    
    // ✅ MANDATORY VALIDATION - Block upload if fields are missing
    if (!expiryTimeInput.value || expiryTimeInput.value.trim() === "") {
      showError("❌ Expiration date is required for security. Please set an expiry time.");
      encryptBtn.disabled = false;
      return;
    }
    
    if (!maxDecryptCountInput.value || maxDecryptCountInput.value.trim() === "") {
      showError("❌ Download limit is required for security. Please set a maximum download count.");
      encryptBtn.disabled = false;
      return;
    }
    
    const maxDecryptCount = parseInt(maxDecryptCountInput.value);
    if (isNaN(maxDecryptCount) || maxDecryptCount < 1) {
      showError("❌ Download limit must be at least 1.");
      encryptBtn.disabled = false;
      return;
    }
    
    progressContainer?.classList.add("active");
    encryptBtn.disabled = true;

    try {
      // Convert datetime-local to Unix timestamp
      const expiryDateTime = new Date(expiryTimeInput.value);
      if (isNaN(expiryDateTime.getTime())) {
        throw new Error("Invalid expiry date/time");
      }
      
      const expiryTime = Math.floor(expiryDateTime.getTime() / 1000);
      const now = Math.floor(Date.now() / 1000);
      
      if (expiryTime <= now) {
        throw new Error("Expiry time must be in the future");
      }

      // Create policy - same expiry for all recipients
      const policy = { expiryTime, maxDecryptCount };

      console.log(`📅 Policy created with expiry: ${expiryTime} (${new Date(expiryTime * 1000).toISOString()})`);

      updateProgressStep("step1", "active", "Loading encryption keys...");
      await getPrivateKey();
      updateProgressStep("step1", "completed", "Keys loaded ✓");

      if (selectedRecipients.length === 0) {
        throw new Error("Please select at least one recipient.");
      }

      updateProgressStep("step2", "active", "Reading and encrypting file...");

      // ✅ Read file ONCE
      const fileData = await selectedFile.arrayBuffer();
      const plainBytes = new Uint8Array(fileData);
      const plainEntropyInfo = computeEntropy(plainBytes);

      console.log(`\n${"=".repeat(60)}`);
      console.log(`🔐 HYBRID ENCRYPTION (Approach 2)`);
      console.log(`Recipients: ${selectedRecipients.length}`);
      console.log(`File: ${selectedFile.name} (${formatFileSize(selectedFile.size)})`);
      console.log(`Expiry: ${new Date(expiryTime * 1000).toLocaleString()}`);
      console.log(`${"=".repeat(60)}\n`);

      // ✅ ENCRYPT FILE ONCE (not per recipient!)
      const encryptionStartTime = Date.now();
      const encrypted = await encryptFileOnce(fileData);
      const encryptionTime = Date.now() - encryptionStartTime;

      // Calculate entropy (only once now!)
      const cipherBody = encrypted.fileEnc.subarray(12);
      const cipherEntropyInfo = computeEntropy(cipherBody);

      console.log(`\n📊 Entropy Analysis:`);
      console.log(`  Plaintext:  ${plainEntropyInfo.entropyBitsPerByte.toFixed(3)} bits/byte`);
      console.log(`  Ciphertext: ${cipherEntropyInfo.entropyBitsPerByte.toFixed(3)} bits/byte`);
      console.log(`  Encryption time: ${encryptionTime}ms\n`);

      encryptionMetrics = {
        plaintextEntropyBitsPerByte: plainEntropyInfo.entropyBitsPerByte,
        plaintextSampleSize: plainEntropyInfo.sampleSize,
        cipherEntropyBitsPerByte: cipherEntropyInfo.entropyBitsPerByte,
        cipherSampleSize: cipherEntropyInfo.sampleSize,
        encryptionTime: encryptionTime,
      };

      updateProgressStep("step2", "completed", "File encrypted ✓");
      updateProgressStep("step3", "active", "Wrapping keys for recipients...");

      // ✅ Now wrap the AES key for each recipient
      const wrappedKeys = [];
      for (let i = 0; i < selectedRecipients.length; i++) {
        const recipient = selectedRecipients[i];
        console.log(`\n[${i + 1}/${selectedRecipients.length}] Wrapping key for ${recipient.email}...`);

        // Get recipient's public key
        const receiverData = await getReceiverPublicKey(recipient.email);
        const receiverPubKey = await crypto.subtle.importKey(
          "jwk",
          receiverData.publicKeyJwk,
          { name: "RSA-OAEP", hash: "SHA-256" },
          true,
          ["encrypt"]
        );

        // Wrap the SAME AES key with this recipient's RSA key
        const encFileKey = await wrapKeyForRecipient(
          encrypted.fileKey,
          receiverPubKey,
          recipient.email
        );

        wrappedKeys.push({
          recipient: recipient,
          encFileKey: encFileKey,
          receiverData: receiverData,
        });
      }

      updateProgressStep("step3", "completed", `Keys wrapped for ${wrappedKeys.length} recipients ✓`);

      console.log(`\n✅ File encrypted ONCE, keys wrapped for ${wrappedKeys.length} recipients`);
      console.log(`⏱️  Total encryption time: ${encryptionTime}ms (vs ${encryptionTime * selectedRecipients.length}ms in old approach)\n`);

      // ✅ Now upload for each recipient
      updateProgressStep("step4", "active", "Uploading to recipients...");

      let successCount = 0;
      let failedRecipients = [];
      let allResults = [];

      // Convert encrypted hash to base64
      const hashEncBase64 = btoa(String.fromCharCode(...encrypted.hashEnc));

      for (let i = 0; i < wrappedKeys.length; i++) {
        const { recipient, encFileKey, receiverData } = wrappedKeys[i];

        // Rate limiting for email
        if (i > 0) {
          console.log(`\n⏳ Waiting 3 seconds before next upload (email rate limit)...`);
          await new Promise((resolve) => setTimeout(resolve, 3000));
        }

        console.log(`\n┌${"─".repeat(58)}┐`);
        console.log(`│ UPLOADING ${i + 1}/${wrappedKeys.length}: ${recipient.fullname || recipient.username}`.padEnd(59) + "│");
        console.log(`│ Email: ${recipient.email}`.padEnd(59) + "│");
        console.log(`└${"─".repeat(58)}┘`);

        try {
          updateProgressStep(
            "step4",
            "active",
            `Uploading for ${recipient.username} (${i + 1}/${wrappedKeys.length})...`
          );

          console.log(`  → Preparing upload...`);
          const formData = new FormData();
          
          // ✅ Upload the SAME encrypted file
          formData.append(
            "file",
            new Blob([encrypted.fileEnc]),
            selectedFile.name + ".enc"
          );
          
          formData.append(
            "metadata",
            JSON.stringify({
              receiverEmail: recipient.email,
              encFileKey: encFileKey, // Different per recipient
              hashEnc: hashEncBase64, // Same for all
              policy: policy,
              fileName: selectedFile.name,
              fileSize: selectedFile.size,
              mimeType: selectedFile.type || "application/octet-stream",
              encryptionTime: encryptionTime, // Same for all (file encrypted once)
            })
          );

          console.log(`  → Sending to server...`);
          const res = await fetch(`${API_BASE}upload.php`, {
            method: "POST",
            body: formData,
          });

          if (!res.ok) {
            const errorText = await res.text();
            throw new Error(`Server returned ${res.status}: ${errorText.substring(0, 100)}`);
          }

          const responseText = await res.text();
          let data;
          try {
            data = JSON.parse(responseText);
          } catch (e) {
            console.error("Failed to parse JSON:", responseText.substring(0, 500));
            throw new Error("Invalid JSON response from server");
          }

          if (!data.ok) {
            throw new Error(data.message || "Upload failed");
          }

          console.log(`  ✓ File ID: ${data.fileId}`);
          console.log(`  ✓ Email sent: ${data.emailSent ? "YES" : "NO"}`);
          if (data.emailError) {
            console.log(`  ⚠ Email error: ${data.emailError}`);
          }

          successCount++;
          allResults.push({
            email: recipient.email,
            name: recipient.fullname || recipient.username,
            success: true,
            fileId: data.fileId,
            emailSent: data.emailSent,
            emailError: data.emailError,
          });

          updateProgressStep(
            "step4",
            "completed",
            `✓ Sent to ${recipient.username} (${successCount}/${wrappedKeys.length})`
          );
          console.log(`\n✅ SUCCESS for ${recipient.email}\n`);

        } catch (error) {
          console.error(`\n❌ FAILED for ${recipient.username}:`, error.message);
          failedRecipients.push({
            email: recipient.email,
            name: recipient.fullname || recipient.username,
            error: error.message,
          });
          allResults.push({
            email: recipient.email,
            name: recipient.fullname || recipient.username,
            success: false,
            error: error.message,
          });
        }
      }

      updateProgressStep(
        "step5",
        "completed",
        `Complete! Sent to ${successCount}/${wrappedKeys.length} recipients ✓`
      );

      console.log(`\n${"=".repeat(60)}`);
      console.log(`FINAL RESULTS: ${successCount}/${wrappedKeys.length} successful`);
      console.log(`${"=".repeat(60)}\n`);

      // Show results
      if (successCount === wrappedKeys.length) {
        showSuccess(
          `File successfully encrypted and sent to all ${successCount} recipient${
            successCount > 1 ? "s" : ""
          }!`
        );
      } else if (successCount > 0) {
        showSuccess(
          `File sent to ${successCount} of ${wrappedKeys.length} recipients.`
        );
        if (failedRecipients.length > 0) {
          const failedNames = failedRecipients
            .map((f) => f.name || f.email)
            .join(", ");
          setTimeout(() => {
            showError(`Failed to send to: ${failedNames}`);
          }, 1000);
        }
      } else {
        throw new Error("Failed to send file to any recipients");
      }

      // Reset after 3 seconds
      setTimeout(() => {
        resetForm();
      }, 3000);
    } catch (error) {
      console.error("Upload error:", error);
      showError("Error: " + error.message);

      document.querySelectorAll(".progress-step.active").forEach((step) => {
        step.classList.remove("active");
        step.classList.add("error");
      });

      encryptBtn.disabled = false;
    }
  });

  // Helper function to format date in LOCAL timezone
  function formatLocalDateTime(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
  }

  // Validate expiry time is in the future
  function validateExpiryTime() {
    if (!expiryTimeInput.value) return;
    
    const selectedTime = new Date(expiryTimeInput.value).getTime();
    const currentTime = new Date().getTime();
    
    if (selectedTime <= currentTime) {
      showError("Expiry time must be in the future. Please select a later time.");
      
      // Auto-correct to 24 hours from now
      const futureDate = new Date();
      futureDate.setHours(futureDate.getHours() + 24);
      expiryTimeInput.value = formatLocalDateTime(futureDate);
      
      // Update min attribute
      expiryTimeInput.min = formatLocalDateTime(new Date());
    }
  }
  
  // Add real-time validation on datetime change
  expiryTimeInput.addEventListener("change", validateExpiryTime);
  expiryTimeInput.addEventListener("blur", validateExpiryTime);
  
  // Add event listeners to update encrypt button when expiry or download limit changes
  expiryTimeInput.addEventListener("input", updateEncryptButton);
  expiryTimeInput.addEventListener("change", updateEncryptButton);
  maxDecryptCountInput.addEventListener("input", updateEncryptButton);
  maxDecryptCountInput.addEventListener("change", updateEncryptButton);

  function resetForm() {
    console.log("Resetting form...");

    uploadForm.reset();
    selectedFile = null;
    fileInput.value = "";

    if (fileName) fileName.textContent = "No file selected";
    if (fileSize) fileSize.textContent = "0 MB";
    const fileTypeElement = document.getElementById("fileType");
    if (fileTypeElement) fileTypeElement.textContent = "";
    fileInfo.classList.remove("active");

    selectedRecipients = [];
    const recipientsContainer = document.getElementById(
      "selectedRecipientsContainer"
    );
    if (recipientsContainer) {
      recipientsContainer.innerHTML = "";
    }
    emailDisplay.classList.remove("active");
    receiverUsername.value = "";

    progressContainer.classList.remove("active");
    successMessage?.classList.remove("active");
    errorMessage?.classList.remove("active");

    encryptBtn.disabled = true;
    encryptBtn.textContent = "🔒 Select Recipients & File";
    encryptionMetrics = {};

    document.querySelectorAll(".progress-step").forEach((step) => {
      step.classList.remove("active", "completed", "error");
      const span = step.querySelector("span");
      const originalText = span.getAttribute("data-original");
      if (originalText) span.textContent = originalText;
    });

    // Reset datetime picker to 24 hours from NOW
    const futureDate = new Date();
    futureDate.setHours(futureDate.getHours() + 24);
    expiryTimeInput.value = formatLocalDateTime(futureDate);
    
    // Update minimum to current time
    const currentDate = new Date();
    expiryTimeInput.min = formatLocalDateTime(currentDate);
    
    // ✅ Reset download limit to default value of 3
    maxDecryptCountInput.value = "3";

    console.log("✓ Form reset complete");
  }
  
  // Update minimum datetime every minute to prevent past selection
  function updateMinDateTime() {
    const currentDate = new Date();
    const minDateTime = formatLocalDateTime(currentDate);
    expiryTimeInput.min = minDateTime;
    
    // Also validate current value if user hasn't changed it in a while
    if (expiryTimeInput.value) {
      const selectedTime = new Date(expiryTimeInput.value).getTime();
      const currentTime = currentDate.getTime();
      
      if (selectedTime <= currentTime) {
        // Silently update to valid time without showing error
        const futureDate = new Date();
        futureDate.setHours(futureDate.getHours() + 1);
        expiryTimeInput.value = formatLocalDateTime(futureDate);
      }
    }
  }
  
  // Update minimum time every 60 seconds
  setInterval(updateMinDateTime, 60000);

  window.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".progress-step span").forEach((span) => {
      span.setAttribute("data-original", span.textContent);
    });
    
    // Set default expiry time to 24 hours from now
    const futureDate = new Date();
    futureDate.setHours(futureDate.getHours() + 24);
    expiryTimeInput.value = formatLocalDateTime(futureDate);
    
    // Set minimum datetime to current time
    const currentDate = new Date();
    expiryTimeInput.min = formatLocalDateTime(currentDate);
    
    // ✅ Set default download limit to 3
    if (!maxDecryptCountInput.value) {
      maxDecryptCountInput.value = "3";
    }
  });

  loadUsers();
})();