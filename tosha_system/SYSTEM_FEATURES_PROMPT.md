# TOSHA EXPRESS - Complete System Features Documentation

## System Overview
**TOSHA EXPRESS** - "SAFEST MEAN OF TRANSPORT AT AFFORDABLE FARES"

A comprehensive bus booking and parcel management system built with PHP, MySQL, HTML, CSS, and JavaScript. The system provides complete solutions for managing bus operations, ticket bookings, parcel deliveries, payments, and administrative tasks.

---

## 🔐 1. AUTHENTICATION & USER MANAGEMENT

### Login System
- Secure login with username and password
- Session-based authentication
- Password hashing (MD5)
- Default credentials: admin / admin123
- Automatic session timeout
- Logout functionality

### User Management (Admin Only)
- Create new users (Admin/Clerk roles)
- Edit user details (name, username, password, role, status)
- Delete users (cannot delete own account)
- User status management (Active/Inactive)
- Role-based access control (Admin/Clerk)
- View all users with creation dates

---

## 📊 2. DASHBOARD

### Real-Time Statistics
- Total tickets sold (confirmed status)
- Total parcels sent
- Daily income calculation (tickets + parcels)
- Active buses count
- Visual dashboard cards with color-coded metrics

### Seat Availability Widget
- Real-time seat availability overview for all active buses
- Visual representation of:
  - Available seats count
  - Booked seats count
  - Maintenance seats count
- Progress bars showing seat distribution
- Quick actions: Manage Seats, Book Ticket
- Bus information display (name, plate number)

### Quick Actions
- Book New Ticket
- Record New Parcel
- Manage Bookings
- Bulk Print Tickets
- QR Scanner
- View Reports
- Manage Buses

---

## 🎫 3. TICKET BOOKING SYSTEM

### Booking Features
- Interactive seat map (60 seats per bus)
- Real-time seat availability display
- Multiple seat selection for group bookings
- Automatic fare calculation based on route
- Multiple passenger booking support
- Travel date and time selection
- Payment method selection (Cash/M-Pesa)

### Seat Map Features
- Visual grid representation of bus seats
- Color-coded seat status:
  - Green: Available
  - Red: Booked
  - Blue: Selected
- Click-to-select/deselect seats
- Real-time seat status updates
- Seat availability validation before booking

### Route & Fare Management
- Dynamic route selection (From/To)
- Automatic fare lookup from routes table
- Fare calculation per seat
- Total fare calculation for multiple seats
- Route information display

### Payment Integration
- Cash payment option
- M-Pesa STK Push payment integration
- Phone number validation (Kenyan format: 07XXXXXXXX, 01XXXXXXXX)
- Payment status tracking
- Payment method stored with ticket

### Ticket Generation
- Unique ticket number generation (TK + date + random)
- Automatic booking date/time recording
- Ticket confirmation status
- Seat assignment and locking
- Ticket receipt printing

---

## 💰 4. M-PESA PAYMENT INTEGRATION

### STK Push Payment
- M-Pesa STK Push initiation
- Phone number format validation
- Amount validation
- Payment callback handling
- Payment status tracking
- Sandbox and Production environment support

### Configuration
- Consumer Key/Secret management
- Shortcode configuration
- Passkey management
- Callback URL configuration
- Account reference setup
- Transaction description customization

### Payment Flow
1. Customer selects M-Pesa payment method
2. Enters phone number
3. System initiates STK Push
4. Customer receives prompt on phone
5. Customer enters PIN
6. Payment callback received
7. Ticket automatically confirmed

### Error Handling
- Invalid phone number validation
- Callback URL validation (HTTPS required)
- Payment failure handling
- Network error handling
- User-friendly error messages

---

## 📦 5. PARCEL MANAGEMENT

### Parcel Recording
- Sender information (name, phone)
- Receiver information (name, phone)
- Route selection (From/To)
- Parcel description
- Weight input (kg)
- Cost calculation
- Unique parcel number generation (PK + date + random)

### Parcel Status Management
- Status workflow: Pending → Approved → In Transit → Delivered
- Status update buttons with visual indicators
- Status change confirmation
- Status history tracking

### Parcel Operations
- View all parcels
- Search and filter parcels
- Edit parcel details
- Delete individual parcels
- Bulk delete all parcels
- Export to Excel
- Print parcel receipts

### Parcel Tracking
- Parcel number tracking
- Route tracking
- Status tracking
- Date/time tracking
- Cost tracking

---

## 🚌 6. BUS MANAGEMENT

### Bus Operations
- Add new buses
- Edit bus details
- Delete buses (with safety checks)
- Bus status management (Active/Inactive)
- Bus information:
  - Bus name
  - Plate number (unique)
  - Color
  - Driver name
  - Capacity (default: 60 seats)

### Seat Generation
- Automatic seat generation (60 seats per bus)
- Manual seat generation for specific buses
- Bulk seat generation for all buses
- Seat numbering (S01, S02, ..., S60)
- Seat status management

### Bus Validation
- Prevents deletion of buses with active bookings
- Checks for future travel dates
- Seat count display
- Bus availability status

---

## 🛣️ 7. ROUTE MANAGEMENT

### Route Operations
- Add new routes
- Edit route details
- Delete routes (with safety checks)
- Route information:
  - Start point
  - Destination
  - Fare (KSh)
  - Distance (km)

### Route Validation
- Prevents duplicate routes
- Prevents deletion of routes with active bookings
- Route availability in booking forms

### Default Routes
- Nairobi → Nakuru (KSh 1,500)
- Nakuru → Eldoret (KSh 1,200)
- Eldoret → Kitale (KSh 800)
- Kitale → Lodwar (KSh 2,000)
- Lodwar → Kakuma (KSh 500)

---

## 🪑 8. SEAT MANAGEMENT

### Seat Operations
- View seat map for selected bus
- Real-time seat status display
- Change seat status (Available/Booked/Maintenance)
- Edit seat number
- Add new seats
- Delete seats (with validation)
- Seat statistics display

### Seat Status Management
- Available: Seat is free for booking
- Booked: Seat is occupied
- Maintenance: Seat is under maintenance

### Seat Map Visualization
- Grid layout (4 columns)
- Color-coded seats
- Click to select seat
- Seat information display
- Quick actions for booked seats

### Seat Statistics
- Total seats count
- Available seats count
- Booked seats count
- Maintenance seats count
- Visual progress indicators

### Booked Seat Actions
- Print ticket for booked seat
- View ticket details
- Change seat status
- Export seat data to Excel

---

## 📋 9. BOOKING MANAGEMENT

### Booking Operations
- View all bookings
- Search bookings (Ticket No, Passenger Name, Phone)
- Filter by status (Confirmed/Cancelled)
- Filter by date range
- Pagination (20 bookings per page)

### Booking Actions
- Print ticket
- Edit booking details
- Cancel booking (frees seat)
- Delete booking (permanent removal)
- Bulk delete filtered bookings
- Bulk delete all bookings

### Booking Information Display
- Ticket number
- Passenger name and phone
- Route (From → To)
- Bus information (name, plate, driver)
- Seat number
- Travel date and time
- Fare amount
- Booking status
- Payment method

### Booking Validation
- Prevents cancellation of past bookings
- Seat release on cancellation
- Confirmation dialogs for destructive actions

---

## 📄 10. PRINTING SYSTEM

### Ticket Printing
- Individual ticket printing
- Bulk ticket printing
- Print all tickets
- Print ticket receipt
- Print test ticket
- Professional ticket format
- QR code generation for tickets
- Barcode support

### Parcel Printing
- Individual parcel receipt printing
- Print all parcels
- Professional receipt format
- Parcel tracking information

### Seat Printing
- Print all seats for a bus
- Seat status display
- Excel export functionality

### Print Features
- Print-friendly layouts
- Company branding
- All relevant information included
- Date/time stamps
- Unique reference numbers

---

## 📊 11. REPORTING SYSTEM

### Report Types
- Daily reports
- Weekly reports
- Monthly reports
- Custom date range reports

### Report Metrics
- Tickets sold count
- Parcels sent count
- Ticket revenue (KSh)
- Parcel revenue (KSh)
- Total revenue (KSh)

### Report Details
- Detailed ticket listing
- Detailed parcel listing
- Date filtering
- Status filtering
- Export to CSV
- Print reports

### Report Features
- Summary cards with key metrics
- Detailed transaction tables
- Revenue breakdown
- Date range selection
- Export functionality
- Print functionality

---

## 🔍 12. QR CODE SYSTEM

### QR Code Generation
- Automatic QR code generation for tickets
- QR code contains ticket information
- Printable QR codes on tickets

### QR Code Scanner
- Camera-based QR code scanning
- Real-time ticket verification
- Ticket information display
- Scan result display
- Test QR code functionality

### QR Code Features
- Start/Stop scanner
- Camera access
- Ticket validation
- Ticket details display
- Print ticket from scan result

---

## 🗄️ 13. DATABASE STRUCTURE

### Core Tables
- **admins**: User authentication
- **buses**: Bus information
- **routes**: Route details and fares
- **tickets**: Booking records
- **seats**: Seat availability
- **parcels**: Parcel tracking
- **users**: Additional user management

### Database Features
- Foreign key relationships
- Unique constraints
- Automatic timestamps
- Status enums
- Cascade deletions

---

## 🎨 14. USER INTERFACE FEATURES

### Design Elements
- Modern, responsive design
- Color-coded status indicators
- Interactive buttons and forms
- Modal dialogs for confirmations
- Toast notifications for success/error
- Loading indicators
- Progress bars

### Navigation
- Top navigation bar
- Quick access menu
- Breadcrumb navigation
- Contextual actions
- Logout functionality

### Responsive Design
- Mobile-friendly layouts
- Tablet optimization
- Desktop full-featured view
- Adaptive grid layouts
- Touch-friendly buttons

---

## 🔒 15. SECURITY FEATURES

### Authentication Security
- Password hashing
- Session management
- Role-based access control
- Login requirement for all pages
- Session timeout

### Data Security
- SQL injection prevention (PDO prepared statements)
- Input validation
- XSS protection (htmlspecialchars)
- CSRF protection considerations
- Input sanitization

### Access Control
- Admin-only pages (User Management)
- Clerk access restrictions
- Permission checks
- Action confirmations

---

## 📱 16. ADDITIONAL FEATURES

### Search & Filter
- Global search functionality
- Advanced filtering options
- Date range filtering
- Status filtering
- Real-time search results

### Export Functionality
- Export to Excel (CSV format)
- Export bookings
- Export parcels
- Export seats
- Export reports

### Bulk Operations
- Bulk ticket printing
- Bulk booking deletion
- Bulk parcel deletion
- Bulk seat generation

### Validation & Error Handling
- Form validation
- Real-time validation feedback
- Error messages
- Success notifications
- Confirmation dialogs

### Data Management
- Automatic data cleanup options
- Historical data preservation
- Soft delete considerations
- Data export for backup

---

## 🛠️ 17. SYSTEM CONFIGURATION

### Database Configuration
- Configurable database connection
- Default XAMPP settings
- Connection pooling
- Error handling

### M-Pesa Configuration
- Environment selection (Sandbox/Production)
- Credential management
- Callback URL configuration
- Testing tools

### System Settings
- Default bus capacity (60 seats)
- Default routes
- Default admin account
- Theme colors (Yellow, Red, Blue)

---

## 📈 18. ANALYTICS & STATISTICS

### Dashboard Analytics
- Real-time statistics
- Daily income tracking
- Ticket sales tracking
- Parcel volume tracking
- Bus utilization

### Reporting Analytics
- Revenue analysis
- Booking trends
- Parcel delivery tracking
- Performance metrics

---

## 🔧 19. MAINTENANCE FEATURES

### System Maintenance
- Database schema updates
- Column existence checks
- Automatic migrations
- Error logging
- Debug tools

### Data Maintenance
- Clear all bookings option
- Clear all parcels option
- Seat regeneration
- Bus cleanup
- Route cleanup

---

## 📞 20. SUPPORT & DOCUMENTATION

### Documentation Files
- README.md - System overview
- MPESA_SETUP.md - M-Pesa integration guide
- MPESA_PAYMENT_FLOW.md - Payment flow documentation
- NGROK_SETUP.md - Local development setup
- WHERE_MONEY_GOES.md - Payment tracking
- FIX_MERCHANT_ERROR.md - Troubleshooting guide
- FIND_PASSKEY.md - Passkey location guide

### Setup Guides
- Installation instructions
- Database setup
- Configuration guides
- Testing procedures
- Troubleshooting tips

---

## 🎯 SUMMARY OF ALL FEATURES

### Core Modules (10)
1. Authentication & User Management
2. Dashboard & Analytics
3. Ticket Booking System
4. M-Pesa Payment Integration
5. Parcel Management
6. Bus Management
7. Route Management
8. Seat Management
9. Booking Management
10. Reporting System

### Supporting Features (10)
11. Printing System
12. QR Code System
13. Database Management
14. User Interface
15. Security Features
16. Search & Filter
17. Export Functionality
18. Bulk Operations
19. System Configuration
20. Documentation & Support

### Total Features Count
- **20 Major Feature Categories**
- **100+ Individual Features**
- **Complete CRUD Operations** for all entities
- **Payment Integration** (M-Pesa)
- **Printing & Export** capabilities
- **Real-time Updates** and validation
- **Comprehensive Reporting** system

---

## 🚀 SYSTEM CAPABILITIES

This system provides a **complete end-to-end solution** for:
- ✅ Bus ticket booking and management
- ✅ Parcel delivery tracking
- ✅ Payment processing (Cash & M-Pesa)
- ✅ Real-time seat availability
- ✅ Route and fare management
- ✅ User and access control
- ✅ Comprehensive reporting
- ✅ Printing and export
- ✅ QR code verification
- ✅ Mobile-responsive interface

**TOSHA EXPRESS** - Complete Transportation Management System

