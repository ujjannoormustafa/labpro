<?php

date_default_timezone_set('Asia/Karachi');

// Database file location.
// Electron sets LAB_DB_PATH to the user's data folder; browser testing uses ./data/
$dbPath = getenv('LAB_DB_PATH') ?: __DIR__ . '/data/labpro.db';

$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0777, true);
}

try {

    $conn = new PDO("sqlite:" . $dbPath);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $conn->exec("PRAGMA foreign_keys = ON");
    $conn->exec("PRAGMA journal_mode = WAL");

    // ---------------- TABLES (created automatically) ----------------
    $conn->exec("
        CREATE TABLE IF NOT EXISTS users (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT NOT NULL,
            email      TEXT NOT NULL UNIQUE,
            password   TEXT NOT NULL,
            role       TEXT NOT NULL DEFAULT 'admin',
            status     TEXT NOT NULL DEFAULT 'active',
            created_at TEXT DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE IF NOT EXISTS patients (
            id                INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_code      TEXT NOT NULL UNIQUE,
            name              TEXT NOT NULL,
            father_name       TEXT DEFAULT '',
            gender            TEXT DEFAULT '',
            age               INTEGER,
            phone             TEXT NOT NULL,
            email             TEXT DEFAULT '',
            address           TEXT DEFAULT '',
            registration_date TEXT DEFAULT (date('now','localtime')),
            status            TEXT DEFAULT 'active',
            created_at        TEXT DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE IF NOT EXISTS tests (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            test_code   TEXT DEFAULT '',
            test_name   TEXT NOT NULL,
            category    TEXT DEFAULT '',
            price       REAL NOT NULL DEFAULT 0,
            description TEXT DEFAULT '',
            status      TEXT DEFAULT 'active',
            created_at  TEXT DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE IF NOT EXISTS bills (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            bill_no      TEXT NOT NULL UNIQUE,
            patient_id   INTEGER NOT NULL REFERENCES patients(id),
            patient_name TEXT NOT NULL,
            total_amount REAL NOT NULL DEFAULT 0,
            discount     REAL NOT NULL DEFAULT 0,
            grand_total  REAL NOT NULL DEFAULT 0,
            paid_amount  REAL NOT NULL DEFAULT 0,
            balance      REAL NOT NULL DEFAULT 0,
            created_at   TEXT DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE IF NOT EXISTS bill_items (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            bill_id   INTEGER NOT NULL REFERENCES bills(id) ON DELETE CASCADE,
            test_id   INTEGER,
            test_name TEXT NOT NULL,
            price     REAL NOT NULL DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS expenses (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            expense_title  TEXT NOT NULL,
            category       TEXT NOT NULL,
            amount         REAL NOT NULL,
            expense_date   TEXT NOT NULL,
            payment_method TEXT DEFAULT 'Cash',
            description    TEXT DEFAULT '',
            created_at     TEXT DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE IF NOT EXISTS commissions (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            referral_name   TEXT NOT NULL,
            patient_name    TEXT DEFAULT '',
            bill_no         TEXT DEFAULT '',
            amount          REAL NOT NULL DEFAULT 0,
            commission_date TEXT NOT NULL,
            notes           TEXT DEFAULT '',
            created_at      TEXT DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE IF NOT EXISTS labpro_app_settings (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_key   TEXT NOT NULL UNIQUE,
            setting_value TEXT,
            updated_at    TEXT DEFAULT (datetime('now','localtime'))
        );
    ");

    // ---------------- DEFAULT ADMIN (first run only) ----------------
    if ((int)$conn->query("SELECT COUNT(*) FROM users")->fetchColumn() === 0) {
        $stmt = $conn->prepare(
            "INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            "LabPro Admin",
            "adminlabpro45@gmail.com",
            password_hash("Admin@123", PASSWORD_DEFAULT),
            "admin",
            "active"
        ]);
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
