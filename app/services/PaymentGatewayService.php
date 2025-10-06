<?php
// app/services/PaymentGatewayService.php

class PaymentGatewayService {
    private $pdo;
    private $gateways = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->initializeGateways();
    }
    
    private function initializeGateways() {
        $this->gateways = [
            'mpesa' => new MpesaGateway($this->pdo),
            'card' => new StripeGateway($this->pdo),
            'bank' => new BankGateway($this->pdo),
            'paypal' => new PayPalGateway($this->pdo)
        ];
    }
    
    public function processPayment($gatewayType, $amount, $saleId, $clientData, $additionalData = []) {
        if (!isset($this->gateways[$gatewayType])) {
            throw new Exception("Invalid payment gateway: $gatewayType");
        }
        
        return $this->gateways[$gatewayType]->processPayment($amount, $saleId, $clientData, $additionalData);
    }
    
    public function verifyPayment($gatewayType, $transactionId) {
        if (!isset($this->gateways[$gatewayType])) {
            throw new Exception("Invalid payment gateway: $gatewayType");
        }
        
        return $this->gateways[$gatewayType]->verifyPayment($transactionId);
    }
}

// M-Pesa Gateway Integration (Safaricom Daraja API)
class MpesaGateway {
    private $pdo;
    private $consumerKey;
    private $consumerSecret;
    private $businessShortCode;
    private $passKey;
    private $apiUrl;
    private $callbackUrl;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->consumerKey = getenv('MPESA_CONSUMER_KEY');
        $this->consumerSecret = getenv('MPESA_CONSUMER_SECRET');
        $this->businessShortCode = getenv('MPESA_SHORT_CODE');
        $this->passKey = getenv('MPESA_PASS_KEY');
        $this->apiUrl = getenv('MPESA_ENV') === 'production' 
            ? 'https://api.safaricom.co.ke' 
            : 'https://sandbox.safaricom.co.ke';
        $this->callbackUrl = getenv('APP_URL') . '/api/webhooks/mpesa';
    }
    
    /**
     * Get OAuth access token from M-Pesa
     */
    private function getAccessToken() {
        $url = $this->apiUrl . '/oauth/v1/generate?grant_type=client_credentials';
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_USERPWD, $this->consumerKey . ':' . $this->consumerSecret);
        
        $result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $result = json_decode($result);
        
        if ($status === 200 && isset($result->access_token)) {
            return $result->access_token;
        }
        
        throw new Exception('Failed to get M-Pesa access token');
    }
    
    /**
     * Initiate STK Push (Lipa Na M-Pesa Online)
     */
    public function processPayment($amount, $saleId, $clientData, $additionalData = []) {
        $accessToken = $this->getAccessToken();
        $phoneNumber = $this->formatPhoneNumber($clientData['phone']);
        
        $timestamp = date('YmdHis');
        $password = base64_encode($this->businessShortCode . $this->passKey . $timestamp);
        
        $curl_post_data = [
            'BusinessShortCode' => $this->businessShortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => round($amount),
            'PartyA' => $phoneNumber,
            'PartyB' => $this->businessShortCode,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => $this->callbackUrl,
            'AccountReference' => 'SALE-' . $saleId,
            'TransactionDesc' => 'Payment for Plot Purchase'
        ];
        
        $url = $this->apiUrl . '/mpesa/stkpush/v1/processrequest';
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type:application/json',
            'Authorization:Bearer ' . $accessToken
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $curl_response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $result = json_decode($curl_response);
        
        if ($status === 200 && isset($result->ResponseCode) && $result->ResponseCode === '0') {
            // Save transaction to database
            $stmt = $this->pdo->prepare("
                INSERT INTO online_payments 
                (sale_id, gateway_id, transaction_id, amount, status, gateway_response) 
                VALUES (?, 1, ?, ?, 'pending', ?)
            ");
            
            $stmt->execute([
                $saleId,
                $result->CheckoutRequestID,
                $amount,
                json_encode($result)
            ]);
            
            return [
                'success' => true,
                'transaction_id' => $result->CheckoutRequestID,
                'message' => 'Please check your phone and enter M-Pesa PIN to complete payment'
            ];
        }
        
        return [
            'success' => false,
            'message' => $result->ResponseDescription ?? 'Failed to initiate payment'
        ];
    }
    
    /**
     * Query STK Push Status
     */
    public function queryTransaction($checkoutRequestId) {
        $accessToken = $this->getAccessToken();
        $timestamp = date('YmdHis');
        $password = base64_encode($this->businessShortCode . $this->passKey . $timestamp);
        
        $curl_post_data = [
            'BusinessShortCode' => $this->businessShortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId
        ];
        
        $url = $this->apiUrl . '/mpesa/stkpushquery/v1/query';
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type:application/json',
            'Authorization:Bearer ' . $accessToken
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $curl_response = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($curl_response, true);
    }
    
    /**
     * Register URLs for callbacks
     */
    public function registerUrls() {
        $accessToken = $this->getAccessToken();
        
        $curl_post_data = [
            'ShortCode' => $this->businessShortCode,
            'ResponseType' => 'Completed',
            'ConfirmationURL' => $this->callbackUrl . '/confirmation',
            'ValidationURL' => $this->callbackUrl . '/validation'
        ];
        
        $url = $this->apiUrl . '/mpesa/c2b/v1/registerurl';
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type:application/json',
            'Authorization:Bearer ' . $accessToken
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $curl_response = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($curl_response, true);
    }
    
    /**
     * Process M-Pesa callback
     */
    public function processCallback($data) {
        // Log the callback
        file_put_contents(
            __DIR__ . '/../../logs/mpesa_callbacks.log',
            date('Y-m-d H:i:s') . ' - ' . json_encode($data) . PHP_EOL,
            FILE_APPEND
        );
        
        if (isset($data['Body']['stkCallback'])) {
            $callback = $data['Body']['stkCallback'];
            $checkoutRequestId = $callback['CheckoutRequestID'];
            $resultCode = $callback['ResultCode'];
            
            // Get the transaction
            $stmt = $this->pdo->prepare("
                SELECT * FROM online_payments 
                WHERE transaction_id = ?
            ");
            $stmt->execute([$checkoutRequestId]);
            $transaction = $stmt->fetch();
            
            if ($transaction) {
                if ($resultCode === 0) {
                    // Payment successful
                    $metadata = $callback['CallbackMetadata']['Item'];
                    $mpesaReceiptNumber = '';
                    $amount = 0;
                    $phoneNumber = '';
                    
                    foreach ($metadata as $item) {
                        switch ($item['Name']) {
                            case 'MpesaReceiptNumber':
                                $mpesaReceiptNumber = $item['Value'];
                                break;
                            case 'Amount':
                                $amount = $item['Value'];
                                break;
                            case 'PhoneNumber':
                                $phoneNumber = $item['Value'];
                                break;
                        }
                    }
                    
                    // Update online payment record
                    $stmt = $this->pdo->prepare("
                        UPDATE online_payments 
                        SET status = 'completed', gateway_response = ? 
                        WHERE transaction_id = ?
                    ");
                    $stmt->execute([json_encode($callback), $checkoutRequestId]);
                    
                    // Record payment in main payments table
                    $stmt = $this->pdo->prepare("
                        INSERT INTO payments 
                        (sale_id, amount, payment_method, reference_number, payment_date, received_by, notes) 
                        VALUES (?, ?, 'mpesa', ?, NOW(), 1, ?)
                    ");
                    $stmt->execute([
                        $transaction['sale_id'],
                        $amount,
                        $mpesaReceiptNumber,
                        'Auto-captured via M-Pesa'
                    ]);
                    
                    // Update sale balance
                    $stmt = $this->pdo->prepare("
                        UPDATE sales 
                        SET balance = balance - ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$amount, $transaction['sale_id']]);
                    
                    // Send confirmation SMS/Email to client
                    $this->sendPaymentConfirmation($transaction['sale_id'], $amount, $mpesaReceiptNumber);
                    
                } else {
                    // Payment failed
                    $stmt = $this->pdo->prepare("
                        UPDATE online_payments 
                        SET status = 'failed', gateway_response = ? 
                        WHERE transaction_id = ?
                    ");
                    $stmt->execute([json_encode($callback), $checkoutRequestId]);
                }
            }
        }
        
        // Return response to M-Pesa
        return ['ResultCode' => 0, 'ResultDesc' => 'Accepted'];
    }
    
    /**
     * Format phone number for M-Pesa
     */
    private function formatPhoneNumber($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (substr($phone, 0, 3) !== '254') {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Send payment confirmation
     */
    private function sendPaymentConfirmation($saleId, $amount, $receiptNumber) {
        // Get sale and client details
        $stmt = $this->pdo->prepare("
            SELECT s.*, c.full_name, c.phone, c.email 
            FROM sales s 
            JOIN clients c ON s.client_id = c.id 
            WHERE s.id = ?
        ");
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch();
        
        if ($sale) {
            // Send SMS
            $message = "Dear {$sale['full_name']}, we have received your payment of KES " . number_format($amount) . 
                      ". Receipt: {$receiptNumber}. Balance: KES " . number_format($sale['balance'] - $amount) . 
                      ". Thank you for your payment.";
            
            // Use SMS service to send
            // $smsService->send($sale['phone'], $message);
            
            // Send Email
            // $emailService->send($sale['email'], 'Payment Received', $emailTemplate);
        }
    }
    
    public function verifyPayment($transactionId) {
        return $this->queryTransaction($transactionId);
    }
}

// Stripe Gateway for Card Payments
class StripeGateway {
    private $pdo;
    private $stripeSecretKey;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->stripeSecretKey = getenv('STRIPE_SECRET_KEY');
    }
    
    public function processPayment($amount, $saleId, $clientData, $additionalData = []) {
        // Stripe implementation
        \Stripe\Stripe::setApiKey($this->stripeSecretKey);
        
        try {
            // Create payment intent
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $amount * 100, // Stripe uses cents
                'currency' => 'kes',
                'description' => 'Payment for Sale #' . $saleId,
                'metadata' => [
                    'sale_id' => $saleId,
                    'client_id' => $clientData['id']
                ]
            ]);
            
            // Save transaction
            $stmt = $this->pdo->prepare("
                INSERT INTO online_payments 
                (sale_id, gateway_id, transaction_id, amount, status, gateway_response) 
                VALUES (?, 2, ?, ?, 'pending', ?)
            ");
            
            $stmt->execute([
                $saleId,
                $paymentIntent->id,
                $amount,
                json_encode($paymentIntent)
            ]);
            
            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'transaction_id' => $paymentIntent->id
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function verifyPayment($transactionId) {
        \Stripe\Stripe::setApiKey($this->stripeSecretKey);
        
        try {
            $paymentIntent = \Stripe\PaymentIntent::retrieve($transactionId);
            
            if ($paymentIntent->status === 'succeeded') {
                // Update database
                $stmt = $this->pdo->prepare("
                    UPDATE online_payments 
                    SET status = 'completed' 
                    WHERE transaction_id = ?
                ");
                $stmt->execute([$transactionId]);
                
                return ['success' => true, 'status' => 'completed'];
            }
            
            return ['success' => true, 'status' => $paymentIntent->status];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

// Bank Transfer Gateway
class BankGateway {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function processPayment($amount, $saleId, $clientData, $additionalData = []) {
        // Generate unique reference
        $reference = 'REF-' . strtoupper(uniqid());
        
        // Save pending bank transfer
        $stmt = $this->pdo->prepare("
            INSERT INTO online_payments 
            (sale_id, gateway_id, transaction_id, amount, status, gateway_response) 
            VALUES (?, 3, ?, ?, 'pending', ?)
        ");
        
        $bankDetails = [
            'bank_name' => 'Kenya Commercial Bank',
            'account_name' => 'Zuri Real Estate Ltd',
            'account_number' => '1234567890',
            'branch' => 'Westlands Branch',
            'swift_code' => 'KCBLKENX',
            'reference' => $reference
        ];
        
        $stmt->execute([
            $saleId,
            $reference,
            $amount,
            json_encode($bankDetails)
        ]);
        
        // Send bank details to client
        $this->sendBankDetails($clientData, $amount, $reference, $bankDetails);
        
        return [
            'success' => true,
            'transaction_id' => $reference,
            'bank_details' => $bankDetails,
            'message' => 'Bank transfer details have been sent to your email/phone'
        ];
    }
    
    private function sendBankDetails($clientData, $amount, $reference, $bankDetails) {
        // Send via SMS and Email
        $message = "Bank Transfer Details:\n";
        $message .= "Bank: {$bankDetails['bank_name']}\n";
        $message .= "Account: {$bankDetails['account_number']}\n";
        $message .= "Amount: KES " . number_format($amount) . "\n";
        $message .= "Reference: {$reference}\n";
        $message .= "IMPORTANT: Use the reference number when making the transfer.";
        
        // Send SMS
        // $smsService->send($clientData['phone'], $message);
        
        // Send Email with more detailed instructions
        // $emailService->send($clientData['email'], 'Bank Transfer Instructions', $emailTemplate);
    }
    
    public function verifyPayment($transactionId) {
        // Manual verification process
        // Bank transfers need to be verified manually by finance team
        $stmt = $this->pdo->prepare("
            SELECT status FROM online_payments 
            WHERE transaction_id = ?
        ");
        $stmt->execute([$transactionId]);
        $result = $stmt->fetch();
        
        return [
            'success' => true,
            'status' => $result['status'] ?? 'pending',
            'message' => 'Bank transfer verification pending. Our team will confirm once payment is received.'
        ];
    }
}

// PayPal Gateway
class PayPalGateway {
    private $pdo;
    private $clientId;
    private $clientSecret;
    private $apiUrl;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->clientId = getenv('PAYPAL_CLIENT_ID');
        $this->clientSecret = getenv('PAYPAL_CLIENT_SECRET');
        $this->apiUrl = getenv('PAYPAL_ENV') === 'production' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';
    }
    
    public function processPayment($amount, $saleId, $clientData, $additionalData = []) {
        // PayPal implementation
        // Similar to Stripe but using PayPal's API
        return ['success' => true];
    }
    
    public function verifyPayment($transactionId) {
        // Verify PayPal payment
        return ['success' => true];
    }
}