# TOSHA EXPRESS - Office Booking & Parcel Management System

A comprehensive bus booking and parcel management system for TOSHA EXPRESS, built with PHP, MySQL, HTML, CSS, and JavaScript.

## 🚌 System Overview

**TOSHA EXPRESS** - "SAFEST MEAN OF TRANSPORT AT AFFORDABLE FARES"

This system provides a complete solution for managing bus bookings, parcel deliveries, and administrative tasks for a transportation company.

## 🎨 Theme Colors

- **Primary Yellow**: Background and main theme
- **Red**: Headers and accents
- **Blue**: Buttons and borders
- **Bus Capacity**: 60 seats per bus

## 🏗️ System Modules

### 1. Admin Login
- Secure login system
- Role-based access (Admin/Clerk)
- Default credentials: admin / admin123

### 2. Dashboard
- Real-time statistics
- Quick action buttons
- Recent bookings and parcels overview
- Visual charts and summaries

### 3. Ticket Booking
- Interactive seat map (60 seats)
- Real-time seat availability
- Multiple passenger booking
- Automatic fare calculation
- Print ticket receipts

### 4. Parcel Management
- Complete parcel tracking
- Sender and receiver details
- Weight and cost calculation
- Print parcel receipts

### 5. Bus Management
- Add/Edit/Delete buses
- Driver assignment
- Status management
- Automatic seat generation

### 6. Route Management
- Predefined routes with fares
- Distance tracking
- Route optimization

### 7. User Management (Admin Only)
- Create/Edit/Delete users
- Role assignment
- Account status management

### 8. Reports
- Daily, Weekly, Monthly reports
- Revenue tracking
- Export to CSV
- Print functionality

## 🗄️ Database Structure

The system uses MySQL with the following main tables:
- `admins` - User authentication
- `buses` - Bus information
- `routes` - Route details and fares
- `tickets` - Booking records
- `seats` - Seat availability
- `parcels` - Parcel tracking
- `users` - Additional user management

## 🚀 Installation & Setup

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- Web browser

### Installation Steps

1. **Extract Files**
   - Place all files in your XAMPP htdocs directory
   - Path: `/Applications/XAMPP/xamppfiles/htdocs/tosha_system/`

2. **Start XAMPP Services**
   - Start Apache and MySQL from XAMPP Control Panel

3. **Create Database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the SQL file: `sql/schema.sql`
   - This will create the database and sample data

4. **Configure Database**
   - Edit `config/database.php` if needed
   - Default settings work with XAMPP

5. **Access System**
   - Open browser and go to: `http://localhost/tosha_system`
   - Login with: admin / admin123

## 🔧 Configuration

### Database Settings
Edit `config/database.php`:
```php
private $host = "localhost";
private $db_name = "tosha_express";
private $username = "root";
private $password = "";
```

### Default Routes
The system comes with predefined routes:
- Nairobi → Nakuru (KSh 1,500)
- Nakuru → Eldoret (KSh 1,200)
- Eldoret → Kitale (KSh 800)
- Kitale → Lodwar (KSh 2,000)
- Lodwar → Kakuma (KSh 500)

## 📱 Features

### Interactive Seat Map
- Visual representation of 60 seats
- Real-time availability updates
- Click to select/deselect seats
- Color-coded status (Available/Booked/Selected)

### Receipt Generation
- Professional ticket receipts
- Parcel tracking receipts
- Print-friendly format
- Barcode generation

### Reporting System
- Multiple report types
- Export functionality
- Print capabilities
- Revenue tracking

### Responsive Design
- Mobile-friendly interface
- Works on all devices
- Modern UI/UX

## 🔐 Security Features

- Password hashing
- Session management
- SQL injection prevention
- Input validation
- Role-based access control

## 📊 Sample Data

The system includes sample data:
- 3 sample buses
- 5 predefined routes
- Default admin account
- 60 seats per bus (automatically generated)

## 🎯 Usage

1. **Login** with admin credentials
2. **Add Buses** in Bus Management
3. **Create Routes** with appropriate fares
4. **Book Tickets** using the seat map
5. **Record Parcels** for delivery
6. **Generate Reports** for analysis
7. **Manage Users** (Admin only)

## 🛠️ Customization

### Adding New Routes
1. Go to Route Management
2. Add new route with fare
3. Routes will appear in booking forms

### Modifying Bus Capacity
1. Edit bus capacity in database
2. Update seat generation logic
3. Modify seat map display

### Theme Customization
Edit `assets/css/style.css` to change colors and styling.

## 📞 Support

For technical support or customization requests, contact the development team.

## 📄 License

This system is developed for TOSHA EXPRESS internal use.

---

**TOSHA EXPRESS** - "SAFEST MEAN OF TRANSPORT AT AFFORDABLE FARES"
