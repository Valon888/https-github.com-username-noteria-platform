<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Handle AJAX request to save abonim data in session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_abonim') {
    // Initialize selected_abonim array if it doesn't exist
    if (!isset($_SESSION['selected_abonim']) || !is_array($_SESSION['selected_abonim'])) {
        $_SESSION['selected_abonim'] = [];
    }
    
    // Save abonim data in session as a structured array
    if (isset($_POST['abonim_id'])) {
        $_SESSION['selected_abonim']['id'] = $_POST['abonim_id'];
    }
    
    if (isset($_POST['abonim_price'])) {
        $_SESSION['selected_abonim']['price'] = floatval($_POST['abonim_price']);
    }
    
    if (isset($_POST['abonim_name'])) {
        $_SESSION['selected_abonim']['name'] = $_POST['abonim_name'];
    }
    
    if (isset($_POST['payment_method'])) {
        $_SESSION['selected_abonim']['payment_method'] = $_POST['payment_method'];
        $_SESSION['payment_method'] = $_POST['payment_method']; // Keeping for backward compatibility
    }
    
    // Return success response
    echo json_encode(['success' => true, 'message' => 'Abonim data saved in session']);
    exit;
}

// If not a valid request, return error
echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit;