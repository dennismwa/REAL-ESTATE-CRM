<?php
$pageTitle = 'Attendance';
require_once 'config.php';
requirePermission('attendance', 'view');

$userId = getUserId();
$userRole = getUserRole();
$settings = getSettings();

// Check if user is clocked in today
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND DATE(clock_in) = CURDATE() AND clock_out IS NULL");
$stmt->execute([$userId]);
$currentAttendance = $stmt->fetch();

// Handle clock in/out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('attendance', 'create')) {
    $action = $_POST['action'] ?? '';
    $latitude = floatval($_POST['latitude'] ?? 0);
    $longitude = floatval($_POST['longitude'] ?? 0);
    
    if ($action === 'clock_in' && !$currentAttendance) {
        // Check if within office radius
        if (!isWithinOffice($latitude, $longitude)) {
            flashMessage('You must be at the office location to clock in', 'error');
        } else {
            $stmt = $pdo->prepare("INSERT INTO attendance (user_id, clock_in, clock_in_latitude, clock_in_longitude) VALUES (?, NOW(), ?, ?)");
            if ($stmt->execute([$userId, $latitude, $longitude])) {
                logActivity('Clock In', 'Clocked in');
                flashMessage('Clocked in successfully!');
                redirect('/attendance.php');
            }
        }
    } elseif ($action === 'clock_out' && $currentAttendance) {
        $stmt = $pdo->prepare("UPDATE attendance SET clock_out = NOW(), clock_out_latitude = ?, clock_out_longitude = ? WHERE id = ?");
        if ($stmt->execute([$latitude, $longitude, $currentAttendance['id']])) {
            logActivity('Clock Out', 'Clocked out');
            flashMessage('Clocked out successfully!');
            redirect('/attendance.php');
        }
    }
}

// Get attendance records
if (in_array($userRole, ['admin', 'manager', 'finance'])) {
    // Show all attendance
    $stmt = $pdo->query("SELECT a.*, u.full_name, u.role 
                         FROM attendance a 
                         JOIN users u ON a.user_id = u.id 
                         ORDER BY a.clock_in DESC 
                         LIMIT 50");
    $attendanceRecords = $stmt->fetchAll();
} else {
    // Show only own attendance
    $stmt = $pdo->prepare("SELECT a.*, u.full_name, u.role 
                           FROM attendance a 
                           JOIN users u ON a.user_id = u.id 
                           WHERE a.user_id = ? 
                           ORDER BY a.clock_in DESC 
                           LIMIT 30");
    $stmt->execute([$userId]);
    $attendanceRecords = $stmt->fetchAll();
}

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Attendance</h1>
        <p class="text-gray-600 mt-1">Track your working hours</p>
    </div>
    
    <!-- Clock In/Out Card -->
    <?php if (hasPermission('attendance', 'create')): ?>
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="text-center">
            <div class="mb-6">
                <div id="currentTime" class="text-4xl font-bold text-primary mb-2"></div>
                <p class="text-gray-600"><?php echo date('l, F d, Y'); ?></p>
            </div>
            
            <?php if ($currentAttendance): ?>
                <div class="mb-6 p-4 bg-green-50 rounded-lg">
                    <p class="text-green-800 font-semibold mb-2">
                        <i class="fas fa-check-circle mr-2"></i>You are clocked in
                    </p>
                    <p class="text-sm text-gray-600">
                        Clock in time: <?php echo date('h:i A', strtotime($currentAttendance['clock_in'])); ?>
                    </p>
                </div>
                
                <form method="POST" id="clockOutForm">
                    <input type="hidden" name="action" value="clock_out">
                    <input type="hidden" name="latitude" id="clockOutLat">
                    <input type="hidden" name="longitude" id="clockOutLon">
                    <button type="button" onclick="clockOut()" 
                            class="w-full md:w-auto px-8 py-4 bg-red-600 text-white rounded-lg text-lg font-semibold hover:bg-red-700 transition">
                        <i class="fas fa-sign-out-alt mr-2"></i>Clock Out
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" id="clockInForm">
                    <input type="hidden" name="action" value="clock_in">
                    <input type="hidden" name="latitude" id="clockInLat">
                    <input type="hidden" name="longitude" id="clockInLon">
                    <button type="button" onclick="clockIn()" 
                            class="w-full md:w-auto px-8 py-4 bg-primary text-white rounded-lg text-lg font-semibold hover:opacity-90 transition">
                        <i class="fas fa-sign-in-alt mr-2"></i>Clock In
                    </button>
                </form>
            <?php endif; ?>
            
            <p class="text-xs text-gray-500 mt-4">
                <i class="fas fa-info-circle mr-1"></i>
                <?php if ($settings['office_latitude']): ?>
                You must be within <?php echo $settings['office_radius']; ?>m of the office to clock in
                <?php else: ?>
                Location tracking is enabled
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Attendance Records -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-bold">Attendance History</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                        <?php if (in_array($userRole, ['admin', 'manager', 'finance'])): ?>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Employee</th>
                        <?php endif; ?>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Clock In</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Clock Out</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Duration</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($attendanceRecords as $record): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm"><?php echo formatDate($record['clock_in'], 'M d, Y'); ?></td>
                        <?php if (in_array($userRole, ['admin', 'manager', 'finance'])): ?>
                        <td class="px-4 py-3 text-sm">
                            <div>
                                <p class="font-semibold"><?php echo sanitize($record['full_name']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo ucfirst(str_replace('_', ' ', $record['role'])); ?></p>
                            </div>
                        </td>
                        <?php endif; ?>
                        <td class="px-4 py-3 text-sm"><?php echo date('h:i A', strtotime($record['clock_in'])); ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?php echo $record['clock_out'] ? date('h:i A', strtotime($record['clock_out'])) : '<span class="text-green-600 font-semibold">Active</span>'; ?>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <?php 
                            if ($record['clock_out']) {
                                $duration = strtotime($record['clock_out']) - strtotime($record['clock_in']);
                                $hours = floor($duration / 3600);
                                $minutes = floor(($duration % 3600) / 60);
                                echo $hours . 'h ' . $minutes . 'm';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="px-2 py-1 text-xs rounded-full <?php 
                                echo $record['status'] === 'present' ? 'bg-green-100 text-green-800' : 
                                    ($record['status'] === 'late' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'); 
                            ?>">
                                <?php echo ucfirst($record['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($attendanceRecords)): ?>
                    <tr>
                        <td colspan="<?php echo in_array($userRole, ['admin', 'manager', 'finance']) ? '6' : '5'; ?>" class="px-4 py-8 text-center text-gray-500">
                            No attendance records found
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Update current time
function updateTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const timeEl = document.getElementById('currentTime');
    if (timeEl) timeEl.textContent = timeStr;
}
setInterval(updateTime, 1000);
updateTime();

// Get user location
function getLocation(callback) {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
    }
    
    navigator.geolocation.getCurrentPosition(
        (position) => {
            callback(position.coords.latitude, position.coords.longitude);
        },
        (error) => {
            alert('Unable to get your location. Please enable location services.');
            console.error(error);
        }
    );
}

// Clock In
function clockIn() {
    getLocation((lat, lon) => {
        document.getElementById('clockInLat').value = lat;
        document.getElementById('clockInLon').value = lon;
        document.getElementById('clockInForm').submit();
    });
}

// Clock Out
function clockOut() {
    if (confirm('Are you sure you want to clock out?')) {
        getLocation((lat, lon) => {
            document.getElementById('clockOutLat').value = lat;
            document.getElementById('clockOutLon').value = lon;
            document.getElementById('clockOutForm').submit();
        });
    }
}
</script>

<?php include 'includes/footer.php'; ?>