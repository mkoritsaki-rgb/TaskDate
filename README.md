
# TaskDate

**TaskDate** is a web-based task and appointment management application designed to help users organize their daily activities, schedule tasks, and manage appointments.

## Features

- User registration, login, and logout.
- Guest access without creating an account.
- Create, edit, delete, complete, and restore tasks.
- Schedule tasks with specific dates and times.
- Create and manage appointments.
- Appointment conflict detection.
- Weekly calendar view (Monday–Sunday).
- Previous, current, and next week navigation.
- Data isolation between user accounts.
- Multilingual interface: Greek, English, and Spanish.
- Responsive design for desktop and mobile devices.

## Technologies

- PHP
- MySQL
- PDO
- HTML5
- CSS3
- JavaScript

## Project Structure

The application is organized into separate folders for configuration, authentication, assets, tasks, appointments, and calendar functionality.

## Installation

1. Clone or download this repository.
2. Set up a local PHP and MySQL environment (for example, XAMPP).
3. Create a MySQL database.
4. Copy `config/database.local.example.php` to `config/database.local.php`.
5. Enter your local database credentials in `database.local.php`.
6. Configure your local web server to serve the project directory.

## Security

- Database credentials are kept outside the public repository.
- PDO is used for database connections and prepared statements.
- CSRF protection is implemented for sensitive actions.
- User data ownership is checked before accessing or modifying records.

## Important

Actual database credentials and database backup files are not included in this repository.

## Project Status

Developed as a personal web development portfolio project.
