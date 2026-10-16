# pfCentral — Installation & Setup Guide

This guide covers complete installation instructions for running pfCentral via **Docker** or **Bare-Metal** (Linux / Windows).

---

## Method 1: Docker Deployment (Fastest)

### Prerequisites
- Docker Engine 24.0+
- Docker Compose v2.0+

### Steps
1. Clone the repository:
   ```bash
   git clone https://github.com/pfcentral/pfcentral.git
   cd pfcentral
   ```

2. Start the application stack:
   ```bash
   docker-compose up -d
   ```

3. Run the installer inside the container:
   ```bash
   docker exec -it pfcentral-app php spark pfcentral:install
   ```

4. Access the web interface at `http://localhost:8080/login` using the credentials output by the installer.

---

## Method 2: Bare-Metal Installation (Ubuntu / Debian)

### 1. Install System Packages
```bash
sudo apt update
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-curl \
    php8.2-intl php8.2-mbstring php8.2-xml php8.2-zip php8.2-bcmath \
    mariadb-server nginx git curl unzip
```

### 2. Configure Database
```bash
sudo mysql -u root -e "
CREATE DATABASE pfcentral CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pfcentral'@'localhost' IDENTIFIED BY 'SetAStrongPasswordHere123!';
GRANT ALL PRIVILEGES ON pfcentral.* TO 'pfcentral'@'localhost';
FLUSH PRIVILEGES;
"
```

### 3. Deploy Application Code
```bash
sudo git clone https://github.com/pfcentral/pfcentral.git /var/www/pfcentral
cd /var/www/pfcentral
sudo cp .env.example .env
```

Edit `/var/www/pfcentral/.env` with your database credentials:
```ini
CI_ENVIRONMENT = production
app.baseURL = 'https://pfcentral.yourdomain.com'
database.default.hostname = localhost
database.default.database = pfcentral
database.default.username = pfcentral
database.default.password = SetAStrongPasswordHere123!
database.default.DBDriver = MySQLi
```

### 4. Set Permissions
```bash
sudo chown -R www-data:www-data /var/www/pfcentral/writable
sudo chmod -R 775 /var/www/pfcentral/writable
```

### 5. Run the 1-Command Installer
```bash
sudo -u www-data php spark pfcentral:install
```

### 6. Configure Nginx Web Server
Create `/etc/nginx/sites-available/pfcentral`:
```nginx
server {
    listen 80;
    server_name pfcentral.yourdomain.com;
    root /var/www/pfcentral/public;
    index index.php index.html;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\. {
        deny all;
    }
}
```

Enable the site and reload Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/pfcentral /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

---

## Method 3: Windows (Laragon / XAMPP)

1. Clone or copy `pfCentral` into `d:\laragon\www\pfCentral` (or `C:\xampp\htdocs\pfcentral`).
2. Copy `.env.example` to `.env` and configure database connection.
3. Open Terminal in the project root:
   ```powershell
   php spark pfcentral:install
   ```
4. Access `http://localhost/pfCentral/public/` in your browser.
