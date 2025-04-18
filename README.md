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

2. **Install dependencies**
  ```bash
  composer install

3. **Configure your AMI | Database connection in config.php**
  ```php
  $ami_host = '';
  $ami_port = ;
  $ami_username = '';
  $ami_password = '';

  $db_host = '';
  $db_name = '';
  $db_username = '';
  $db_password = '';
  $tbl_name = '';

4. **(Optional) Set notification URL (Nodejs Push Notification)**
  ```php
  $url = 'http://your-server.com/notify';

5. **Run The Script**
  ```php
  php ami-manager.php

**If You Need to Run This Service as a Daemon, Do This**

### 5.1. Edit `ami-manager.service` File

Edit the `ExecStart` path to Code to your actual PHP script location.

```ini
  [Unit]
  Description=Call Logger Service
  After=network.target

  [Service]
  ExecStart=/usr/bin/php /path-to-code/ami-manager/index.php
  Restart=always
  User=root
  Group=root
  StandardOutput=append:/var/log/ami-manager.log
  StandardError=append:/var/log/ami-manager.log

  [Install]
  WantedBy=multi-user.target

### 5.2. Copy And Enable Service
```bash
  cp ami-manager.service /etc/systemd/system/
  systemctl daemon-reload
  systemctl enable ami-manager.service
  systemctl start ami-manager.service

