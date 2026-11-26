# BePresent – Attendance Monitoring System

BePresent is a web-based attendance monitoring system developed using PHP and MySQL. It allows administrators to manage employee records and monitor attendance, while employees can record their daily Time-In and Time-Out.

This project was developed as a personal portfolio project to demonstrate practical skills in web development and database management.

## Features

### Admin Features
- Dashboard with attendance statistics
- Employee account management
- Add, edit, and deactivate employee accounts
- View and search attendance records
- Manually create attendance records
- Edit attendance records
- Attendance correction history
- Attendance reports

### Employee Features
- Secure login and logout
- Personal attendance dashboard
- Record daily Time-In and Time-Out
- View attendance history
- View attendance calendar

## Technologies Used

- PHP
- MySQL
- PDO
- HTML5
- CSS3
- JavaScript
- Bootstrap Icons
- XAMPP

## Installation and Setup

Follow these steps to run BePresent locally.

### 1. Install XAMPP

Download and install [XAMPP](https://www.apachefriends.org/).

Start Apache and MySQL from the XAMPP Control Panel.

### 2. Clone the Repository

Open your terminal and run:

```bash
git clone YOUR_GITHUB_REPOSITORY_URL
```

Alternatively, download the repository as a ZIP file and extract it.

### 3. Move the Project

Place the BePresent folder inside:

```text
C:\xampp\htdocs\
```

### 4. Create the Database

1. Open `http://localhost/phpmyadmin`.
2. Create a database named `BePresent`.
3. Select the newly created database.
4. Click the **Import** tab.
5. Choose the `bepresent.sql` file.
6. Click **Import**.

### 5. Configure the Database Connection

Open:

`connection/connect.php`

Use your local database credentials.

Default XAMPP configuration:

```php
$host = "localhost";
$dbname = "BePresent";
$username = "root";
$password = "";
```

### 6. Run the Application

Open your browser and visit:

```text
http://localhost/BePresent/
```

Log in using an administrator or employee account configured in the database.

## Project Structure

```text
BePresent/
│
├── admin/
│   ├── attendance/
│   ├── employees/
│   ├── reports/
│   └── dashboard.php
│
├── employee/
│   ├── dashboard.php
│   ├── history.php
│   └── calendar.php
│
├── connection/
│   └── connect.php
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── includes/
├── index.php
├── login.php
├── logout.php
├── bepresent.sql
└── README.md
```

## Database

BePresent uses MySQL to store:

- User accounts and roles
- Employee information
- Daily attendance records
- Attendance correction logs

## Project Purpose

This project was developed as a personal portfolio project to gain practical experience in building a database-driven web application using PHP and MySQL.

## Developer

Developed as a personal portfolio project.
