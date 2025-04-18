# Insurance Claims Processing - Backend (CodeIgniter 4)

This project serves as the backend API for the web-based Insurance Claim Processing application. It's built using the CodeIgniter 4 framework and utilizes CodeIgniter Shield for robust authentication and authorization, providing a RESTful API consumed by the [React Frontend](link-to-your-frontend-repo-if-applicable).

## Features

*   **RESTful API:** Provides endpoints for managing claims, users, and authentication.
*   **JWT Authentication:** Secure stateless authentication using JSON Web Tokens handled by CodeIgniter Shield.
*   **Role-Based Access Control (RBAC):** Implements distinct roles (Claimant, Reviewer/Maker, Checker) using Shield Groups, ensuring users can only access appropriate endpoints and data.
*   **Claims Workflow:** Supports the core claims lifecycle:
    *   Claim submission by Claimants (including file uploads).
    *   Fetching claims based on user role (own claims, assigned claims, all claims).
    *   Assigning pending claims to reviewers (Checker).
    *   Reviewer actions: Adding notes, uploading documents, submitting for approval.
    *   Checker actions: Final approval or denial (with reasons).
*   **Database Interaction:** Uses CodeIgniter's Model layer for interacting with the database (MySQL configured by default, easily adaptable to others like PostgreSQL or SQLite).
*   **CORS Handling:** Configured to allow requests from the designated frontend application origin.

## Tech Stack

*   **Framework:** CodeIgniter 4 (v4.6 or later recommended)
*   **Authentication/Authorization:** CodeIgniter Shield
*   **Database:** MySQL (default, configured via `.env`), adaptable to PostgreSQL, SQLite3
*   **API Type:** RESTful API (using ResourceController)

## Prerequisites

*   PHP (v8.1 or later recommended - check [CodeIgniter requirements](https://codeigniter.com/user_guide/intro/requirements.html))
*   Composer ([https://getcomposer.org/](https://getcomposer.org/))
*   Web Server (Apache or Nginx recommended) with URL rewriting enabled.
*   Database Server (MySQL/MariaDB, PostgreSQL, or SQLite)
*   **Required PHP Extensions:**
    *   `intl` (for internationalization features often used by frameworks)
    *   `mbstring` (for multibyte string handling)
    *   `json` (for handling JSON API requests/responses)
    *   `mysqlnd` (if using MySQL/MariaDB with MySQLi driver) or relevant driver for your chosen database (e.g., `pdo_pgsql`, `pdo_sqlite`, `sqlite3`)
    *   `curl` (often used by libraries for HTTP requests, though less critical for the basic API itself)
    *   `openssl` (for JWT generation/validation and HTTPS)
    *   Check the "Server Requirements" section of the CodeIgniter documentation for the most up-to-date list.

## Project Setup & Installation

1.  **Clone the Repository (if applicable):**
    ```bash
    git clone <your-backend-repository-url>
    cd <repository-folder-name>
    ```

2.  **Install Dependencies:**
    ```bash
    composer install
    ```
    This installs CodeIgniter, Shield, and other required PHP packages.

3.  **Configure Environment (`.env`):**
    *   Copy the `.env.example` file (if provided) or the standard CodeIgniter `env` file to `.env` in the project root.
    *   **Crucially, edit the `.env` file** and configure the following sections based on your local development environment:

        ```dotenv
        #--------------------------------------------------------------------
        # ENVIRONMENT
        #--------------------------------------------------------------------
        # Set to 'development' for local work, 'production' for deployment
        CI_ENVIRONMENT=development

        #--------------------------------------------------------------------
        # APP
        #--------------------------------------------------------------------
        # Set the base URL of your backend application
        # IMPORTANT: Include the trailing slash!
        # Example for php spark serve:
        app.baseURL='http://localhost:8080/'
        # Example for Valet/custom domain:
        # app.baseURL='http://insurance-claims-ci.test/'

        #--------------------------------------------------------------------
        # DATABASE (Example for MySQL - modify for your setup)
        #--------------------------------------------------------------------
        # If using MySQL/MariaDB (uncomment and fill):
        database.default.hostname=127.0.0.1 # Or localhost on windows
        database.default.database=claims      # CHANGE to your database name
        database.default.username=root        # CHANGE to your DB username
        database.default.password=YourDbPassword # CHANGE to your DB password (use quotes if needed)
        database.default.DBDriver=MySQLi
        # database.default.port = 3306

        # If using SQLite (uncomment and configure):
        # database.default.DBDriver   = SQLite3
        # database.default.database   = database/claims_db.sqlite # Path relative to WRITEPATH
        # database.default.foreignKeys = true

        # If using PostgreSQL (uncomment and configure):
        # database.default.hostname=127.0.0.1
        # database.default.database=claims
        # database.default.username=your_pg_user
        # database.default.password=YourPgPassword
        # database.default.DBDriver=PostgreSQL
        # database.default.port = 5432
        # database.default.schema = public


        #--------------------------------------------------------------------
        # AUTHENTICATION (Shield JWT)
        #--------------------------------------------------------------------
        # Generate a strong, random key (e.g., `openssl rand -base64 32` in terminal)
        # and paste it here. KEEP THIS SECRET!
        auth.jwt.secretKey=YOUR_STRONG_RANDOM_JWT_SECRET_KEY_HERE
        ```

4.  **Install CodeIgniter Shield:**
    *   Ensure Shield is listed in your `composer.json` dependencies. If not already installed via `composer install`:
        ```bash
        composer require codeigniter4/shield
        ```
    *   Run the Shield installation command:
        ```bash
        php spark shield:install
        ```
        *(This publishes necessary config files like `Auth.php`, `AuthGroups.php`, `AuthToken.php`, etc. to your `app/Config/` directory).*

5.  **Run Database Migrations:**
    *   Make sure your database connection details in `.env` are correct and the database exists (or your user has privileges to create it).
    *   Run CodeIgniter's base migrations first (if it's a fresh project):
        ```bash
        php spark migrate --all
        ```
    *   Run Shield's migrations AND your application's custom migrations (for claims, claim_types, claim_documents, etc.):
        ```bash
        php spark migrate
        ```
        *(This creates all necessary tables like `users`, `auth_identities`, `auth_groups_users`, `claims`, `claim_types`, `claim_documents`, etc.).*

6.  **Define Roles and Permissions (If not done via published files):**
    *   Edit `app/Config/AuthGroups.php`. Ensure the roles `claimant`, `reviewer`, `checker` (and potentially associated permissions) are defined as discussed previously. Reference the ["Define Roles" section in Phase 1 of the backend setup](link-to-previous-readme-section-or-documentation) for the required structure.

7.  **Configure Shield for JWT & CORS:**
    *   **Authentication:** Edit `app/Config/Auth.php`. Set `public string $defaultAuthenticator = 'tokens';`. Ensure the `tokens` authenticator is listed in `$authenticators`. Review JWT settings like `$tokenValidity`.
    *   **CORS:** Edit `app/Config/Cors.php` (the standard location in recent CI4). Ensure `$default['allowedOrigins']` includes your React frontend's development URL (e.g., `'http://localhost:5173'`). Ensure `$default['allowedMethods']` includes `GET`, `POST`, `PATCH`, `DELETE`, `OPTIONS`. Ensure `$default['allowedHeaders']` includes `Content-Type` and `Authorization`.
    *   **Filters:** Edit `app/Config/Filters.php`. Ensure the `cors` filter alias points to `\CodeIgniter\Filters\Cors::class`. Apply the `cors` filter **either** globally (`$globals['before']`) **or** specifically to your `/api` route group (`'filter' => 'cors'` in the group definition in `Routes.php`). See [CORS Troubleshooting](link-to-cors-troubleshooting-info) if needed. Also ensure the `auth:tokens` alias points to `\CodeIgniter\Shield\Filters\TokenAuth::class`.

8.  **Create Initial Users and Assign Roles:**
    *   Use Shield's Spark commands to create test users for each role:
        ```bash
        # Create users (replace with desired credentials)
        php spark shield:user create --username claimant1 --email claimant1@example.com --password password123
        php spark shield:user create --username reviewer1 --email reviewer1@example.com --password password123
        php spark shield:user create --username checker1 --email checker1@example.com --password password123

        # Assign groups/roles
        php spark shield:user addgroup claimant1 claimant
        php spark shield:user addgroup reviewer1 reviewer
        php spark shield:user addgroup checker1 checker
        ```
    *   Refer to the [CodeIgniter Shield documentation](https://shield.codeigniter.com/user_guide/manage_users/managing_users/) for more user management commands.

9.  **Set File Permissions:**
    *   Ensure the `writable/` directory (and its subdirectories, especially `writable/uploads/` if created by your controllers) is writable by the web server process (e.g., `www-data`, `apache`, `nginx`). This is crucial for session files (if using file handler), logs, cache, and file uploads.
    *   Consult CodeIgniter's [installation documentation](https://codeigniter.com/user_guide/installation/installing_composer.html#running-locally) for recommended permissions. A common starting point is `sudo chown -R $USER:www-data writable && sudo chmod -R 775 writable`.

## Running the Application

1.  **Using Spark's Built-in Server (for development):**
    ```bash
    php spark serve
    ```
    This will typically start the server at `http://localhost:8080`. Make sure this matches the host/port used in your frontend's `VITE_API_BASE_URL` (without the `/api` part).

2.  **Using a Local Development Environment (Valet, Laragon, XAMPP, etc.):**
    *   Configure your virtual host or server settings to point the document root to the `public/` directory of your CodeIgniter project.
    *   Ensure URL rewriting (`.htaccess` for Apache, `try_files` for Nginx) is enabled and configured correctly for CodeIgniter 4 to handle routing via `public/index.php`.
    *   Access the application via the URL you configured (e.g., `http://insurance-claims-ci.test/`).

## API Endpoints Overview

*(Generated by CodeIgniter Routes combined with Namespaces)*

*   **Auth:**
    *   `POST /api/auth/login`
    *   `DELETE /api/auth/logout` (Requires Auth Token)
*   **Claimant:** (Requires Auth Token)
    *   `GET /api/claimant/claims`
    *   `POST /api/claimant/claims`
    *   `GET /api/claimant/claims/{id}`
*   **Reviewer:** (Requires Auth Token)
    *   `GET /api/reviewer/claims`
    *   `GET /api/reviewer/claims/{id}`
    *   `PATCH /api/reviewer/claims/{id}/submit-for-approval`
*   **Checker:** (Requires Auth Token)
    *   `GET /api/checker/claims`
    *   `GET /api/checker/claims?status={status}` (Optional filtering)
    *   `GET /api/checker/claims/{id}`
    *   `PATCH /api/checker/claims/{id}/assign`
    *   `PATCH /api/checker/claims/{id}/approve`
    *   `PATCH /api/checker/claims/{id}/deny`
    *   `GET /api/checker/users?role=reviewer`

*(Remember that authorization based on role and data ownership is handled within the respective controller methods).*

## Project Structure (Backend Overview)

```plaintext
insurance-claims-ci/
├── app/
│   ├── Config/
│   │   ├── App.php         # Base App Config
│   │   ├── Auth.php        # Shield Auth Config
│   │   ├── AuthGroups.php  # Role/Group Definitions
│   │   ├── Cors.php        # CORS Configuration
│   │   ├── Filters.php     # Filter Aliases & Application
│   │   ├── Routes.php      # API Route Definitions
│   │   └── ...             # Other config files
│   ├── Controllers/
│   │   └── API/
│   │       ├── AuthController.php
│   │       ├── Claimant/
│   │       │   └── ClaimsController.php
│   │       ├── Reviewer/
│   │       │   └── ClaimsController.php
│   │       └── Checker/
│   │           ├── ClaimsController.php
│   │           └── UsersController.php
│   ├── Database/
│   │   ├── Migrations/     # Database migration files
│   │   └── Seeds/          # Database seeders (optional)
│   ├── Filters/            # Custom filters (e.g., ApiAuthFilter if used)
│   ├── Helpers/            # Custom helpers
│   ├── Language/           # Language files
│   ├── Models/             # Database models (ClaimModel, ClaimTypeModel, etc.)
│   │                       # Includes Shield's UserModel if published
│   ├── ThirdParty/         # Third-party libraries not managed by Composer
│   └── Views/              # Views (less relevant for pure API)
├── public/                 # Web server document root
│   └── index.php           # Application entry point
│   └── .htaccess           # Apache rewrite rules (if applicable)
├── writable/               # Directory needs write permissions by web server
│   ├── cache/
│   ├── logs/
│   ├── session/
│   ├── uploads/            # Directory for file uploads
│   │   ├── claims/
│   │   └── reviewer_docs/
│   └── database/           # Location for SQLite DB if used
├── tests/                  # Application tests
├── vendor/                 # Composer dependencies
├── .env                    # Environment configuration (!!DO NOT COMMIT SECRETS!!)
├── composer.json           # Composer definition file
├── phpunit.xml.dist
└── spark                   # CodeIgniter CLI tool
```

## Further Resources

*   **CodeIgniter 4 User Guide:** [https://codeigniter.com/user_guide/index.html](https://codeigniter.com/user_guide/index.html) - The primary documentation for the framework.
*   **CodeIgniter Shield Documentation:** [https://shield.codeigniter.com/user_guide/index.html](https://shield.codeigniter.com/user_guide/index.html) - Specific documentation for the authentication/authorization library.
*   **CodeIgniter API Response Trait:** [https://codeigniter.com/user_guide/libraries/api_responses.html](https://codeigniter.com/user_guide/libraries/api_responses.html) - Details on standardized API response methods.
*   **CodeIgniter Migrations:** [https://codeigniter.com/user_guide/dbmgmt/migration.html](https://codeigniter.com/user_guide/dbmgmt/migration.html) - Information on managing database schema changes.
*   **CodeIgniter Models:** [https://codeigniter.com/user_guide/models/model.html](https://codeigniter.com/user_guide/models/model.html) - How to interact with your database using Models.
*   **CodeIgniter Filters:** [https://codeigniter.com/user_guide/incoming/filters.html](https://codeigniter.com/user_guide/incoming/filters.html) - Understanding how to intercept requests and responses.
*   **CodeIgniter Routing:** [https://codeigniter.com/user_guide/incoming/routing.html](https://codeigniter.com/user_guide/incoming/routing.html) - Defining how URLs map to controllers.