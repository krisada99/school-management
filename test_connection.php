<?php
// --- ไฟล์สำหรับทดสอบการเชื่อมต่อฐานข้อมูลบน Synology NAS ---

// ✨ กรุณาใส่ข้อมูลเดียวกับในไฟล์ db_connect.php ของคุณ ✨
$serverName = "krisada.synology.me:3307";
$database   = "school_system_db";
$uid        = "school";
$pwd        = "P@ssw0rd@2012";

echo "<h1>Database Connection Test</h1>";
echo "<p>Attempting to connect to <strong>" . htmlspecialchars($serverName) . "</strong>...</p>";

try {
    // ตั้งค่า timeout ให้สั้นลงเพื่อให้รู้ผลเร็วขึ้น (5 วินาที)
    $conn = new PDO(
        "mysql:host=$serverName;dbname=$database;charset=utf8mb4",
        $uid,
        $pwd,
        [PDO::ATTR_TIMEOUT => 5]
    );

    // ถ้าเชื่อมต่อสำเร็จ
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<h2 style='color:green;'>✅ Success!</h2>";
    echo "<p>Successfully connected to the database '<strong>" . htmlspecialchars($database) . "</strong>'.</p>";
    echo "<p>Your setup is working correctly!</p>";

} catch (PDOException $e) {
    // ถ้าเชื่อมต่อล้มเหลว
    echo "<h2 style='color:red;'>❌ Failed!</h2>";
    echo "<p>Could not connect to the database. Please check the following:</p>";
    echo "<ul>";
    echo "<li>Is your Synology NAS turned on and connected to the internet?</li>";
    echo "<li>Is Port Forwarding (Port 3307) set up correctly on your home router?</li>";
    echo "<li>Is the Firewall on your Synology NAS allowing connections on port 3306?</li>";
    echo "<li>Are the username and password correct?</li>";
    echo "</ul>";
    echo "<hr>";
    echo "<strong>Error Details:</strong><br>";
    echo "<pre style='background-color:#f0f0f0; padding:10px; border:1px solid #ccc;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
}

$conn = null;
?>