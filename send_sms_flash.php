<?php
/**
 * SMPP Flash SMS Sender (via HTTP GET)
 *
 * This PHP script allows you to send **Flash SMS** messages using the SMPP protocol.
 * It connects to an SMPP server, authenticates as a transmitter, sends a Class 0 SMS
 * (Flash), and then cleanly disconnects.
 *
 * ✅ Supports: 
 *   - Flash SMS (GSM Class 0 via esm_class 0x10 and data_coding 0x10)
 *   - Logging each request to `sms_access.log`
 *   - Takes input from GET parameters:
 *       - `to`: Recipient number (e.g., 923001234567)
 *       - `message`: Message body (max 160 chars for GSM 7-bit)
 *
 * 🔐 Configuration:
 *   - Edit SMPP credentials and host settings in the configuration section
 *
 * 📜 Example Usage:
 *   http://your-server/send_sms_flash.php?to=13001234567&message=Hello+Flash+World
 *
 * 📁 Logs:
 *   - Logs every message attempt (success/failure) to `sms_access.log`
 *
 * 🛠 Author: Adeel Ahmed
 * 📅 Last Updated: 2025-07-21
 * 🔗 License: MIT
*/
function smpp_pack_string($str) {
    return $str . "\0";
}

function smpp_pdu_header($command_id, $sequence_number, $body) {
    $length = 16 + strlen($body);
    return pack("NNNN", $length, $command_id, 0x00000000, $sequence_number) . $body;
}

function hex_dump($data) {
    return implode(' ', str_split(bin2hex($data), 2));
}

function send_pdu($socket, $pdu, $label = '') {
    fwrite($socket, $pdu);
    $response = fread($socket, 2048);
    //echo "📥 [$label] Response (hex): " . hex_dump($response) . "\n";

    if (strlen($response) < 16) {
        //echo "❌ Invalid response (too short).\n";
        return false;
    }

    $header = unpack("Nlength/Ncommand_id/Ncommand_status/Nsequence_number", substr($response, 0, 16));
    if ($header['command_status'] !== 0x00000000) {
        //echo "❌ SMPP Error: Command status = " . sprintf("0x%08X", $header['command_status']) . "\n";
        return false;
    }

    return true;
}

function log_access($status, $recipient, $message) {
    $logFile = __DIR__ . '/sms_access.log';
    $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $time    = date('Y-m-d H:i:s');
    $logLine = "[$time] IP: $ip | To: $recipient | Message: " . substr($message, 0, 100) . " | Status: $status\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
}

// ===========================
// YOUR SMPP CONFIG
// ===========================
$host = 'smpp.host.ip';
$port = 123456; //smpp port
$system_id = 'smpp_username';
$password = 'smpp_password';
$system_type = 'SMPP';
$sender = 'sender';
$seq = 1;

// ===========================
// GET Parameters
// ===========================
$recipient = $_GET['to'] ?? '';
$message   = $_GET['message'] ?? '';

// ===========================
// CONNECT
// ===========================
//echo "🔌 Connecting to $host:$port...\n";
$socket = fsockopen($host, $port, $errno, $errstr, 10);
if (!$socket) {
    die("❌ Connection failed: $errstr ($errno)\n");
}
//echo "✅ Connected\n";

// ===========================
// BIND_TRANSMITTER
// ===========================
$bind_body =
    smpp_pack_string($system_id) .
    smpp_pack_string($password) .
    smpp_pack_string($system_type) .
    chr(0x34) . // SMPP v3.4
    chr(0x00) . // addr_ton
    chr(0x01) . // addr_npi
    smpp_pack_string(""); // address-range

$bind_pdu = smpp_pdu_header(0x00000002, $seq++, $bind_body); // bind_transmitter
$bindSuccess   = send_pdu($socket, $bind_pdu, "BIND_TRANSMITTER");

// ===========================
// SUBMIT_SM
// ===========================
$submit_body =
    chr(0x00) .                          // service_type
    chr(0x00) .                          // source_addr_ton
    chr(0x01) .                          // source_addr_npi
    smpp_pack_string($sender) .         // source_addr
    chr(0x01) .                          // dest_addr_ton
    chr(0x01) .                          // dest_addr_npi
    smpp_pack_string($recipient) .      // destination_addr
    chr(0x10) .                          // esm_class: 0x10 = Flash SMS
    chr(0x00) .                          // protocol_id
    chr(0x00) .                          // priority_flag
    smpp_pack_string("") .              // schedule_delivery_time
    smpp_pack_string("") .              // validity_period
    chr(0x01) .                          // registered_delivery
    chr(0x00) .                          // replace_if_present_flag
    chr(0x10) .                          // data_coding: 0x10 = Flash SMS (GSM 7-bit Class 0)
    chr(0x00) .                          // sm_default_msg_id
    chr(strlen($message)) .             // sm_length
    $message;

$submit_pdu = smpp_pdu_header(0x00000004, $seq++, $submit_body);
$submitSuccess = send_pdu($socket, $submit_pdu, "SUBMIT_SM");

// ===========================
// UNBIND
// ===========================
$unbind_pdu = smpp_pdu_header(0x00000006, $seq++, '');
$unbindSuccess = send_pdu($socket, $unbind_pdu, "UNBIND");

fclose($socket);
//echo "✅ Disconnected\n";

// Final Status
if ($bindSuccess && $submitSuccess && $unbindSuccess) {
    log_access("SMS sent", $recipient, $message);
	echo "Sent";
} else {
    log_access("SMS failed", $recipient, $message);
	echo "Failed";
}
