# PeacePlot - Cemetery Management System

A comprehensive web-based cemetery management system for tracking cemetery lots, burial records, reservations, and maintenance.

## Project Structure

```
peaceplot/
├── api/                    # API endpoints for CRUD operations
├── assets/                 # Static assets
│   ├── css/               # Stylesheets
│   └── js/                # JavaScript files
├── config/                # Configuration files
│   └── database.php       # Database connection class
├── database/              # Database files and scripts
│   ├── schema.sql         # Database schema
│   ├── seed.sql           # Sample data
│   ├── init.php           # Database initialization script
│   └── peaceplot.db       # SQLite database (generated)
└── public/                # Public HTML pages
    ├── index.html         # Cemetery lot management
    ├── dashboard.html     # Dashboard overview
    ├── lot-availability.html
    ├── cemetery-map.html
    ├── burial-records.html
    └── reports.html
```

## Features

- **Dashboard**: Overview of cemetery statistics and section summaries
- **Cemetery Lot Management**: CRUD operations for cemetery lots
- **Lot Availability**: Track available and occupied lots
- **Burial Records**: Maintain deceased person records
- **Cemetery Map**: Visual representation of cemetery layout
- **Reports**: Generate various reports and analytics
- **User Management**: Admin and staff access control
- **Activity Logs**: Track all system activities

## Database Schema

### Main Tables
- `cemetery_lots` - Cemetery lot information
- `deceased_records` - Burial records
- `reservations` - Lot reservations
- `payments` - Payment tracking
- `maintenance_records` - Maintenance history
- `users` - System users
- `activity_logs` - Activity tracking

## Setup Instructions

### Prerequisites
- WAMP/XAMPP server (Apache + PHP)
- PHP 7.4 or higher
- SQLite3 extension enabled in PHP

### Installation

1. **Clone or copy the project** to your WAMP www directory:
   ```
   c:\wamp64\www\peaceplot\
   ```

2. **Initialize the database**:
   - Open your browser and navigate to:
     ```
     http://localhost/peaceplot/database/init.php
     ```
   - Or run via command line:
     ```bash
     php database/init.php
     ```

3. **Access the application**:
   ```
   http://localhost/peaceplot/public/dashboard.html
   ```

### Default Login Credentials
- **Username**: admin
- **Password**: admin123
- ⚠️ **Important**: Change the default password after first login

## Technology Stack

- **Backend**: PHP 7.4+ (Core programming language)
- **Frontend**: HTML5, CSS3, Vanilla JavaScript (Client-side interactivity)
- **Database**: SQLite3 (Data persistence)
- **Server**: Apache (WAMP Environment)

## Programming Languages & Tools

- **PHP**: Handles all server-side logic, API endpoints, and database interactions.
- **JavaScript**: Manages client-side behavior and asynchronous API calls.
- **SQL**: Used for database schema definitions and data manipulation (SQLite).
- **CSS**: Defines the visual presentation and layout of the system.

## Development Status

Currently in development phase:
- ✅ Project structure organized
- ✅ Database schema designed
- ✅ Frontend UI completed
- 🔄 API endpoints (in progress)
- 🔄 Backend integration (pending)
- 🔄 Authentication system (pending)

## Next Steps

1. Create API endpoints in `/api` folder
2. Implement CRUD operations
3. Add authentication and authorization
4. Connect frontend to backend
5. Implement search and filtering
6. Add data validation
7. Create backup/restore functionality

## License

Proprietary - All rights reserved

## Support

For support, contact: admin@peaceplot.com
