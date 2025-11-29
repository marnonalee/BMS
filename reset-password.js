const passwordInput = document.getElementById("password");
const confirmInput = document.getElementById("confirm_password");
const requirementBox = document.getElementById("password-requirements");
const matchError = document.getElementById("match-error");
const lengthReq = document.getElementById("length");
const uppercaseReq = document.getElementById("uppercase");
const lowercaseReq = document.getElementById("lowercase");
const numberReq = document.getElementById("number");
const specialReq = document.getElementById("special");
requirementBox.style.display = "none";
confirmInput.disabled = true;

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
lengthReq.className = value.length >= 8 ? "valid" : (valid=false,"invalid");
uppercaseReq.className = /[A-Z]/.test(value) ? "valid" : (valid=false,"invalid");
lowercaseReq.className = /[a-z]/.test(value) ? "valid" : (valid=false,"invalid");
numberReq.className = /\d/.test(value) ? "valid" : (valid=false,"invalid");
specialReq.className = /[^A-Za-z0-9]/.test(value) ? "valid" : (valid=false,"invalid");
return valid;
}

passwordInput.addEventListener("input", () => {
const value = passwordInput.value;
const allValid = checkPasswordRequirements(value);
if (value.length > 0 && !allValid) {
requirementBox.style.display = "block";
requirementBox.style.opacity = "1";
confirmInput.disabled = true;
confirmInput.value = "";
confirmInput.style.background = "#eee";
confirmInput.placeholder = "Complete password requirements first";
matchError.style.display = "none";
} else if (allValid) {
requirementBox.style.opacity = "0";
setTimeout(()=>{ requirementBox.style.display="none"; }, 300);
confirmInput.disabled = false;
confirmInput.style.background = "#fff";
confirmInput.placeholder = "Confirm Password";
} else {
requirementBox.style.opacity = "0";
setTimeout(()=>{ requirementBox.style.display="none"; }, 300);
confirmInput.disabled = true;
confirmInput.value = "";
confirmInput.style.background = "#eee";
confirmInput.placeholder = "Complete password requirements first";
matchError.style.display = "none";
}
});

confirmInput.addEventListener("input", () => {
matchError.style.display = confirmInput.value !== passwordInput.value ? "block" : "none";
});

document.getElementById("resetForm").addEventListener("submit", function(e){
if(!checkPasswordRequirements(passwordInput.value)){
e.preventDefault();
alert("Password does not meet all requirements.");
} else if(passwordInput.value !== confirmInput.value){
e.preventDefault();
alert("Passwords do not match.");
} else {
loadingOverlay.style.display = "flex";
}
});

if (typeof SUCCESS_MESSAGE !== 'undefined' && SUCCESS_MESSAGE) {
const modal = document.getElementById("successModal");
modal.style.display = "flex";
setTimeout(()=>{
modal.style.display = "none";
window.location.href = "user/dashboard.php";
}, 2000);
}
