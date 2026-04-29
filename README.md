# SOC Reporting System

A full-featured **Security Operations Center (SOC) incident reporting platform** built for managing security events across multiple operational environments. Designed for SOC teams that need structured incident tracking, threat intelligence management, and operational metrics — all in one place.

Built with PHP 8, MariaDB/MySQL, and vanilla JavaScript. No frameworks, no dependencies beyond a database.

---

## Features

### Incident Management
- **Multi-machine architecture** — Track incidents across up to 4 independent environments, each with its own database
- **Structured report workflows** — Draft → Submitted → Reviewed → Closed lifecycle with audit trail
- **Dynamic form builder** — Admin-configurable report fields per machine (no code changes needed)
- **Department status tracking** — Mark incidents as Informed or Resolved with resolution timestamps
- **Event ID system** — Auto-generated IDs in format `M{machine}-{YYYYMMDD}-{seq}` for traceability

### Threat Intelligence
- **CVE/Threat log management** — Track CVEs with severity (Critical/High/Medium/Low/Info), CVSS scores, and affected systems
- **Status lifecycle** — New → Notified → Patched → Mitigated → Closed with automatic timestamping
- **Department notification tracking** — Record which departments were notified about each threat
- **Mitigation documentation** — Capture remediation steps and affected system details

### Operational Metrics
- **MTTC (Mean Time to Contain)** — Average time from incident occurrence to report creation, per machine
- **MTTR (Mean Time to Resolve)** — Average time from report creation to resolution, per machine
- **Per-machine and overall averages** — Dashboard-level visibility into response performance
- **Today's activity counters** — Real-time count of reports created today per machine

### PDF Export
- **TCPDF integration** — Native PDF generation for incident reports and threat logs
- **HTML fallback** — Print-to-PDF view when TCPDF is not installed
- **Professional formatting** — Branded reports with severity badges, metadata grids, and timestamps

### Administration
- **User management** — Role-based access (Admin/User) with bcrypt password hashing
- **Machine configuration** — Name and configure each operational environment
- **Form builder** — Drag-and-drop field configuration per machine
- **Dropdown data management** — Centralized management of dropdown options across all forms
- **Deleted reports archive** — Soft-delete with full audit trail and restore capability
- **Audit logging** — Every significant action is logged with username and timestamp

### Security
- **CSRF protection** — Token-based protection on all forms and API endpoints
- **Prepared statements** — All database queries use parameterized PDO queries
- **Session management** — Configurable session lifetime, secure flags, HTTP-only cookies
- **Account lockout** — Automatic lockout after failed login attempts (configurable threshold)
- **Input sanitization** — HTML entity encoding on all output (`e()` helper function)

### UI/UX
- **Dark & Light theme** — Toggle between dark (default) and PS1-gray light mode with localStorage persistence
- **Bilingual** — Full English/German support with `label()` helper function
- **Responsive design** — Works on desktop and tablet screens
- **Animated sidebar** — Globe logo with orbiting train animation

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.x (no framework) |
| Database | MariaDB / MySQL (5 databases: 1 system + 4 machine) |
| Frontend | Vanilla JavaScript, CSS custom properties |
| PDF | TCPDF (optional) with HTML fallback |
| Auth | bcrypt, CSRF tokens, session-based |
| Deployment | Apache/Nginx, cross-platform installers |

---

## Architecture

```
SOC-Reporting/
├── api/                    # JSON API endpoints
│   ├── export_pdf.php      # Report PDF generation
│   ├── export_threat_pdf.php # Threat log PDF generation
│   ├── update_dept_status.php # Department status AJAX
│   ├── update_threat_status.php # Threat status AJAX
│   ├── reports.php         # Report CRUD API
│   └── charts_data.php     # Chart data endpoints
├── assets/
│   ├── css/style.css       # Theme system (dark/light variables)
│   └── js/                 # Frontend scripts
├── config/
│   ├── app.php             # Application settings
│   └── database.php.example # Database config template
├── includes/
│   ├── auth.php            # Authentication & CSRF
│   ├── db.php              # Database abstraction layer
│   ├── helpers.php         # Utility functions
│   └── session.php         # Session management
├── pages/
│   ├── dashboard.php       # Main dashboard with metrics
│   ├── reports_list.php    # Report listing with filters
│   ├── report_create.php   # Dynamic report form
│   ├── report_view.php     # Report detail view
│   ├── threat_logs.php     # Threat/CVE listing
│   ├── threat_view.php     # Threat detail view
│   ├── threat_create.php   # New threat entry
│   ├── charts.php          # Analytics charts
│   └── admin/              # Admin panel pages
├── sql/
│   ├── system.sql          # System database schema
│   ├── machine_template.sql # Machine database template
│   └── threat_logs.sql     # Threat logs table
└── index.php               # Router & main layout
```

### Database Architecture

The system uses a **multi-database design** to isolate machine data:

- `soc_system` — Users, sessions, audit logs, machines config, form fields, threat logs
- `soc_machine_1` through `soc_machine_4` — Independent report storage per environment

This design allows each operational environment to scale independently and provides data isolation between machines.

---

## Installation

### Linux / macOS

```bash
git clone https://github.com/saburovicmuamer96-jpg/SOC-Reporting-Installer.git
cd SOC-Reporting-Installer
sudo bash install.sh
```

The installer auto-detects your distribution and installs Apache, PHP, and MariaDB:
- **Ubuntu/Debian** (apt)
- **Fedora** (dnf)
- **CentOS/RHEL** (yum)
- **Arch Linux** (pacman)
- **macOS** (Homebrew)

### Windows

```
Right-click INSTALL.bat → "Run as administrator"
```

Downloads and configures XAMPP automatically.

### Manual Setup

1. Install PHP 8.x, MariaDB/MySQL, and Apache/Nginx
2. Copy `SOC-Reporting/` to your web root
3. Copy `config/database.php.example` to `config/database.php` and update credentials
4. Import SQL schemas: `system.sql`, `machine_template.sql` (for machines 1-4), `threat_logs.sql`
5. Open `http://localhost/SOC-Reporting/setup.php` to verify
6. Login with `admin` / `Admin@SOC2024`
7. **Change the password immediately**
8. Delete `setup.php`

---

## Default Credentials

| Account | Username | Password |
|---------|----------|----------|
| Application | `admin` | `Admin@SOC2024` |
| Database | `soc_admin` | `SOC_Local_2024!` |

> **Change these immediately after installation.**

---

## Server Management

```bash
# Linux/macOS
bash start-server.sh    # Start Apache + MariaDB
bash stop-server.sh     # Stop services

# Windows
START-SERVER.bat        # Start XAMPP services
STOP-SERVER.bat         # Stop XAMPP services
```

---

## Configuration

All configuration is in `SOC-Reporting/config/`:

- **`app.php`** — Session lifetime, auth settings, pagination, date formats, language
- **`database.php`** — Database host, credentials, charset (create from `.example` template)

---

## License

This project is provided as-is for educational and operational use.

---

Built by [Muamer Saburovic](https://www.hmtech.at) | [GitHub](https://github.com/saburovicmuamer96-jpg)
