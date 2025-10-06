<artifacts>
<artifact id="document-generator-service" type="application/vnd.ant.code" language="php" title="DocumentGeneratorService.php">
<?php
// app/services/DocumentGeneratorService.phpclass DocumentGeneratorService {
private $pdo;
private $templatePath;public function __construct($pdo) {
    $this->pdo = $pdo;
    $this->templatePath = __DIR__ . '/../../templates/documents/';
}/**
 * Generate document from template
 */
public function generateFromTemplate($templateId, $entityType, $entityId, $format = 'pdf') {
    // Get template
    $stmt = $this->pdo->prepare("SELECT * FROM document_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch();    if (!$template) {
        throw new Exception("Template not found");
    }    // Get entity data
    $entityData = $this->getEntityData($entityType, $entityId);    // Load template file
    $templateContent = file_get_contents($this->templatePath . $template['template_file']);    // Replace variables
    $documentContent = $this->replaceTemplateVariables($templateContent, $entityData);    // Generate document based on format
    switch ($format) {
        case 'pdf':
            $filePath = $this->generatePDF($documentContent, $entityType, $entityId);
            break;
        case 'docx':
            $filePath = $this->generateDOCX($documentContent, $entityType, $entityId);
            break;
        case 'html':
            $filePath = $this->saveHTML($documentContent, $entityType, $entityId);
            break;
        default:
            throw new Exception("Unsupported format");
    }    // Save generated document record
    $stmt = $this->pdo->prepare("
        INSERT INTO generated_documents 
        (template_id, entity_type, entity_id, file_path, signature_status, generated_by, created_at)
        VALUES (?, ?, ?, ?, 'pending', ?, NOW())
    ");    $stmt->execute([
        $templateId,
        $entityType,
        $entityId,
        $filePath,
        $_SESSION['user_id'] ?? 1
    ]);    return [
        'success' => true,
        'file_path' => $filePath,
        'document_id' => $this->pdo->lastInsertId()
    ];
}/**
 * Get entity data for template
 */
private function getEntityData($entityType, $entityId) {
    $data = [];    switch ($entityType) {
        case 'sale':
            $stmt = $this->pdo->prepare("
                SELECT s.*, 
                       c.full_name as client_name, c.phone as client_phone, 
                       c.email as client_email, c.id_number as client_id_number,
                       c.address as client_address,
                       p.plot_number, p.size as plot_size, p.section,
                       pr.project_name, pr.location as project_location,
                       u.full_name as agent_name
                FROM sales s
                JOIN clients c ON s.client_id = c.id
                JOIN plots p ON s.plot_id = p.id
                JOIN projects pr ON p.project_id = pr.id
                JOIN users u ON s.agent_id = u.id
                WHERE s.id = ?
            ");
            $stmt->execute([$entityId]);
            $data = $stmt->fetch();
            break;        case 'client':
            $stmt = $this->pdo->prepare("
                SELECT c.*, 
                       u.full_name as agent_name,
                       COUNT(DISTINCT s.id) as total_purchases,
                       SUM(s.sale_price) as total_investment
                FROM clients c
                LEFT JOIN users u ON c.assigned_agent = u.id
                LEFT JOIN sales s ON c.id = s.client_id
                WHERE c.id = ?
                GROUP BY c.id
            ");
            $stmt->execute([$entityId]);
            $data = $stmt->fetch();
            break;        case 'project':
            $stmt = $this->pdo->prepare("
                SELECT pr.*,
                       COUNT(DISTINCT p.id) as total_plots,
                       COUNT(DISTINCT CASE WHEN p.status = 'sold' THEN p.id END) as sold_plots,
                       COUNT(DISTINCT CASE WHEN p.status = 'available' THEN p.id END) as available_plots
                FROM projects pr
                LEFT JOIN plots p ON pr.id = p.project_id
                WHERE pr.id = ?
                GROUP BY pr.id
            ");
            $stmt->execute([$entityId]);
            $data = $stmt->fetch();
            break;
    }    // Add company data
    $settings = $this->getSettings();
    $data['company_name'] = $settings['site_name'];
    $data['company_email'] = $settings['contact_email'];
    $data['company_phone'] = $settings['contact_phone'];
    $data['company_address'] = $settings['contact_address'];    // Add current date
    $data['current_date'] = date('F d, Y');
    $data['current_time'] = date('h:i A');    return $data;
}/**
 * Replace template variables
 */
private function replaceTemplateVariables($content, $data) {
    foreach ($data as $key => $value) {
        if (is_numeric($value) && strpos($key, 'amount') !== false || strpos($key, 'price') !== false) {
            $value = 'KES ' . number_format($value, 2);
        }        $content = str_replace('{{' . $key . '}}', $value, $content);
    }    // Handle conditional sections
    $content = $this->processConditionals($content, $data);    // Handle loops
    $content = $this->processLoops($content, $data);    return $content;
}/**
 * Process conditional sections in template
 */
private function processConditionals($content, $data) {
    preg_match_all('/{{#if\s+(.+?)}}(.*?){{\/if}}/s', $content, $matches);    foreach ($matches[0] as $index => $match) {
        $condition = $matches[1][$index];
        $conditionalContent = $matches[2][$index];        if ($this->evaluateCondition($condition, $data)) {
            $content = str_replace($match, $conditionalContent, $content);
        } else {
            $content = str_replace($match, '', $content);
        }
    }    return $content;
}/**
 * Process loops in template
 */
private function processLoops($content, $data) {
    preg_match_all('/{{#each\s+(.+?)}}(.*?){{\/each}}/s', $content, $matches);    foreach ($matches[0] as $index => $match) {
        $arrayKey = $matches[1][$index];
        $loopContent = $matches[2][$index];        if (isset($data[$arrayKey]) && is_array($data[$arrayKey])) {
            $output = '';
            foreach ($data[$arrayKey] as $item) {
                $itemContent = $loopContent;
                foreach ($item as $key => $value) {
                    $itemContent = str_replace('{{' . $key . '}}', $value, $itemContent);
                }
                $output .= $itemContent;
            }
            $content = str_replace($match, $output, $content);
        } else {
            $content = str_replace($match, '', $content);
        }
    }    return $content;
}/**
 * Generate PDF from HTML content
 */
private function generatePDF($htmlContent, $entityType, $entityId) {
    require_once __DIR__ . '/../../vendor/autoload.php'; // Assuming TCPDF or mPDF is installed    // Using TCPDF as example
    $pdf = new \TCPDF();
    $pdf->SetCreator('Zuri CRM');
    $pdf->SetAuthor('Zuri Real Estate');
    $pdf->SetTitle('Document');    $pdf->AddPage();
    $pdf->writeHTML($htmlContent, true, false, true, false, '');    $filename = $entityType . '_' . $entityId . '_' . time() . '.pdf';
    $filepath = __DIR__ . '/../../uploads/documents/' . $filename;    $pdf->Output($filepath, 'F');    return '/uploads/documents/' . $filename;
}/**
 * Generate DOCX from content
 */
private function generateDOCX($content, $entityType, $entityId) {
    // Using PHPWord library
    require_once __DIR__ . '/../../vendor/autoload.php';    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();    // Parse HTML and add to Word document
    \PhpOffice\PhpWord\Shared\Html::addHtml($section, $content);    $filename = $entityType . '_' . $entityId . '_' . time() . '.docx';
    $filepath = __DIR__ . '/../../uploads/documents/' . $filename;    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save($filepath);    return '/uploads/documents/' . $filename;
}/**
 * Save HTML content
 */
private function saveHTML($content, $entityType, $entityId) {
    $filename = $entityType . '_' . $entityId . '_' . time() . '.html';
    $filepath = __DIR__ . '/../../uploads/documents/' . $filename;    file_put_contents($filepath, $content);    return '/uploads/documents/' . $filename;
}/**
 * Get settings
 */
private function getSettings() {
    $stmt = $this->pdo->query("SELECT * FROM settings WHERE id = 1");
    return $stmt->fetch();
}/**
 * Evaluate condition
 */
private function evaluateCondition($condition, $data) {
    // Simple condition evaluation
    // Format: variable == value or variable != value
    if (strpos($condition, '==') !== false) {
        list($var, $val) = explode('==', $condition);
        $var = trim($var);
        $val = trim($val, ' "\'');
        return isset($data[$var]) && $data[$var] == $val;
    } elseif (strpos($condition, '!=') !== false) {
        list($var, $val) = explode('!=', $condition);
        $var = trim($var);
        $val = trim($val, ' "\'');
        return isset($data[$var]) && $data[$var] != $val;
    } else {
        // Just check if variable exists and is truthy
        $var = trim($condition);
        return isset($data[$var]) && $data[$var];
    }
}/**
 * Add digital signature to document
 */
public function addSignature($documentId, $signatureData) {
    $stmt = $this->pdo->prepare("
        UPDATE generated_documents 
        SET signature_status = 'signed',
            signature_data = ?,
            signed_at = NOW()
        WHERE id = ?
    ");    return $stmt->execute([$signatureData, $documentId]);
}/**
 * Create sale agreement
 */
public function createSaleAgreement($saleId) {
    // Get sale agreement template
    $stmt = $this->pdo->prepare("SELECT id FROM document_templates WHERE type = 'sale_agreement' LIMIT 1");
    $stmt->execute();
    $template = $stmt->fetch();    if ($template) {
        return $this->generateFromTemplate($template['id'], 'sale', $saleId);
    }    return false;
}/**
 * Create receipt
 */
public function createReceipt($paymentId) {
    $stmt = $this->pdo->prepare("
        SELECT p.*, s.id as sale_id, s.sale_price, s.balance,
               c.full_name as client_name, c.phone as client_phone,
               pl.plot_number, pr.project_name
        FROM payments p
        JOIN sales s ON p.sale_id = s.id
        JOIN clients c ON s.client_id = c.id
        JOIN plots pl ON s.plot_id = pl.id
        JOIN projects pr ON pl.project_id = pr.id
        WHERE p.id = ?
    ");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();    if (!$payment) {
        return false;
    }    // Generate receipt HTML
    $html = $this->generateReceiptHTML($payment);    // Convert to PDF
    return $this->generatePDF($html, 'receipt', $paymentId);
}/**
 * Generate receipt HTML
 */
private function generateReceiptHTML($payment) {
    $settings = $this->getSettings();    $html = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .header { text-align: center; margin-bottom: 30px; }
            .receipt-title { font-size: 24px; font-weight: bold; margin: 20px 0; }
            .info-table { width: 100%; margin: 20px 0; }
            .info-table td { padding: 8px; }
            .amount { font-size: 20px; font-weight: bold; color: #0C3807; }
            .footer { margin-top: 50px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>' . $settings['site_name'] . '</h1>
            <p>' . $settings['contact_phone'] . ' | ' . $settings['contact_email'] . '</p>
            <div class="receipt-title">PAYMENT RECEIPT</div>
        </div>        <table class="info-table">
            <tr>
                <td><strong>Receipt No:</strong></td>
                <td>' . str_pad($payment['id'], 6, '0', STR_PAD_LEFT) . '</td>
                <td><strong>Date:</strong></td>
                <td>' . date('F d, Y', strtotime($payment['payment_date'])) . '</td>
            </tr>
            <tr>
                <td><strong>Client:</strong></td>
                <td>' . $payment['client_name'] . '</td>
                <td><strong>Phone:</strong></td>
                <td>' . $payment['client_phone'] . '</td>
            </tr>
            <tr>
                <td><strong>Project:</strong></td>
                <td>' . $payment['project_name'] . '</td>
                <td><strong>Plot No:</strong></td>
                <td>' . $payment['plot_number'] . '</td>
            </tr>
            <tr>
                <td><strong>Payment Method:</strong></td>
                <td>' . ucfirst(str_replace('_', ' ', $payment['payment_method'])) . '</td>
                <td><strong>Reference:</strong></td>
                <td>' . ($payment['reference_number'] ?: 'N/A') . '</td>
            </tr>
        </table>        <div style="text-align: center; margin: 30px 0;">
            <div class="amount">Amount Paid: KES ' . number_format($payment['amount'], 2) . '</div>
        </div>        <table class="info-table">
            <tr>
                <td><strong>Total Price:</strong></td>
                <td>KES ' . number_format($payment['sale_price'], 2) . '</td>
            </tr>
            <tr>
                <td><strong>Balance:</strong></td>
                <td>KES ' . number_format($payment['balance'], 2) . '</td>
            </tr>
        </table>        <div class="footer">
            <p>Thank you for your payment!</p>
            <p style="font-size: 12px; color: #666;">This is a computer generated receipt</p>
        </div>
    </body>
    </html>';    return $html;
}
}
</artifact>
</artifacts>
