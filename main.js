console.log("SubHub v6.3 JS Loaded (with On-Demand feature)");

document.addEventListener("DOMContentLoaded", () => {
    
    // --- Mobile Menu Toggle (Purana Code) ---
    const toggleButton = document.querySelector(".mobile-nav-toggle");
    const navLinks = document.querySelector(".nav-links");

    if (toggleButton && navLinks) {
        toggleButton.addEventListener("click", () => {
            // Button aur links par 'active' class lagayein/hatayein
            toggleButton.classList.toggle("active");
            navLinks.classList.toggle("active");
        });
    }

    // --- Naya On-Demand WhatsApp Button Code ---
    const requestBtn = document.getElementById("send-request-btn");
    const requestText = document.getElementById("tool-request-text");

    if (requestBtn && requestText) {
        // Admin ka number button se hasil karein (jo PHP se aaya hai)
        const adminWhatsapp = requestBtn.getAttribute("data-admin-whatsapp");

        requestBtn.addEventListener("click", () => {
            const message = requestText.value;

            if (message.trim() === "") {
                alert("Please enter the tool name or details first.");
                requestText.focus();
                return;
            }

            // WhatsApp ke liye message format karein
            const fullMessage = "👋 Assalam-o-Alaikum Admin,\n\nI have an on-demand tool request:\n\n\"" + message + "\"\n\nPlease let me know if you can arrange this.";
            
            // Message ko URL-friendly banayein
            const encodedMessage = encodeURIComponent(fullMessage);
            
            // WhatsApp link banayein
            const whatsappUrl = `https://wa.me/${adminWhatsapp}?text=${encodedMessage}`;

            // Naye tab mein WhatsApp kholein
            window.open(whatsappUrl, '_blank');
            
            // Text box khali kar dein
            requestText.value = '';
        });
    }
});