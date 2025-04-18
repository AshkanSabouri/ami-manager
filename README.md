# 📞 Asterisk AMI Logger with MySQL & Notification

This PHP script connects to the Asterisk AMI (Asterisk Manager Interface), listens for call events, parses relevant information, logs the call data into a MySQL database, and optionally sends notifications to a Node.js endpoint.

## 🚀 Features

- Realtime connection to Asterisk AMI
- Parses call events:
  - CallerIDName
  - CallerIDNum
  - ConnectedLineName
  - ConnectedLineNum
  - Uniqueid
- Determines call type: ورودی (incoming), خروجی (outgoing), داخلی (internal)
- Skips incomplete or unknown calls
- Converts Gregorian date to Jalali format using `morilog/jalali`
- Sends JSON event to external webhook for notifications
- Prevents duplicate logs using `Uniqueid`
- Matches caller names from `tblcontacts` or `tblclients` by phone number
- Logs events and errors to file

## 🧑‍💻 Requirements

- PHP 7.4+
- Composer
- MySQL or MariaDB
- Enabled extensions: `mysqli`, `mbstring`, `json`, `openssl`
- Asterisk AMI enabled

## 📦 Installation

1. **Clone the repository**  
   ```bash
   git clone https://github.com/your-username/ami-manager.git
   cd ami-manager
