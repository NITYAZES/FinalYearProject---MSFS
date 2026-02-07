// Parse the response data if available
const urlParams = new URLSearchParams(window.location.search);
const emailDomain = urlParams.get('domain') || 'gmail.com';

const emailDisplay = document.getElementById('emailDisplay');
if (emailDisplay) {
    emailDisplay.textContent = emailDomain;
}