# 🎓 StudentHub - Digital Student Portal

StudentHub is a responsive web-based student portal designed to
provide students with a simple and centralized platform for accessing
academic information, campus events, student services, profiles,
notices, FAQs and other important resources.

The project is developed progressively as a semester-long web
development practical, with each practical adding new functionality
to the same StudentHub portal.

---

## 📌 Project Overview

StudentHub provides a centralized digital platform where students can:

- View important campus information
- Explore upcoming events
- Search and filter events
- Register for events
- Manage their student profile
- Access the student dashboard
- Submit feedback
- Find answers through FAQs
- Register for a StudentHub account
- Use the portal on desktop, tablet and mobile devices

The project focuses on:

- Clean UI design
- Responsive layouts
- Accessibility
- JavaScript interactivity
- Form validation
- Dynamic data loading
- Search and filtering
- Sorting and pagination

---

# 🎯 Objectives

- Design and develop a student-centric web portal
- Understand website planning and information architecture
- Create a sitemap and navigation structure
- Develop semantic HTML5 pages
- Design responsive layouts using CSS Grid and Flexbox
- Implement interactive UI components using JavaScript
- Implement client-side form validation using Regular Expressions
- Load external JSON data using Fetch API
- Implement dynamic rendering, search, filtering, sorting and pagination
- Maintain the project using Git and GitHub

---

# 🛠️ Technologies Used

## Frontend

- HTML5
- CSS3
- JavaScript
- JSON

## CSS Concepts

- CSS Grid
- Flexbox
- Media Queries
- Responsive Design
- Mobile-First Design
- CSS Transitions

## JavaScript Concepts

- DOM Manipulation
- Event Listeners
- Regular Expressions
- Form Validation
- Fetch API
- Async/Await
- Dynamic Rendering
- Search
- Filtering
- Sorting
- Pagination
- Modal Popup
- FAQ Accordion
- Content Slider
- Hamburger Menu
- Light/Dark Theme
- Local Storage

## Tools

- Visual Studio Code
- Git
- GitHub
- Live Server
- Browser Developer Tools

---

# 📚 Practical Progress

# Practical 1 - Project Initiation and Planning

## Objective

To initiate the semester-long StudentHub portal by identifying
the problem scope, user roles, key modules, navigation flow and
minimum required pages.

## Work Completed

- Identified the problem scope
- Defined StudentHub user requirements
- Identified user roles
- Defined major portal modules
- Designed website navigation flow
- Created sitemap
- Created low-fidelity wireframe
- Created project folder structure
- Created README documentation
- Created GitHub repository

---

# Practical 2 - Static HTML5 Pages

## Objective

To develop static HTML5 skeletons for at least 10 StudentHub pages
using semantic and accessibility-friendly HTML structure.

## Pages Developed

1. Home
2. About
3. Register
4. Login
5. Dashboard
6. Events
7. Profile
8. Contact
9. Admin
10. FAQ
11. Feedback

## HTML5 Concepts Used

- `header`
- `nav`
- `main`
- `section`
- `article`
- `footer`
- Forms
- Labels
- Buttons
- Semantic structure
- Accessibility attributes

---

# Practical 3 - Responsive Web Design

## Objective

To design responsive layouts for the major StudentHub pages using
CSS Grid and Flexbox.

## Pages Designed

- Home
- About
- Registration
- Dashboard
- Events

## Features Implemented

- CSS Grid layouts
- Flexbox layouts
- Responsive navigation
- Responsive cards
- Mobile-first design
- Media queries
- Responsive forms
- Desktop layouts
- Tablet layouts
- Mobile layouts

The StudentHub interface adapts to different screen sizes including:

- Desktop
- Laptop
- Tablet
- Mobile

---

# Practical 4 - JavaScript Dynamic UI Components

## Objective

To add dynamic and interactive UI components using JavaScript.

## Components Implemented

### 1. 🔔 Notification Banner

A notification banner is displayed on the portal with a
close button.

### 2. 🖼️ Content Slider

A JavaScript-powered content slider was implemented.

Features:

- Multiple slides
- Previous button
- Next button
- Automatic slide change

### 3. ❓ Collapsible FAQ

The FAQ page contains expandable and collapsible questions.

When a question is clicked, the corresponding answer is displayed.

### 4. 🪟 Modal Popup

Event registration uses a modal popup where users can
confirm or cancel an action.

### 5. ☰ Hamburger Menu

A responsive hamburger navigation menu was implemented
for smaller screen sizes.

### 6. 🌙 Light/Dark Theme Switcher

Users can switch between light and dark themes.

The selected theme can be preserved using browser local storage.

---

# Practical 5 - Student Registration Form

## Objective

To create a student registration form using HTML5 input types
and JavaScript validation with Regular Expressions.

## Registration Fields

The form contains:

- Full Name
- Email Address
- Mobile Number
- Password
- Confirm Password
- Course
- Academic Year
- Gender
- Terms and Conditions

## HTML5 Input Types Used

- Text
- Email
- Telephone
- Password
- Radio
- Checkbox
- Select

## JavaScript Validation

The form validates:

- Name
- Email
- Mobile Number
- Password
- Confirm Password
- Course
- Academic Year
- Gender
- Terms acceptance

## Regular Expressions

### Name

```text
/^[A-Za-z]+(?:\s[A-Za-z]+)+$/
```

### Email

```text
/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/
```

### Mobile

```text
/^[6-9]\d{9}$/
```

### Password

```text
/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/
```

---

# Practical 6 - Asynchronous Data Loading using Fetch API

## Objective

To fetch event data asynchronously from an external JSON file using the JavaScript Fetch API, dynamically render event cards, and implement real-time search, category filtering, multi-criteria sorting, and pagination.

## Implemented Features

- **JSON Data Source (`data/events.json`)**: Campus events dataset with id, title, category, description, date, time, location, organizer, and seats.
- **Fetch API & Async/Await**: Asynchronously loads and parses `data/events.json` with loading status and error handling.
- **Dynamic DOM Rendering**: Renders event cards dynamically with formatted dates and category badges.
- **Live Search**: Filters events in real time by title, category, or location.
- **Category Filter**: Dropdown filter for Academic, Cultural, Sports, and Workshop events.
- **Multi-Criteria Sorting**: Sort by date (ascending/descending) or title (alphabetical).
- **Client-Side Pagination**: Clean pagination controls with previous/next buttons and active page indicator.

---

# Practical 7 - Server-Side Form Processing with PHP

## Objective

To process submitted registration and contact form data using PHP, sanitize and validate all inputs on the server side, store records in CSV and JSON file formats, and display clear, responsive success and error messages.

## Key Capabilities Implemented

### 1. Server-Side Input Sanitization
- `strip_tags()` and `trim()` to remove extraneous whitespace and HTML tags.
- `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` for robust Cross-Site Scripting (XSS) defense.
- `filter_var(..., FILTER_SANITIZE_EMAIL)` to sanitize email strings.
- Regex numeric sanitization to isolate mobile numbers.

### 2. Comprehensive Server-Side Validation
- **Full Name**: Verified presence, length (3–60 characters), and regex validation (`/^[A-Za-z]+(?:\s[A-Za-z]+)+$/`) requiring letters and spaces only.
- **Email Address**: Server-side format validation using PHP's `filter_var($email, FILTER_VALIDATE_EMAIL)`.
- **Mobile Number**: Verified 10-digit Indian mobile number starting with 6–9 using `/^[6-9]\d{9}$/`.
- **Password Complexity**: Enforced minimum 8 characters with at least one uppercase letter, one lowercase letter, and one number (`/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/`).
- **Confirm Password**: Server-side equality check (`$password === $confirmPassword`).
- **Course & Academic Year**: Strict whitelist verification against allowed campus courses and years (1–4).
- **Gender**: Whitelist verification for Male, Female, and Other.
- **Terms & Conditions**: Explicit verification that terms agreement is checked.
- **Duplicate Prevention**: Scans existing records to reject duplicate registrations with the same email or mobile.

### 3. Dual Persistent File Storage (CSV & JSON)
- **CSV Storage (`data/registrations.csv` & `data/contacts.csv`)**:
  - Automatically writes CSV header if file does not exist.
  - Safe concurrent writing using exclusive file locks (`flock($handle, LOCK_EX)`).
  - RFC 4180 compliant CSV formatting via `fputcsv()`.
- **JSON Storage (`data/registrations.json` & `data/contacts.json`)**:
  - Reads, decodes, appends new record, and writes back cleanly formatted JSON with `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES`.
- **Security & Integrity**:
  - Passwords are never stored in plain text; hashed using bcrypt via `password_hash($password, PASSWORD_DEFAULT)`.
  - Auto-generated sequential Registration IDs (e.g., `STU20261013004`) and Contact IDs (e.g., `MSG20261013001`).
  - Standardized timestamps (`YYYY-MM-DD HH:MM:SS`) in IST timezone.

### 4. Dynamic Feedback & Result Presentation
- **Success Response**: Displays registration ID badge, storage confirmation tags (CSV & JSON), and a detailed summary grid of sanitized submitted details.
- **Error Response**: Clear alert boxes with actionable error messages for each invalid field, plus a button to return to the form with preserved values.
- **Records Viewer (`php/view_registrations.php`)**: An interactive dashboard showing all stored student registrations with search count, live table rendering, CSV download, and raw JSON view.
- **Dual Mode Support**: Supports traditional form submissions (renders complete StudentHub HTML pages) as well as modern asynchronous AJAX/Fetch requests (returns structured JSON).

## Practical 7 File Structure

```text
Student Hub/
├── css/
│   └── style.css               # Added Practical 7 alert boxes, data tables, summary cards, dark mode
├── data/
│   ├── registrations.csv       # Persistent CSV student database
│   ├── registrations.json      # Structured JSON student database
│   ├── contacts.csv            # Persistent CSV contact messages
│   └── contacts.json           # Structured JSON contact messages
├── js/
│   └── script.js               # Client validation integrated with PHP backend submission
├── pages/
│   ├── register.html           # Registration form wired to php/register_process.php
│   └── contact.html            # Contact form wired to php/contact_process.php
└── php/
    ├── register_process.php    # Server-side PHP processor for registration
    ├── contact_process.php     # Server-side PHP processor for contact form
    └── view_registrations.php  # Live record viewer and CSV/JSON exporter
```

---

# Practical 8 - MySQL Schema Design, ER Model, PDO Connectivity, and Prepared Statements

## Objective

To design a normalized relational database schema for StudentHub (students, events, and event registrations), document the Entity-Relationship (ER) model, establish a secure PHP database connection using PDO (PHP Data Objects), and execute secure parameterized database operations using Prepared Statements.

## Key Capabilities Implemented

### 1. Relational Database Schema (`database/schema.sql`)
- **`students`**: Stores registered student profiles with attributes (`student_id`, `registration_no`, `full_name`, `email`, `mobile`, `password_hash`, `course`, `academic_year`, `gender`, `terms_accepted`, timestamps). Enforces uniqueness on registration number, email, and mobile.
- **`events`**: Stores campus event records matching Practical 6 (`event_id`, `title`, `category`, `event_date`, `event_time`, `location`, `description`, `organizer`, `total_seats`, `available_seats`, `status`).
- **`event_registrations`**: Associative junction table resolving the **Many-to-Many (M:N)** relationship between Students and Events. Enforces referential integrity via Foreign Keys (`ON DELETE CASCADE`, `ON UPDATE CASCADE`) and prevents duplicate registrations with `UNIQUE KEY unique_student_event_registration (student_id, event_id)`.
- **`contact_inquiries`**: Stores campus inquiries and helpdesk tickets (`inquiry_id`, `reference_no`, `full_name`, `email`, `mobile`, `subject`, `message`, `status`, `submitted_at`).
- **Initial Seed Data**: Pre-loaded with realistic records matching Practical 5, 6, and 7 for instant testing.

### 2. Entity-Relationship (ER) Model (`docs/ER_MODEL.md`)
- Detailed ER Model documentation covering entity types, attributes, constraints, cardinality (`1:N`, `M:N`), and Third Normal Form (`3NF`) normalization analysis.
- Includes embedded Mermaid ER Diagram (`erDiagram`) for immediate visual rendering.

### 3. PDO Database Connection Module (`php/db_connect.php`)
- **PHP Data Objects (PDO)** configuration connecting to MySQL using UTF-8 multi-byte encoding (`utf8mb4`).
- **Strict Error Handling**: Enables `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`.
- **Native Prepared Statements**: Explicitly sets `PDO::ATTR_EMULATE_PREPARES => false` to force MySQL server-side statement compilation, providing absolute defense against SQL Injection attacks.
- **Diagnostics**: Built-in `testDBConnection()` helper returning live latency, MySQL version, driver info, and table row counts.

### 4. Prepared Statements Demos & Live Operations (`php/db_dashboard.php`)
- **Live Connection Diagnostics**: Shows real-time connection status, database latency, and active table metrics.
- **Demo 1 (Parameterized SELECT with Named Placeholders)**:
  ```php
  $stmt = $pdo->prepare("SELECT student_id, registration_no, full_name, email, course FROM students WHERE course = :course");
  $stmt->execute([':course' => $selectedCourse]);
  $students = $stmt->fetchAll();
  ```
- **Demo 2 (Relational INNER JOIN)**: Multi-table join displaying registered students and their corresponding event titles.
- **Demo 3 (Atomic Transaction & Prepared INSERT)**:
  - Uses `PDO::beginTransaction()`, prepared duplicate checks, prepared seat availability checks with row-level locking (`FOR UPDATE`), prepared INSERT into `event_registrations`, prepared UPDATE to decrement `available_seats`, and `PDO::commit()`.
  - Automatically rolls back on any error (`PDO::rollBack()`).
- **Dual-Storage Integration & MySQLi Registration**: `php/register_process.php` securely performs MySQL insertion using **MySQLi Prepared Statements** (`$stmt = $mysqli->prepare(...)`, `$stmt->bind_param(...)`, `$stmt->execute()`), checks duplicate email and username, and hashes user passwords with `password_hash($pwd, PASSWORD_DEFAULT)`.

### 5. Secure User Registration System (`php/register_process.php`)
- **Frontend & Backend Validation**: Validates full name, username, email, 10-digit mobile, password complexity (uppercase, lowercase, number), password confirmation, course, year, and terms acceptance.
- **Duplicate Email & Username Checks**: Runs parameterized MySQLi `SELECT` queries before inserting to prevent duplicate accounts:
  ```php
  $stmt = $mysqli->prepare("SELECT student_id FROM students WHERE username = ? LIMIT 1");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  ```
- **Cryptographic Password Hashing**: Passwords hashed securely using native `password_hash($password, PASSWORD_DEFAULT)` (Bcrypt) before storage.
- **MySQLi Parameterized Insertion**:
  ```php
  $insertSql = "INSERT INTO students 
      (registration_no, username, full_name, email, mobile, password_hash, course, academic_year, gender, terms_accepted, created_at) 
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
  $stmt = $mysqli->prepare($insertSql);
  $stmt->bind_param("sssssssisss", $regId, $username, $name, $email, $mobile, $passwordHash, $course, $year, $gender, $terms, $date);
  $stmt->execute();
  ```
- **Dual Persistence**: Maintains real-time synchronization across MySQL (`students` table) and flat files (`registrations.csv`, `registrations.json`).

## Practical 8 File Structure

```text
Student Hub/
├── database/
│   └── schema.sql              # Complete MySQL DDL script with seed data (includes username column & unique indexes)
├── docs/
│   └── ER_MODEL.md             # Complete Entity-Relationship model spec & Mermaid diagram
└── php/
    ├── db_connect.php          # Secure connection module providing both PDO & MySQLi (getMySQLiConnection)
    ├── db_dashboard.php        # Interactive diagnostic dashboard & prepared statement demos (PDO + MySQLi)
    ├── register_process.php    # Secure user registration via MySQLi prepared statements & password_hash()
    └── view_registrations.php  # Integrated record viewer with live MySQL query & CSV fallback
```

---

# Practical 10 - Secure User Authentication, PHP Sessions & Access Control

## Objective

To develop secure login and logout functionality using PHP sessions. Verify passwords cryptographically using `password_verify()`, regenerate session IDs upon login to defend against session fixation attacks, restrict access to protected dashboards, enforce role-based redirection, implement an inactivity session timeout, and provide an optional secure remember-me token mechanism.

## Key Capabilities Implemented

### 1. Cryptographic Password Verification (`password_verify()`)
- Processes user identifier (username or email) and password through parameterized MySQLi prepared statements (`SELECT ... WHERE username = ? OR email = ? LIMIT 1`).
- Defends against timing attacks and rainbow tables by validating submitted passwords against stored Bcrypt hashes via PHP's native `password_verify($password, $user['password_hash'])`.

### 2. Session Fixation Defense & Hardened Session Cookies (`session_regenerate_id(true)`)
- Invokes `session_regenerate_id(true)` immediately upon successful credential verification to invalidate the pre-authentication session ID and assign a new cryptographically secure session ID.
- Configures hardened session cookie parameters via `session_set_cookie_params()` before `session_start()`:
  - `httponly => true` (prevents client-side JavaScript access / XSS token theft).
  - `samesite => 'Lax'` (mitigates Cross-Site Request Forgery / CSRF).
  - `secure => true` (automatically enabled when HTTPS is detected).
  - `ini_set('session.use_strict_mode', '1')` (rejects uninitialized attacker-supplied session IDs).

### 3. Protected Dashboards & Access Control Guard (`requireLogin()`)
- Protected pages (`pages/dashboard.php` and `pages/admin.php`) invoke `requireLogin($role)` at the very top.
- Unauthenticated visitors attempting direct URL access are immediately intercepted and redirected to `pages/login.php?error=auth_required&redirect=...`.
- Session state retains user identity, course, academic year, full name, and role for authorized rendering.

### 4. Role-Based Access Control (RBAC) & Dynamic Redirection
- Supports dual system roles: **Student** (`student`) and **Administrator** (`admin`).
- Post-login dispatch directs users dynamically based on their role:
  - Administrators -> `pages/admin.php`
  - Students -> `pages/dashboard.php`
- Enforces cross-role boundary security:
  - Students attempting to access `pages/admin.php` are intercepted and redirected to `pages/dashboard.php?error=admin_only`.
  - Admins can manage users, inspect system statistics, and simulate student views.

### 5. Inactivity Session Timeout (15-Minute Guard)
- Tracks user interaction via `$_SESSION['last_activity'] = time()`.
- Automatically terminates the session if inactivity exceeds 900 seconds (15 minutes).
- Destroys server-side session, deletes session cookie, and redirects user to `pages/login.php?error=session_timeout`.
- Interactive countdown timer rendered on the dashboard with a "Reset Timer" heartbeat action.

### 6. Secure Remember-Me Token Architecture
- Optional "Remember Me" checkbox on the login form.
- Generates a high-entropy 64-character token via `bin2hex(random_bytes(32))`.
- Stores the **SHA-256 hash** of the token in the MySQL `students` table (`remember_token`) with a 30-day expiration date (`token_expiry`).
- Issues an `httponly`, `samesite=Lax` cookie storing `base64_encode(userId:rawToken)`.
- Validates the token against the database hash using `hash_equals()` for timing-safe comparison on subsequent visits.

### 7. Clean Session Termination & Logout (`php/logout.php`)
- Invalidates and removes the database remember-me token.
- Clears the remember-me cookie from the client's browser.
- Clears all session data via `$_SESSION = []`.
- Deletes the session cookie with an expired timestamp.
- Calls `session_destroy()` to wipe server session storage.
- Redirects user to `pages/login.php?message=logged_out`.

## Demo & Testing Accounts

| Role | Username / Email | Password | Landing Page |
| :--- | :--- | :--- | :--- |
| **Student** | `rahul_patel` or `rahul.patel@email.com` | `password` | [`pages/dashboard.php`](file:///c:/Users/avipa/OneDrive/Desktop/Student%20Hub/pages/dashboard.php) |
| **Administrator** | `admin` or `admin@studenthub.com` | `Admin@123` | [`pages/admin.php`](file:///c:/Users/avipa/OneDrive/Desktop/Student%20Hub/pages/admin.php) |

## Practical 10 File Structure

```text
Student Hub/
├── css/
│   └── style.css               # Added login card, password eye toggle, timer badges, RBAC alerts
├── database/
│   └── schema.sql              # Added role, remember_token, token_expiry columns, admin & student seeds
├── docs/
│   └── ER_MODEL.md             # Updated ER specification with role and remember-me attributes
├── pages/
│   ├── login.php               # Protected login page with error alerts, remember-me & auto-fill test buttons
│   ├── login.html              # HTML fallback auto-redirecting to login.php
│   ├── dashboard.php           # Protected student dashboard requiring student role & showing session timer
│   ├── dashboard.html          # HTML fallback auto-redirecting to dashboard.php
│   ├── admin.php               # Protected administrator console requiring admin role & user table
│   └── admin.html              # HTML fallback auto-redirecting to admin.php
└── php/
    ├── auth_session.php        # Core session security, timeout check, requireLogin(), remember-me & logout
    ├── login_process.php       # Authenticates via password_verify(), session_regenerate_id(true), RBAC redirect
    ├── logout.php              # Comprehensive session and token destruction
    ├── register_process.php    # Integrated role='student' assignment and auth_session helper
    └── db_connect.php          # Database connectivity for session verification
```


