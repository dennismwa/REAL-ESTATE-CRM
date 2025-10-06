<?php
$pageTitle = 'Documents';
require_once 'config.php';
requirePermission('documents', 'view');

$action = $_GET['action'] ?? 'list';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload' && hasPermission('documents', 'create')) {
    $title = sanitize($_POST['title']);
    $relatedType = $_POST['related_type'];
    $relatedId = $_POST['related_id'] ? intval($_POST['related_id']) : null;
    
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['document'], 'documents');
        
        if ($upload['success']) {
            $stmt = $pdo->prepare("INSERT INTO documents (title, file_path, file_type, file_size, related_type, related_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title,
                $upload['path'],
                $_FILES['document']['type'],
                $_FILES['document']['size'],
                $relatedType,
                $relatedId,
                getUserId()
            ]);
            
            logActivity('Upload Document', "Uploaded document: $title");
            flashMessage('Document uploaded successfully!');
            redirect('/documents.php');
        } else {
            flashMessage($upload['message'], 'error');
        }
    }
}

// Get all documents
$stmt = $pdo->query("
    SELECT d.*, u.full_name as uploaded_by_name
    FROM documents d
    JOIN users u ON d.uploaded_by = u.id
    ORDER BY d.created_at DESC
");
$documents = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="p-4 md:p-6 pb-20 md:pb-6">
    <?php if ($action === 'list'): ?>
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Documents</h1>
                <p class="text-gray-600 mt-1">Manage company documents and files</p>
            </div>
            <?php if (hasPermission('documents', 'create')): ?>
            <a href="/documents.php?action=upload" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90 transition">
                <i class="fas fa-upload mr-2"></i>
                <span>Upload Document</span>
            </a>
            <?php endif; ?>
        </div>
        
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Title</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Size</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Category</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Uploaded By</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($documents as $doc): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-semibold"><?php echo sanitize($doc['title']); ?></p>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <i class="fas fa-file mr-1"></i>
                                <?php echo strtoupper(pathinfo($doc['file_path'], PATHINFO_EXTENSION)); ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <?php echo round($doc['file_size'] / 1024, 2); ?> KB
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                    <?php echo ucfirst(str_replace('_', ' ', $doc['related_type'])); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm"><?php echo sanitize($doc['uploaded_by_name']); ?></td>
                            <td class="px-4 py-3 text-sm"><?php echo formatDate($doc['created_at']); ?></td>
                            <td class="px-4 py-3">
                                <a href="<?php echo $doc['file_path']; ?>" target="_blank" class="text-primary hover:text-opacity-80" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                No documents uploaded yet
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    <?php elseif ($action === 'upload'): ?>
        <div class="max-w-2xl mx-auto">
            <div class="mb-6">
                <a href="/documents.php" class="text-primary hover:underline">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Documents
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold mb-6">Upload Document</h2>
                
                <form method="POST" enctype="multipart/form-data" action="">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Document Title *</label>
                            <input type="text" name="title" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">File *</label>
                            <input type="file" name="document" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            <p class="text-xs text-gray-500 mt-1">Max file size: 10MB</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                            <select name="related_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="general">General</option>
                                <option value="client">Client</option>
                                <option value="sale">Sale</option>
                                <option value="project">Project</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Related ID (optional)</label>
                            <input type="number" name="related_id"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            <p class="text-xs text-gray-500 mt-1">Enter client ID, sale ID, or project ID if applicable</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="flex-1 bg-primary text-white py-3 rounded-lg font-semibold hover:opacity-90 transition">
                            Upload Document
                        </button>
                        <a href="/documents.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>