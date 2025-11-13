document.addEventListener("DOMContentLoaded", () => {
    const countdownElements = document.querySelectorAll(".countdown");

    if (countdownElements.length === 0) {
        return; // Koi countdown nahi hai, to function rok dein
    }

    const interval = setInterval(() => {
        let activeCountdowns = 0; // Zinda countdowns ka count

        countdownElements.forEach(el => {
            // Check karein ke yeh pehle hi 'Expired' to nahi ho gaya
            if (el.classList.contains('expired-check')) {
                return;
            }

            const endDateString = el.getAttribute("data-end-at");
            
            // Check karein ke date string hai ya nahi
            if (!endDateString) {
                el.innerHTML = "Invalid Date";
                el.classList.add('expired-check'); // Isay dobara check na karein
                return;
            }

            // --- YEH HAI ASAL FIX ---
            // MySQL ke 'YYYY-MM-DD HH:MM:SS' format ko ISO format 'YYYY-MM-DDTHH:MM:SS' mein badalna
            const compatibleDateString = endDateString.replace(" ", "T");
            const endDateTime = new Date(compatibleDateString).getTime();

            // Check karein ke date sahi se parse hui
            if (isNaN(endDateTime)) {
                el.innerHTML = "Date Error";
                el.classList.add('expired-check');
                return;
            }

            const now = new Date().getTime();
            const distance = endDateTime - now;

            if (distance < 0) {
                el.innerHTML = "Expired";
                el.style.color = "#E50914"; // Brand Red
                el.classList.add('expired-check'); // Isay dobara check na karein
            } else {
                activeCountdowns++; // Yeh countdown abhi zinda hai
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                let output = "";
                if (days > 0) output += `${days}d `;
                output += `${hours}h ${minutes}m ${seconds}s`;

                el.innerHTML = output;
            }
        });

        // Agar koi bhi zinda countdown na bache to interval rok dein
        if (activeCountdowns === 0) {
            clearInterval(interval);
        }
    }, 1000);
});