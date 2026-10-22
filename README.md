# Karobar POS — Branch Sales and Inventory

A PHP and MySQL point-of-sale application with separate admin, owner, manager and salesperson areas.

## Features

- Branch and product management
- Customer records and khata
- Purchases, sales history and printable invoices
- Sales CSV export and notifications

## Technology

PHP, MySQL, HTML, CSS and JavaScript.

## Local setup

Clone the repository:

```bash
git clone https://github.com/nasirabbas827/pos-rehman.git
cd pos-rehman
```

1. Install a local PHP web server and MySQL, such as XAMPP.
2. Place the project in the server's document root, or configure a virtual host pointing to this folder.
3. Import `Database/pos-rehman_db.sql` using phpMyAdmin or your MySQL client.
4. Update the database connection in `config.php` and in role-specific copies of `config.php` to match your local database name and credentials.
5. Start Apache and MySQL, then open `http://localhost/pos-rehman/`.

Email verification and notification features require your own SMTP settings in the relevant mail handlers.

## Repository layout

| Path | Purpose |
|---|---|
| `Database/` | SQL database export |
| `PHPMailer/` | Bundled email library |
| `admin/` | Role-specific pages |
| `config.php` | Database connection configuration |
| `index.php` | Web entry page |
| `manager/` | Role-specific pages |
| `owner/` | Role-specific pages |
| `salesperson/` | Role-specific pages |

## Development notes

Setup steps describe local development. Configure your own database, mail and API credentials where applicable. Review debug settings and replace development secrets before deploying. The application was not executed as part of this documentation update.

Maintained by [Nasir Abbas](https://github.com/nasirabbas827).
