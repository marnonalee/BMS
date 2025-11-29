document.addEventListener("DOMContentLoaded", () => {
const passwordInput = document.getElementById("password");
const confirmPasswordInput = document.getElementById("confirm_password");
const requirementBox = document.getElementById("password-requirements");
const length = document.getElementById("length");
const uppercase = document.getElementById("uppercase");
const lowercase = document.getElementById("lowercase");
const number = document.getElementById("number");
const special = document.getElementById("special");

const firstNameInput = document.querySelector('input[name="first_name"]');
const lastNameInput = document.querySelector('input[name="last_name"]');
const addressInput = document.querySelector('input[name="resident_address"]');
const phoneInput = document.getElementById("phone");

const fileInput = document.getElementById("valid_id");
const fileNameSpan = document.getElementById("file-name");

const submitBtn = document.querySelector('button[type="submit"]');

confirmPasswordInput.disabled = true;
confirmPasswordInput.style.background = "#eee";
confirmPasswordInput.placeholder = "Complete password requirements first";

const loadingOverlay = document.createElement("div");
loadingOverlay.id = "loadingOverlay";
loadingOverlay.style.cssText = "position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.8);display:none;justify-content:center;align-items:center;z-index:9999;";
const loader = document.createElement("div");
loader.style.cssText = "border:6px solid #f3f3f3;border-top:6px solid #007bff;border-radius:50%;width:50px;height:50px;animation:spin 1s linear infinite;";
loadingOverlay.appendChild(loader);
document.body.appendChild(loadingOverlay);
const style = document.createElement("style");
style.innerHTML = "@keyframes spin {0% { transform: rotate(0deg);} 100% { transform: rotate(360deg);}}";
document.head.appendChild(style);

function checkPasswordRequirements(value) {
let valid = true;
if (value.length >= 8) length.className = "valid"; else { length.className = "invalid"; valid = false; }
if (/[A-Z]/.test(value)) uppercase.className = "valid"; else { uppercase.className = "invalid"; valid = false; }
if (/[a-z]/.test(value)) lowercase.className = "valid"; else { lowercase.className = "invalid"; valid = false; }
if (/\d/.test(value)) number.className = "valid"; else { number.className = "invalid"; valid = false; }
if (/[^A-Za-z0-9]/.test(value)) special.className = "valid"; else { special.className = "invalid"; valid = false; }
return valid;
}

passwordInput.addEventListener("focus", () => requirementBox.style.display = "block");
passwordInput.addEventListener("blur", () => { if(!checkPasswordRequirements(passwordInput.value)){ requirementBox.style.display = "block"; } });
passwordInput.addEventListener("input", () => {
const allValid = checkPasswordRequirements(passwordInput.value);
if (allValid) {
confirmPasswordInput.disabled = false;
confirmPasswordInput.style.background = "#fff";
confirmPasswordInput.placeholder = "Confirm Password";
requirementBox.classList.add("hidden");
} else {
confirmPasswordInput.disabled = true;
confirmPasswordInput.value = "";
confirmPasswordInput.style.background = "#eee";
confirmPasswordInput.placeholder = "Complete password requirements first";
requirementBox.classList.remove("hidden");
}
});

[firstNameInput, lastNameInput].forEach(input => {
input.addEventListener("input", () => {
input.value = input.value.replace(/[0-9]/g,'');
});
});

phoneInput.addEventListener("input", () => {
phoneInput.value = phoneInput.value.replace(/\D/g,'').slice(0,11);
});

fileInput.addEventListener("change", () => {
if(fileInput.files.length > 0){
fileNameSpan.textContent = fileInput.files[0].name;
} else {
fileNameSpan.textContent = "No file chosen";
}
});

const form = document.getElementById("signupForm");
const signupBtn = form.querySelector('button[type="submit"]');
const requiredFields = form.querySelectorAll("input[required], select[required]");

function checkAllFields() {
let allFilled = true;
requiredFields.forEach(field => {
if (field.type === "file") {
if (!field.files || field.files.length === 0) allFilled = false;
} else {
if (!field.value.trim()) allFilled = false;
}
});
signupBtn.disabled = !allFilled;
}

requiredFields.forEach(field => {
field.addEventListener("input", checkAllFields);
field.addEventListener("change", checkAllFields);
});

checkAllFields();

form.addEventListener("submit", (e) => {
loadingOverlay.style.display = "flex";
});
});
