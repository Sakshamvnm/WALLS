const loginForm = document.querySelector("#login-form");

if (loginForm) {
    loginForm.addEventListener("submit", () => {
        const submitButton = loginForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = "Signing in...";
    });
});
}