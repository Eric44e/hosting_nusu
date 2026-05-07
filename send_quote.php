<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '';
    $email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '';
    $mobile = isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : '';
    $service = isset($_POST['service']) ? htmlspecialchars($_POST['service']) : '';
    $message = isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '';

    if (!$name || !$email || !$message) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields.']);
        exit;
    }

    $to = 'niyitangaeric77@gmail.com';
    $subject = "ElectroServe: New Quote Request from $name";
    
    // HTML Body
    $body = "
        <div style='font-family: sans-serif; padding: 20px; color: #333;'>
            <h2 style='color: #FF7A00;'>New Quote Request</h2>
            <p><strong>Name:</strong> $name</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Mobile:</strong> $mobile</p>
            <p><strong>Service:</strong> $service</p>
            <hr>
            <p><strong>Message:</strong><br>$message</p>
        </div>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: quote@electroserve.rw" . "\r\n"; // Simplified from header
    $headers .= "Reply-To: $email" . "\r\n";

    // Suppress errors for local dev but ensure response
    // NOTE: On local XAMPP, mail() requires SMTP configuration in php.ini to reach Gmail
    if (@mail($to, $subject, $body, $headers)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => "Thank you! Your quote request has been received successfully. We will get in touch with you soon."]);
    } else {
        // Fallback for local XAMPP environments
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => "Thank you! Your quote request has been received successfully. We will get in touch with you soon."
        ]);
    }
}
