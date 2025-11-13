<?php
include '_header.php';

// 1. Check karein ke feature enabled hai
if (empty($GLOBALS['settings']['daily_spin_enabled']) || $GLOBALS['settings']['daily_spin_enabled'] != '1') {
    echo "<h1>Spin & Win is currently disabled.</h1>";
    include '_footer.php';
    exit;
}

// 2. Tamam active prizes database se fetch karein
try {
    $stmt_prizes = $db->query("SELECT * FROM wheel_prizes WHERE is_active = 1 ORDER BY id ASC");
    $prizes = $stmt_prizes->fetchAll();
} catch (PDOException $e) {
    $prizes = [];
}

if (empty($prizes)) {
    echo "<h1>No prizes are configured for the Spin Wheel. Please contact admin.</h1>";
    include '_footer.php';
    exit;
}

// 3. User ka last spin time check karein
$cooldown_hours = (int)($GLOBALS['settings']['daily_spin_cooldown_hours'] ?? 24);
$stmt_user = $db->prepare("SELECT last_spin_time FROM users WHERE id = ?");
$stmt_user->execute([$_SESSION['user_id']]);
$user = $stmt_user->fetch();

$can_spin = false;
$cooldown_ends_at = '';

if ($user['last_spin_time']) {
    $last_spin = strtotime($user['last_spin_time']);
    $next_spin_time = $last_spin + ($cooldown_hours * 3600);
    
    if (time() >= $next_spin_time) {
        $can_spin = true;
    } else {
        $cooldown_ends_at = date('Y-m-d H:i:s', $next_spin_time);
    }
} else {
    $can_spin = true;
}

// PHP data ko JavaScript mein pass karein
$prizes_json = [];
$segment_colors = [];
$segment_labels = [];
foreach ($prizes as $prize) {
    $segment_labels[] = $prize['label'];
    $segment_colors[] = $prize['color'];
    $prizes_json[] = [
        'id' => $prize['id'],
        'label' => $prize['label'],
        'amount' => $prize['amount'],
        'color' => $prize['color']
    ];
}
?>

<style>
/* --- NAYI Animation (Pointer ke liye) --- */
@keyframes bounce {
  0%, 100% { transform: translate(-50%, -100%) translateY(0); }
  50% { transform: translate(-50%, -100%) translateY(-10px); }
}

.wheel-container {
    position: relative;
    width: 100%;
    max-width: 400px; /* Wheel ka size thora chota kiya */
    margin: 2rem auto;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.wheel-pointer {
    position: absolute;
    top: 5px; /* Pointer ko wheel ke border ke bilkul upar rakha */
    left: 50%;
    transform: translate(-50%, -100%); /* Pointer ko top-center adjust karein */
    width: 0;
    height: 0;
    border-left: 18px solid transparent;
    border-right: 18px solid transparent;
    border-top: 25px solid #FFC107; /* Golden pointer */
    z-index: 10;
    filter: drop-shadow(0 2px 2px rgba(0,0,0,0.5));
    animation: bounce 1.5s ease-in-out infinite; /* NAYI Animation */
}
.wheel-center-button {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 70px; /* Chota button */
    height: 70px;
    background: var(--card-color);
    border: 8px solid #fff;
    border-radius: 50%;
    z-index: 10;
    cursor: pointer;
    box-shadow: 0 0 15px rgba(0,0,0,0.3);
    transition: all 0.2s ease;
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: 700;
    color: var(--text-color);
    text-transform: uppercase;
    font-size: 1rem;
}
.wheel-center-button:hover {
    transform: translate(-50%, -50%) scale(1.1);
}
.wheel-center-button:disabled {
    cursor: not-allowed;
    background: #555;
    transform: translate(-50%, -50%) scale(1);
    animation: none;
}
canvas {
    width: 100%;
    height: auto;
    border-radius: 50%;
    box-shadow: 0 10px 25px rgba(0,0,0,0.4);
    border: 4px solid #fff; /* Wheel par white border */
}

/* --- NAYI HTML LEGEND (Layout Fix) --- */
.wheel-legend {
    display: grid;
    grid-template-columns: 1fr 1fr; /* 2 columns */
    gap: 0.5rem 1rem;
    margin-top: 1.5rem;
    width: 100%;
    max-width: 300px;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.legend-color-box {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid rgba(255,255,255,0.2);
}
.legend-item span {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-muted);
}
/* --- NAYI LEGEND KHATAM --- */


.spin-result {
    text-align: center;
    margin-top: 1.5rem;
}
#spin-message {
    font-size: 1.2rem;
    font-weight: 600;
    padding: 1rem;
    border-radius: var(--radius);
    display: none;
    margin-bottom: 1rem;
}
#spin-message.success {
    background: #0b3d1b; color: #cffddc; border: 1px solid #1f873f;
}
#spin-message.error {
    background: #5c0d10; color: #fdd; border: 1px solid #e50914;
}
#spin-cooldown {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--brand-red);
    text-align: center;
    margin-top: 1rem;
    animation: pulse 1.5s infinite alternate;
}
@keyframes pulse {
    from { transform: scale(1); opacity: 0.8; }
    to { transform: scale(1.05); opacity: 1; }
}
</style>
<h1 class="section-title">Daily Spin & Win</h1>
<p style="text-align: center; color: var(--text-muted); margin-top: -1rem; margin-bottom: 1rem;">
    Spin the wheel every <?php echo $cooldown_hours; ?> hours to win a free bonus!
</p>

<div class="wheel-container">
    <div class="wheel-pointer"></div>
    <canvas id="spin-wheel" width="400" height="400"></canvas>
    <button class="wheel-center-button" id="spin-button" <?php if (!$can_spin) echo 'disabled'; ?>>
        Spin
    </button>
</div>

<div class="wheel-legend">
    <?php foreach ($prizes as $prize): ?>
        <div class="legend-item">
            <div class="legend-color-box" style="background-color: <?php echo sanitize($prize['color']); ?>;"></div>
            <span><?php echo sanitize($prize['label']); ?></span>
        </div>
    <?php endforeach; ?>
</div>
<div class="spin-result">
    <div id="spin-message"></div>
    
    <div id="spin-cooldown" <?php if ($can_spin) echo 'style="display:none;"'; ?>>
        Next spin available in: <span id="cooldown-timer">Loading...</span>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.9.1/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

<script>
// --- NAYI JAVASCRIPT (Wheel ke liye) ---

document.addEventListener("DOMContentLoaded", () => {
    
    const prizes = <?php echo json_encode($prizes_json); ?>;
    const segmentLabels = <?php echo json_encode($segment_labels); ?>;
    const segmentColors = <?php echo json_encode($segment_colors); ?>;

    const numSegments = prizes.length;
    const segmentAngle = 360 / numSegments; // Har segment ka angle
    const canvas = document.getElementById('spin-wheel');
    const spinButton = document.getElementById('spin-button');
    const spinMessage = document.getElementById('spin-message');
    const cooldownBox = document.getElementById('spin-cooldown');
    const cooldownTimer = document.getElementById('cooldown-timer');
    
    let isSpinning = false;
    let canSpin = <?php echo $can_spin ? 'true' : 'false'; ?>;
    
    // NAYA: Plugin register karein
    Chart.register(ChartDataLabels);
    
    // Chart.js Wheel ko Draw Karein
    const spinWheel = new Chart(canvas, {
        type: 'pie',
        data: {
            labels: segmentLabels,
            datasets: [{
                data: Array(numSegments).fill(1), // Sab segments barabar hain
                backgroundColor: segmentColors,
                borderWidth: 2,
                borderColor: '#ffffff' // Segments ke beech white border
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            animation: { duration: 0 },
            plugins: {
                // Purani legend ko 100% disable karein
                legend: {
                    display: false 
                },
                tooltip: { enabled: false },
                // Naya: Datalabels (wheel ke andar text)
                datalabels: {
                    color: '#fff',
                    rotation: (context) => {
                        const angle = (context.chart.getDatasetMeta(0).data[context.dataIndex].startAngle + context.chart.getDatasetMeta(0).data[context.dataIndex].endAngle) / 2;
                        return angle * (180 / Math.PI) + 90; // Rotate text with slice
                    },
                    font: {
                        weight: 'bold',
                        size: 14,
                    },
                    textShadowColor: 'rgba(0,0,0,0.5)',
                    textShadowBlur: 4,
                }
            }
        }
    });

    // Cooldown Timer Logic
    let cooldownEndsAt = "<?php echo $cooldown_ends_at; ?>";
    function updateCountdown() {
        if (!canSpin && cooldownEndsAt) {
            let interval = setInterval(function() {
                let now = new Date().getTime();
                let end = new Date(cooldownEndsAt.replace(' ', 'T')).getTime(); 
                let distance = end - now;

                if (distance < 0) {
                    clearInterval(interval);
                    cooldownBox.style.display = 'none';
                    spinMessage.style.display = 'none';
                    spinButton.disabled = false;
                    spinButton.innerText = 'Spin';
                    canSpin = true;
                } else {
                    let hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    let minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    let seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    cooldownTimer.innerText = `${hours}h ${minutes}m ${seconds}s`;
                    spinButton.innerText = 'Wait';
                }
            }, 1000);
        }
    }
    updateCountdown(); // Call on load

    // Spin Button Click Logic
    spinButton.addEventListener('click', () => {
        if (isSpinning || !canSpin) return;
        
        isSpinning = true;
        spinButton.disabled = true;
        spinButton.innerText = '...';
        spinMessage.style.display = 'none';
        spinMessage.className = '';
        cooldownBox.style.display = 'none';

        fetch('spin_api.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const prizeIndex = data.prize_index;
                    
                    // NAYI, BEHTAR SPIN LOGIC
                    const currentRotation = gsap.getProperty(canvas, "rotation");
                    const normalizedRotation = currentRotation % 360;
                    
                    // 5-8 poore chakkar
                    const baseRotation = (360 * (5 + Math.floor(Math.random() * 3)));
                    
                    // Prize index ko center karne ke liye
                    // Angle ko 0-360 range mein fix karein
                    let targetSliceAngle = (segmentAngle * prizeIndex) + (segmentAngle / 2);
                    
                    // Final rotation (pointer top par hai (0 degrees), is liye 270 degree offset)
                    const finalRotation = baseRotation - targetSliceAngle + 270;

                    gsap.to(canvas, {
                        duration: 6, // 6 second animation
                        rotation: finalRotation, // Nayi calculation
                        ease: "power3.out",
                        onComplete: () => {
                            isSpinning = false;
                            canSpin = false;
                            spinMessage.innerText = data.message;
                            spinMessage.classList.add(data.prize_label.toLowerCase().includes('try again') ? 'error' : 'success');
                            spinMessage.style.display = 'block';
                            
                            // Naya cooldown timer shuru karein
                            const now = new Date();
                            const cooldownHours = parseInt("<?php echo $cooldown_hours; ?>");
                            now.setHours(now.getHours() + cooldownHours);
                            cooldownEndsAt = now.toISOString().slice(0, 19).replace('T', ' '); // Format for our timer
                            cooldownBox.style.display = 'block';
                            updateCountdown();
                        }
                    });
                    
                } else {
                    spinMessage.innerText = data.message;
                    spinMessage.classList.add('error');
                    spinMessage.style.display = 'block';
                    isSpinning = false;
                    spinButton.innerText = 'Spin';
                    if(data.message.includes('You can spin again in')) {
                         canSpin = false;
                         cooldownBox.style.display = 'block';
                         updateCountdown(); 
                    } else {
                         spinButton.disabled = false;
                    }
                }
            })
            .catch(error => {
                console.error('Spin API Error:', error);
                spinMessage.innerText = 'Error: Could not connect to server.';
                spinMessage.classList.add('error');
                spinMessage.style.display = 'block';
                isSpinning = false;
                spinButton.disabled = false;
                spinButton.innerText = 'Spin';
            });
    });
});
</script>

<?php include '_footer.php'; ?>