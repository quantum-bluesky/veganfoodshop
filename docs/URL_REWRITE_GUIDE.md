# Hướng Dẫn Cấu Hình URL Rewrite (Bỏ Đuôi .php) - Bếp Chay An Lành

Tài liệu này hướng dẫn cách cấu hình Web Server để hỗ trợ truy cập các trang không cần gõ đuôi `.php` (ví dụ: gõ `/admin` thay vì `/admin.php`, `/process_order` thay vì `/process_order.php`).

---

## 1. Môi trường Máy chủ Linux Nginx (Production Debian / VPS)

Để Nginx tự động tìm file `.php` tương ứng khi người dùng gõ URL không có đuôi mở rộng, cập nhật file cấu hình Nginx (ví dụ: `/etc/nginx/sites-available/veganfoodshop`):

```nginx
server {
    listen 80;
    server_name yourdomain.com; # Thay bằng domain hoặc IP máy chủ
    root /var/www/veganfoodshop;

    index index.php index.html;

    # 1. Bảo vệ file database SQLite tuyệt đối không cho tải về
    location ~ \.(db|sqlite|sqlite3|sql|log|env)$ {
        deny all;
        return 404;
    }

    # 2. Hỗ trợ Clean URLs: Kiểm tra file tĩnh -> file .php -> index.php
    location / {
        try_files $uri $uri/ $uri.php?$query_string /index.php?$query_string;
    }

    # 3. Xử lý PHP thông qua PHP-FPM
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock; # Chỉnh đúng socket phiên bản PHP trên server
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

Kiểm tra và nạp lại cấu hình Nginx:
```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## 2. Môi trường Apache (XAMPP / Laragon / cPanel)

Hệ thống đã đính kèm sẵn file [`.htaccess`](../.htaccess) ở thư mục gốc của dự án. 
Đảm bảo module `mod_rewrite` đã được bật trong Apache:

```apache
# Bật module rewrite trong httpd.conf:
LoadModule rewrite_module modules/mod_rewrite.so

# Cho phép đọc .htaccess trong thư mục web:
<Directory "/path/to/veganfoodshop">
    AllowOverride All
    Require all granted
</Directory>
```

---

## 3. Môi trường Chạy Thử Local (PHP Built-in Server)

Khi phát triển hoặc test trên máy cá nhân không có Nginx/Apache, sử dụng file [router.php](../router.php) đã được cấu hình sẵn:

```powershell
# Chạy PHP Built-in Server kèm router:
php -S localhost:8008 router.php
```

Sau đó bạn có thể mở trình duyệt và truy cập:
- `http://localhost:8008/admin` -> Tự động chuyển tới `admin.php`
- `http://localhost:8008/` -> Tự động mở `index.php`
