console.log("SubHub SMM Panel JS v1.0 Active");

// Helper function for DOM selection
const $ = (selector, parent = document) => parent.querySelector(selector);
const $$ = (selector, parent = document) => parent.querySelectorAll(selector);

// Tamam services ka data PHP se JS mein lein (smm_order.php se)
const allServicesData = typeof window.allServicesData !== 'undefined' ? window.allServicesData : {};

document.addEventListener("DOMContentLoaded", () => {
    
    // Check if we are on an SMM page
    if (!$('.smm-app-container')) return;

    // --- Accordion (Category) Logic ---
    $$(".category-header").forEach(header => {
        header.addEventListener("click", () => {
            const isActive = header.classList.contains("active");
            const list = $('#category-' + header.dataset.category);
            
            // Sab band karein
            $$(".category-header").forEach(h => h.classList.remove("active"));
            $$(".service-list").forEach(l => l.style.display = "none");
            
            // Agar active nahi tha, to isay kholein
            if (!isActive) {
                header.classList.add("active");
                if (list) {
                    list.style.display = "block";
                }
            }
        });
    });

    // --- Search Logic ---
    const searchInput = $("#service-search");
    if (searchInput) {
        searchInput.addEventListener("input", (e) => {
            const query = e.target.value.toLowerCase();
            
            $$(".category-group").forEach(category => {
                let categoryVisible = false;
                
                category.querySelectorAll(".service-item").forEach(service => {
                    const name = service.dataset.serviceName.toLowerCase();
                    if (name.includes(query)) {
                        service.style.display = "block";
                        categoryVisible = true;
                    } else {
                        service.style.display = "none";
                    }
                });
                
                if (categoryVisible) {
                    category.style.display = "block";
                } else {
                    category.style.display = "none";
                }
            });
        });
    }

    // --- Modal (Popup) Logic ---
    const modal = $('#order-modal');
    if (modal) {
        const modalForm = $('#modal-order-form');
        const closeBtn = $('#modal-close-btn');
        const modalLink = $('#modal-link');
        const modalQuantity = $('#modal-quantity');
        const modalMinMaxMsg = $('#modal-min-max-msg');
        const modalTotalCharge = $('#modal-total-charge');
        const linkDetector = $('#link-detector-msg');
        const chaChingSound = $('#cha-ching-sound');
        
        let currentService = null;

        // Service par click kar ke modal kholein
        $$(".service-item").forEach(item => {
            item.addEventListener("click", () => {
                const serviceId = item.dataset.serviceId;
                currentService = allServicesData[serviceId];
                
                if (!currentService) return;

                $('#modal-service-name').innerText = currentService.name;
                $('#modal-service-id').value = serviceId;
                
                // Description (Refill/Cancel/Avg Time)
                $('#modal-service-desc').innerHTML = `
                    <p><strong>Avg. Time:</strong> ${currentService.avg_time}</p>
                    <p><strong>Refill:</strong> <span style="color: ${currentService.has_refill ? 'green' : 'red'};">${currentService.has_refill ? 'Yes (Automatic via API)' : 'No'}</span></p>
                    <p><strong>Cancel:</strong> <span style="color: ${currentService.has_cancel ? 'green' : 'red'};">${currentService.has_cancel ? 'Yes' : 'No'}</span></p>
                `;
                
                modalMinMaxMsg.innerText = `Min: ${currentService.min} / Max: ${currentService.max}`;
                
                // Form reset karein
                modalLink.value = '';
                modalQuantity.value = '';
                modalTotalCharge.innerText = 'PKR 0.00';
                linkDetector.innerText = '';
                modalForm.querySelector('.btn-app-primary').disabled = true; // Button ko disable karein

                if(chaChingSound) {
                     chaChingSound.currentTime = 0; 
                     chaChingSound.play().catch(e => console.log("Audio play failed"));
                }
                
                modal.classList.add("active");
                modalQuantity.focus();
            });
        });
        
        // Modal band karein
        closeBtn.addEventListener("click", () => modal.classList.remove("active"));
        modal.addEventListener("click", (e) => {
            if (e.target === modal) modal.classList.remove("active");
        });

        // --- Link Detector ---
        modalLink.addEventListener("input", (e) => {
            const link = e.target.value.toLowerCase();
            if (link.includes("instagram.com")) {
                linkDetector.innerText = "✅ Instagram Link Detected";
            } else if (link.includes("tiktok.com")) {
                linkDetector.innerText = "✅ TikTok Link Detected";
            } else if (link.includes("youtube.com") || link.includes("youtu.be")) {
                linkDetector.innerText = "✅ YouTube Link Detected";
            } else if (link.length > 10) {
                 detector.innerText = "⚠️ Link type not recognized";
            } else {
                linkDetector.innerText = "";
            }
        });

        // --- Live Price Calculator & Validation ---
        modalQuantity.addEventListener("input", (e) => {
            if (!currentService) return;
            const quantity = parseInt(e.target.value) || 0;
            const ratePer1000 = currentService.rate;
            const totalCharge = (quantity / 1000) * ratePer1000;
            
            modalTotalCharge.innerText = `PKR ${totalCharge.toFixed(4)}`;

            const min = currentService.min;
            const max = currentService.max;
            const placeOrderBtn = modalForm.querySelector('.btn-app-primary');
            
            if (quantity < min || quantity > max || quantity === 0) {
                placeOrderBtn.disabled = true;
                modalMinMaxMsg.style.color = 'red';
            } else {
                placeOrderBtn.disabled = false;
                modalMinMaxMsg.style.color = 'var(--app-secondary)';
            }
        });
    } // End SMM Modal Logic
});