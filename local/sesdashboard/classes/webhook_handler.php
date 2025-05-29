<?php
namespace local_sesdashboard;

defined('MOODLE_INTERNAL') || die();

use local_sesdashboard\util\logger;

class webhook_handler {
    public function handle_notification($payload) {
        global $DB;
        
        // Start transaction for database operations
        $transaction = $DB->start_delegated_transaction();
        
        try {
            logger::debug('Attempting to decode payload');
            $data = json_decode($payload, true);
            if (!$data) {
                logger::error('Invalid JSON payload - ' . json_last_error_msg());
                debugging(get_string('log_invalid_payload', 'local_sesdashboard'), DEBUG_NORMAL);
                return false;
            }

            logger::debug('Decoded payload - ' . json_encode($data));
            debugging(get_string('log_webhook_received', 'local_sesdashboard', json_encode($data)), DEBUG_DEVELOPER);

            // Check if this is an SNS message or direct SES event
            if (isset($data['Type'])) {
                // Handle SNS message
                logger::info('Processing SNS message type - ' . $data['Type']);
                switch ($data['Type']) {
                    case 'SubscriptionConfirmation':
                        logger::info('Processing subscription confirmation');
                        if (isset($data['SubscribeURL'])) {
                            logger::info('Attempting to confirm subscription at ' . $data['SubscribeURL']);
                            $response = file_get_contents($data['SubscribeURL']);
                            if (!empty($response)) {
                                logger::info('Subscription confirmed successfully');
                                debugging(get_string('log_subscription_confirmed', 'local_sesdashboard'), DEBUG_NORMAL);
                                $transaction->allow_commit();
                                return true;
                            }
                            logger::error('Empty response from SubscribeURL');
                        }
                        logger::error('Missing SubscribeURL');
                        break;
                        
                    case 'Notification':
                        logger::info('Processing SNS notification message');
                        if (isset($data['Message'])) {
                            $message = json_decode($data['Message'], true);
                            if (!$message) {
                                logger::error('Failed to decode SNS message payload');
                                $transaction->rollback(new \Exception('Failed to decode SNS message payload'));
                                return false;
                            }
                            $result = $this->process_ses_event($message);
                            if ($result) {
                                $transaction->allow_commit();
                            } else {
                                $transaction->rollback(new \Exception('Failed to process SES event'));
                            }
                            return $result;
                        }
                        logger::error('Missing Message field in SNS notification');
                        break;
                    
                    default:
                        logger::error('Unknown SNS message type: ' . $data['Type']);
                        break;
                }
            } else if (isset($data['eventType'])) {
                // Handle direct SES event
                logger::info('Processing direct SES event');
                $result = $this->process_ses_event($data);
                if ($result) {
                    $transaction->allow_commit();
                } else {
                    $transaction->rollback(new \Exception('Failed to process direct SES event'));
                }
                return $result;
            } else {
                logger::error('Invalid payload format - missing Type or eventType');
            }
            
            $transaction->rollback(new \Exception('Failed to process webhook notification'));
            return false;
        } catch (\Exception $e) {
            // Rollback on error
            $transaction->rollback($e);
            logger::error('Transaction failed: ' . $e->getMessage());
            error_log('Transaction failed: ' . $e->getMessage());
            return false;
        }
    }

    private function process_ses_event($event) {
        global $DB;
        logger::info('Processing SES event type: ' . $event['eventType']);
        
        try {
            // Extract common email data
            $mail = $event['mail'];
            $message_id = $mail['messageId'];
            $timestamp = $mail['timestamp'];
            $source = $mail['source'];
            $destination = implode(', ', $mail['destination']);
            $subject = $mail['commonHeaders']['subject'];
            
            // Create mail record with correct field names
            $mail_record = new \stdClass();
            $mail_record->messageid = $message_id; // Changed from message_id to messageid
            $mail_record->email = $destination; // Changed from destination to email
            $mail_record->subject = $subject;
            $mail_record->status = $event['eventType']; // Added status field
            $mail_record->eventtype = $event['eventType']; // Added eventtype field
            $mail_record->timecreated = time(); // Added timecreated field
            
            // Insert mail record
            try {
                $mail_id = $DB->insert_record('local_sesdashboard_mail', $mail_record);
                logger::debug('Inserted mail record with ID: ' . $mail_id);
            } catch (\Exception $e) {
                logger::error('Failed to insert mail record: ' . $e->getMessage());
                logger::error('Mail record data: ' . json_encode($mail_record));
                // Continue processing to try events table
            }
            
            // Process based on event type
            switch ($event['eventType']) {
                case 'Send':
                    logger::info('Processing Send event for message: ' . $message_id);
                    $record = new \stdClass();
                    $record->message_id = $message_id;
                    $record->event_type = 'Send';
                    $record->timestamp = strtotime($timestamp);
                    $record->source = $source;
                    $record->destination = $destination;
                    $record->subject = $subject;
                    $DB->insert_record('local_sesdashboard_events', $record);
                    logger::info('Successfully recorded Send event');
                    return true;

                case 'Delivery':
                    logger::info('Processing Delivery event for message: ' . $message_id);
                    $delivery = $event['delivery'];
                    $record = new \stdClass();
                    $record->message_id = $message_id;
                    $record->event_type = 'Delivery';
                    $record->timestamp = strtotime($delivery['timestamp']);
                    $record->source = $source;
                    $record->destination = $destination;
                    $record->subject = $subject;
                    $record->processing_time = $delivery['processingTimeMillis'];
                    $record->smtp_response = $delivery['smtpResponse'];
                    $record->remote_mta_ip = $delivery['remoteMtaIp'];
                    $record->reporting_mta = $delivery['reportingMTA'];
                    $DB->insert_record('local_sesdashboard_events', $record);
                    logger::info('Successfully recorded Delivery event');
                    return true;

                case 'Open':
                    logger::info('Processing Open event for message: ' . $message_id);
                    $record = new \stdClass();
                    $record->message_id = $message_id;
                    $record->event_type = 'Open';
                    $record->timestamp = strtotime($event['open']['timestamp']);
                    $record->source = $source;
                    $record->destination = $destination;
                    $record->subject = $subject;
                    $record->user_agent = $event['open']['userAgent'];
                    $record->ip_address = $event['open']['ipAddress'];
                    $DB->insert_record('local_sesdashboard_events', $record);
                    logger::info('Successfully recorded Open event');
                    return true;

                case 'Click':
                    logger::info('Processing Click event for message: ' . $message_id);
                    $click = $event['click'];
                    $record = new \stdClass();
                    $record->message_id = $message_id;
                    $record->event_type = 'Click';
                    $record->timestamp = strtotime($click['timestamp']);
                    $record->source = $source;
                    $record->destination = $destination;
                    $record->subject = $subject;
                    $record->user_agent = $click['userAgent'];
                    $record->ip_address = $click['ipAddress'];
                    $record->link = $click['link'];
                    $DB->insert_record('local_sesdashboard_events', $record);
                    logger::info('Successfully recorded Click event');
                    return true;

                case 'Bounce':
                    logger::info('Processing Bounce event for message: ' . $message_id);
                    $bounce = $event['bounce'];
                    $record = new \stdClass();
                    $record->message_id = $message_id;
                    $record->event_type = 'Bounce';
                    $record->timestamp = strtotime($bounce['timestamp']);
                    $record->source = $source;
                    $record->destination = $destination;
                    $record->subject = $subject;
                    $record->bounce_type = $bounce['bounceType'];
                    $record->bounce_subtype = $bounce['bounceSubType'];
                    $record->diagnostic_code = isset($bounce['diagnosticCode']) ? $bounce['diagnosticCode'] : null;
                    $record->reporting_mta = isset($bounce['reportingMTA']) ? $bounce['reportingMTA'] : null;
                    $DB->insert_record('local_sesdashboard_events', $record);
                    logger::info('Successfully recorded Bounce event');
                    return true;

                case 'Complaint':
                    logger::info('Processing Complaint event for message: ' . $message_id);
                    $complaint = $event['complaint'];
                    $record = new \stdClass();
                    $record->message_id = $message_id;
                    $record->event_type = 'Complaint';
                    $record->timestamp = strtotime($complaint['timestamp']);
                    $record->source = $source;
                    $record->destination = $destination;
                    $record->subject = $subject;
                    $record->complaint_feedback_type = isset($complaint['complaintFeedbackType']) ? $complaint['complaintFeedbackType'] : null;
                    $record->feedback_id = isset($complaint['feedbackId']) ? $complaint['feedbackId'] : null;
                    $DB->insert_record('local_sesdashboard_events', $record);
                    logger::info('Successfully recorded Complaint event');
                    return true;

                case 'DeliveryDelay':
                    logger::info('Processing Delivery Delay event for message: ' . $message_id);
                    $delay = $event['deliveryDelay'];
                    $record = new \stdClass();
                    $record->message_id = $message_id;
                    $record->event_type = 'DeliveryDelay';
                    $record->timestamp = strtotime($delay['timestamp']);
                    $record->source = $source;
                    $record->destination = $destination;
                    $record->subject = $subject;
                    $record->delay_type = $delay['delayType'];
                    $record->delay_duration = isset($delay['delayDuration']) ? $delay['delayDuration'] : null;
                    $record->reporting_mta = isset($delay['reportingMTA']) ? $delay['reportingMTA'] : null;
                    $DB->insert_record('local_sesdashboard_events', $record);
                    logger::info('Successfully recorded Delivery Delay event');
                    return true;

                case 'Reject':
                    logger::info('Processing Reject event for message: ' . $message_id);
                    $reject = $event['reject'];
                    $record = new \stdClass();
                    $record->message_id = $message_id;
                    $record->event_type = 'Reject';
                    $record->timestamp = strtotime($timestamp);
                    $record->source = $source;
                    $record->destination = $destination;
                    $record->subject = $subject;
                    $record->reason = $reject['reason'];
                    $DB->insert_record('local_sesdashboard_events', $record);
                    logger::info('Successfully recorded Reject event');
                    return true;

                case 'RenderingFailure':
                    logger::info('Processing Rendering Failure event for message: ' . $message_id);
                    $failure = $event['renderingFailure'];
                    $record = new \stdClass();
                    $record->message_id = $message_id;
                    $record->event_type = 'RenderingFailure';
                    $record->timestamp = strtotime($timestamp);
                    $record->source = $source;
                    $record->destination = $destination;
                    $record->subject = $subject;
                    $record->error_message = $failure['errorMessage'];
                    $record->template_name = isset($failure['templateName']) ? $failure['templateName'] : null;
                    $DB->insert_record('local_sesdashboard_events', $record);
                    logger::info('Successfully recorded Rendering Failure event');
                    return true;

                default:
                    logger::error('Unsupported event type: ' . $event['eventType']);
                    return false;
            }
        } catch (\Exception $e) {
            logger::error('Failed to process event: ' . $e->getMessage());
            error_log('Failed to process event: ' . $e->getMessage());
            return false;
        }
    }
    
    // Additional helper methods can be added here if needed
    
    /**
     * Debug method to log database operations
     * @param string $message The message to log
     * @param mixed $data Optional data to include in the log
     */
    private function log_db_operation($message, $data = null) {
        logger::debug($message . ($data !== null ? ' - ' . json_encode($data) : ''));
        error_log($message . ($data !== null ? ' - ' . json_encode($data) : ''));
    }
}