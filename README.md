# 🎓 StudentHub - Digital Student Portal

StudentHub is a responsive web-based student portal designed to
provide students with a simple and centralized platform for accessing
academic information, campus events, student services, profiles,
feedback and other important resources.

The project is developed progressively as part of a semester-long
web development practical.

---

## 📌 Project Overview

StudentHub provides a single digital platform where students can:

- View important campus information
- Explore upcoming events
- Register for events
- Manage their student profile
- Access the student dashboard
- Submit feedback
- Find answers through FAQs
- Register for a StudentHub account
- Use the portal on desktop, tablet and mobile devices

The project focuses on clean UI design, responsive layouts,
accessibility and JavaScript-based interactivity.

---

# 🎯 Objectives

- Design and develop a student-centric web portal
- Understand website planning and information architecture
- Create a proper sitemap and navigation structure
- Develop semantic HTML5 pages
- Design responsive layouts using CSS Grid and Flexbox
- Implement interactive UI components using JavaScript
- Implement client-side form validation using Regular Expressions
- Maintain the project using Git and GitHub

---

# 🛠️ Technologies Used

### Frontend

- HTML5
- CSS3
- JavaScript

### CSS Concepts

- CSS Grid
- Flexbox
- Media Queries
- Responsive Design
- Mobile-First Design
- CSS Transitions

### JavaScript Concepts

- DOM Manipulation
- Event Listeners
- Regular Expressions
- Form Validation
- Modal Popup
- FAQ Accordion
- Content Slider
- Hamburger Menu
- Theme Switching
- Notification Banner
- Local Storage

### Tools

- Visual Studio Code
- Git
- GitHub
- Browser Developer Tools

---

# 📚 Practical Progress

## Practical 1 - Project Initiation and Planning

### Objective

To initiate the semester-long StudentHub portal by identifying
the problem scope, user roles, modules, navigation flow and
minimum required pages.

### Work Completed

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

### Objective

To develop static HTML5 skeletons for at least 10 StudentHub pages
using semantic and accessibility-friendly HTML structure.

### Pages Developed

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

### HTML5 Concepts Used

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

### Objective

To design responsive layouts for the major StudentHub pages using
CSS Grid and Flexbox.

### Pages Designed

- Home
- About
- Registration
- Dashboard
- Events

### Features Implemented

- CSS Grid layouts
- Flexbox layouts
- Responsive navigation
- Responsive cards
- Mobile-first design
- Media queries
- Responsive forms
- Desktop and mobile layouts

The StudentHub interface adapts to different screen sizes including:

- Desktop
- Laptop
- Tablet
- Mobile

---

# Practical 4 - JavaScript Dynamic UI Components

### Objective

To add dynamic and interactive UI components using JavaScript.

### Components Implemented

### 1. 🔔 Notification Banner

A notification banner is displayed on the Home page with a
close button.

### 2. 🖼️ Content Slider

A JavaScript-powered content slider was implemented on the
Home page.

Features:

- Automatic slide change
- Previous button
- Next button
- Multiple content slides

### 3. ❓ Collapsible FAQ

The FAQ page contains expandable and collapsible questions.

When a question is clicked, the corresponding answer is displayed.

### 4. 🪟 Modal Popup

The Events page contains event registration buttons.

Clicking the Register button opens a modal popup where the
student can confirm or cancel registration.

### 5. ☰ Hamburger Menu

A responsive hamburger navigation menu was implemented for
smaller screen sizes.

### 6. 🌙 Light/Dark Theme Switcher

A theme switcher allows users to change between light and
dark modes.

The selected theme can be preserved using browser local storage.

---

# Practical 5 - Student Registration Form

### Objective

To create a student registration form using HTML5 input types
and JavaScript validation with Regular Expressions.

### Registration Fields

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

### HTML5 Input Types Used

- Text
- Email
- Telephone
- Password
- Radio
- Checkbox
- Select

### JavaScript Validation

JavaScript validates the form before submission.

The following validations are implemented:

#### Name Validation

Checks that the name contains valid letters and spaces.

#### Email Validation

Checks whether the entered email follows a valid email format.

#### Mobile Validation

Checks for a valid 10-digit Indian mobile number.

#### Password Validation

The password must contain:

- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number

#### Confirm Password

Checks whether the confirm password matches the original password.

#### Course Validation

Checks whether a course has been selected.

#### Academic Year Validation

Checks whether an academic year has been selected.

#### Gender Validation

Checks whether the user has selected a gender.

#### Terms Validation

Checks whether the user has accepted the Terms and Conditions.

---

# 🔐 Regular Expressions Used

### Name

```text
/^[A-Za-z]+(?:\s[A-Za-z]+)+$/