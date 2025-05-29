<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir . '/adminlib.php');

// Define MOODLE_INTERNAL for core functions
if (!defined('MOODLE_INTERNAL')) {
    define('MOODLE_INTERNAL', true);
}

use local_sesdashboard\util\logger;

// Get raw POST data
$payload = file_get_contents('php://input');
logger::info('Webhook received raw payload: ' . $payload);

if (empty($payload)) {
    logger::error('Empty payload received');
    debugging('Empty payload received', DEBUG_NORMAL);
    http_response_code(400);
    die('No payload received');
}

// Process the notification
try {
    logger::info('Creating webhook handler instance');
    $handler = new \local_sesdashboard\webhook_handler();
    
    logger::info('Attempting to handle notification');
    $result = $handler->handle_notification($payload);
    
    if ($result) {
        logger::info('Notification processed successfully');
        http_response_code(200);
        die('OK');
    } else {
        logger::error('Failed to process notification');
        http_response_code(500);
        die('Failed to process notification');
    }
} catch (Exception $e) {
    logger::error('Exception: ' . $e->getMessage());
    debugging('Exception processing notification: ' . $e->getMessage(), DEBUG_NORMAL);
    http_response_code(500);
    die('Error processing notification');
}