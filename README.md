SMPP SMS Sender (via HTTP GET)

This PHP script allows you to send **SMS** messages using the SMPP protocol.
It connects to an SMPP server, authenticates as a transmitter, sends a Class 0 SMS
, and then cleanly disconnects.

✅ Supports: 
  - SMS (GSM Class 0 via esm_class 0x10 and data_coding 0x10)
  - Logging each request to `sms_access.log`
  - Takes input from GET parameters:
      - `to`: Recipient number (e.g., 923001234567)
      - `message`: Message body (max 160 chars for GSM 7-bit)

🔐 Configuration:
  - Edit SMPP credentials and host settings in the configuration section

📜 Example Usage:
  http://your-server/send_sms.php?to=13001234567&message=Hello+World

📁 Logs:
  - Logs every message attempt (success/failure) to `sms_access.log`

🛠 Author: Adeel Ahmed
📅 Last Updated: 2025-07-21
🔗 License: MIT
