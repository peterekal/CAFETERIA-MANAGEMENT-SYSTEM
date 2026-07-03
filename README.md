# 🍴 Cafeteria Admin Login System

## 📖 Overview
This project is a **secure admin login module** for a cafeteria management system, built with **PHP** and **MySQL**. It provides administrators with a protected gateway to access the backend dashboard where they can manage cafeteria operations such as menus, orders, and staff records.

## ✨ Features
- **[Secure authentication](ca://s?q=Secure_authentication_in_PHP)** using prepared statements to prevent SQL injection  
- **[Password hashing](ca://s?q=Password_hashing_in_PHP)** with `password_hash()` and `password_verify()` for strong security  
- **[Session management](ca://s?q=PHP_session_management)** to maintain admin login state safely  
- **[Error handling](ca://s?q=Error_handling_in_PHP)** with user-friendly messages for invalid credentials  
- **[Redirection](ca://s?q=PHP_header_redirection)** to the admin dashboard (`admin_index.php`) upon successful login  
- Clean and responsive **HTML/CSS interface**  

## 🛠 Technologies Used
- **PHP** (server-side scripting)  
- **MySQL** (database for storing admin credentials)  
- **HTML & CSS** (frontend interface)  

## 📊 Workflow
1. Admin enters **username** and **password**  
2. System checks credentials against the `Admins` table in the database  
3. If valid, a secure session is created and admin is redirected to the dashboard  
4. If invalid, an error message is displayed  

## 🔒 Security Highlights
- Uses **prepared statements** to block SQL injection  
- Passwords stored as **secure hashes** instead of plain text  
- **Session regeneration** prevents fixation attacks  
- Generic error messages avoid exposing sensitive details  

## ⚙️ Installation
1. Clone this repository:
   ```bash
   git clone https://github.com/your-username/cafeteria-admin-login.git
