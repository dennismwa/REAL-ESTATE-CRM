<?php
$pageTitle = 'Settings';
require_once 'config.php';
requirePermission('settings', 'view');

$settings = getSettings();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('settings', 'edit')) {
    $siteName = sanitize($_POST['site_name']);
    $primaryColor = sanitize($_POST['primary_color']);
    $secondaryColor = sanitize($_POST['secondary_color']);
    $contactEmail = sanitize($_POST['contact_email']);
    $contactPhone = sanitize($_POST['contact_phone']);
    $contactAddress = sanitize($_POST['contact_address']);
    $officeLatitude = floatval($_POST['office_latitude']);
    $officeLongitude = floatval($_POST['office_longitude']);
    $officeRadius = intval($_POST['office_radius']);
    
    // Handle logo upload
    $logoPath = $settings['logo_path'];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['logo'], 'logo');
        if ($upload['success']) {
            $logoPath = $upload['path'];
        }
    }
    
    $stmt = $pdo->prepare("UPDATE settings SET 
        site_name = ?, 
        primary_color = ?, 
        secondary_color = ?, 
        contact_email = ?, 
        contact_phone = ?, 
        contact_address = ?,
        office_latitude = ?,
        office_longitude = ?,
        office_radius = ?,
        logo_path = ?
        WHERE id = 1");
    
    if ($stmt->execute([$siteName, $primaryColor, $secondaryColor, $contactEmail, $contactPhone, $contactAddress, $officeLatitude, $officeLongitude, $officeRadius, $logoPath])) {
        logActivity('Update Settings', 'Updated system settings');
        flashMessage('Settings updated successfully!');
        redirect('/settings.php');
    }
}

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">System Settings</h1>
            <p class="text-gray-600 mt-1">Configure your CRM system</p>
        </div>
        
        <div class="bg-white rounded-lg shadow">
            <div class="border-b border-gray-200">
                <nav class="flex overflow-x-auto">
                    <button onclick="showTab('general')" class="settings-tab active px-6 py-4 text-sm font-semibold border-b-2 border-primary">
                        General
                    </button>
                    <button onclick="showTab('appearance')" class="settings-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent">
                        Appearance
                    </button>
                    <button onclick="showTab('location')" class="settings-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent">
                        Office Location
                    </button>
                </nav>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="p-6">
                <!-- General Settings Tab -->
                <div id="generalTab" class="tab-content">
                    <h3 class="text-lg font-bold mb-4">General Information</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">System Name</label>
                            <input type="text" name="site_name" required
                                   value="<?php echo sanitize($settings['site_name']); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Logo</label>
                            <div class="flex items-center gap-4">
                                <img src="<?php echo $settings['logo_path']; ?>" alt="Logo" class="h-16 w-16 object-contain border border-gray-300 rounded">
                                <input type="file" name="logo" accept="image/*"
                                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Recommended: PNG format, 200x200px</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Contact Email</label>
                            <input type="email" name="contact_email"
                                   value="<?php echo sanitize($settings['contact_email']); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Contact Phone</label>
                            <input type="tel" name="contact_phone"
                                   value="<?php echo sanitize($settings['contact_phone']); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Contact Address</label>
                            <textarea name="contact_address" rows="3"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"><?php echo sanitize($settings['contact_address']); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Appearance Tab -->
                <div id="appearanceTab" class="tab-content hidden">
                    <h3 class="text-lg font-bold mb-4">Color Scheme</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Primary Color</label>
                            <div class="flex items-center gap-4">
                                <input type="color" name="primary_color"
                                       value="<?php echo $settings['primary_color']; ?>"
                                       class="h-12 w-24 border border-gray-300 rounded-lg cursor-pointer">
                                <input type="text" value="<?php echo $settings['primary_color']; ?>"
                                       readonly
                                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg bg-gray-50">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Used for primary buttons and highlights</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Secondary Color</label>
                            <div class="flex items-center gap-4">
                                <input type="color" name="secondary_color"
                                       value="<?php echo $settings['secondary_color']; ?>"
                                       class="h-12 w-24 border border-gray-300 rounded-lg cursor-pointer">
                                <input type="text" value="<?php echo $settings['secondary_color']; ?>"
                                       readonly
                                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg bg-gray-50">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Used for secondary elements and accents</p>
                        </div>
                        
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-sm font-semibold mb-2">Preview</p>
                            <div class="flex gap-2">
                                <button type="button" class="px-4 py-2 rounded-lg text-white" style="background-color: <?php echo $settings['primary_color']; ?>">
                                    Primary Button
                                </button>
                                <button type="button" class="px-4 py-2 rounded-lg text-white" style="background-color: <?php echo $settings['secondary_color']; ?>">
                                    Secondary Button
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Location Tab -->
                <div id="locationTab" class="tab-content hidden">
                    <h3 class="text-lg font-bold mb-4">Office Location Settings</h3>
                    <p class="text-sm text-gray-600 mb-4">Configure the office location for attendance tracking</p>
                    
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Latitude</label>
                                <input type="number" name="office_latitude" step="0.00000001"
                                       value="<?php echo $settings['office_latitude']; ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Longitude</label>
                                <input type="number" name="office_longitude" step="0.00000001"
                                       value="<?php echo $settings['office_longitude']; ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Radius (meters)</label>
                            <input type="number" name="office_radius" min="10" max="1000"
                                   value="<?php echo $settings['office_radius']; ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            <p class="text-xs text-gray-500 mt-1">Employees must be within this radius to clock in</p>
                        </div>
                        
                        <button type="button" onclick="getCurrentLocation()" class="w-full md:w-auto px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-map-marker-alt mr-2"></i>Use Current Location
                        </button>
                    </div>
                </div>
                
                <?php if (hasPermission('settings', 'edit')): ?>
                <div class="flex gap-3 mt-6 pt-6 border-t border-gray-200">
                    <button type="submit" class="flex-1 md:flex-none px-8 py-3 bg-primary text-white rounded-lg font-semibold hover:opacity-90 transition">
                        Save Changes
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.settings-tab').forEach(btn => {
        btn.classList.remove('active', 'border-primary', 'text-primary');
        btn.classList.add('border-transparent', 'text-gray-600');
    });
    
    // Show selected tab
    document.getElementById(tabName + 'Tab').classList.remove('hidden');
    
    // Add active class to clicked button
    event.target.classList.add('active', 'border-primary', 'text-primary');
    event.target.classList.remove('border-transparent', 'text-gray-600');
}

// Get current location
function getCurrentLocation() {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
    }
    
    navigator.geolocation.getCurrentPosition(
        (position) => {
            document.querySelector('input[name="office_latitude"]').value = position.coords.latitude;
            document.querySelector('input[name="office_longitude"]').value = position.coords.longitude;
            alert('Location updated! Don\'t forget to save changes.');
        },
        (error) => {
            alert('Unable to get your location. Please enable location services.');
            console.error(error);
        }
    );
}

// Update color preview
document.querySelectorAll('input[type="color"]').forEach(input => {
    input.addEventListener('change', function() {
        this.nextElementSibling.value = this.value;
    });
});
</script>

<style>
.settings-tab.active {
    color: var(--primary-color);
    border-color: var(--primary-color);
}
</style>

<?php include 'includes/footer.php'; ?>