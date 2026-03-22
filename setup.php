<?php
// ================================================================
//  OPTMS Invoice Manager — setup.php
//  Run this ONCE to create the database tables and admin user.
//  DELETE THIS FILE after setup is complete.
// ================================================================

// ── Safety check: only run from browser, not CLI ──────────────
if (php_sapi_name() === 'cli') { die("Run from browser.\n"); }

require_once __DIR__ . '/config/db.php';

$step    = $_POST['step'] ?? 'form';
$message = '';
$error   = '';

if ($step === 'install' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminName  = trim($_POST['admin_name']  ?? 'Admin Kumar');
    $adminEmail = trim($_POST['admin_email'] ?? 'admin@optmstech.in');
    $adminPass  = $_POST['admin_pass']  ?? '';
    $adminPass2 = $_POST['admin_pass2'] ?? '';
    $company    = trim($_POST['company'] ?? 'OPTMS Tech');
    $phone      = trim($_POST['phone']   ?? '');
    $gst        = trim($_POST['gst']     ?? '');

    if (!$adminEmail || !$adminPass) {
        $error = 'Email and password are required.';
    } elseif ($adminPass !== $adminPass2) {
        $error = 'Passwords do not match.';
    } elseif (strlen($adminPass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        try {
            $db = getDB();

            // ── Create tables ───────────────────────────────────
            $db->exec("CREATE TABLE IF NOT EXISTS users (
              id         INT AUTO_INCREMENT PRIMARY KEY,
              name       VARCHAR(100) NOT NULL,
              email      VARCHAR(150) NOT NULL UNIQUE,
              password   VARCHAR(255) NOT NULL,
              role       ENUM('admin','staff') DEFAULT 'admin',
              avatar     TEXT,
              is_active  TINYINT(1) DEFAULT 1,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $db->exec("CREATE TABLE IF NOT EXISTS settings (
              id         INT AUTO_INCREMENT PRIMARY KEY,
              `key`      VARCHAR(100) NOT NULL UNIQUE,
              value      TEXT,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $db->exec("CREATE TABLE IF NOT EXISTS clients (
              id         INT AUTO_INCREMENT PRIMARY KEY,
              name       VARCHAR(200) NOT NULL,
              person     VARCHAR(150),
              email      VARCHAR(150),
              phone      VARCHAR(30),
              whatsapp   VARCHAR(30),
              gst_number VARCHAR(20),
              address    TEXT,
              color      VARCHAR(10) DEFAULT '#00897B',
              logo       TEXT,
              is_active  TINYINT(1) DEFAULT 1,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $db->exec("CREATE TABLE IF NOT EXISTS products (
              id          INT AUTO_INCREMENT PRIMARY KEY,
              name        VARCHAR(200) NOT NULL,
              category    VARCHAR(100) DEFAULT 'Other',
              rate        DECIMAL(12,2) NOT NULL DEFAULT 0,
              hsn_code    VARCHAR(20) DEFAULT '998314',
              gst_rate    DECIMAL(5,2) DEFAULT 18.00,
              description TEXT,
              is_active   TINYINT(1) DEFAULT 1,
              created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $db->exec("CREATE TABLE IF NOT EXISTS invoices (
              id              INT AUTO_INCREMENT PRIMARY KEY,
              invoice_number  VARCHAR(50) NOT NULL UNIQUE,
              client_id       INT,
              client_name     VARCHAR(200),
              service_type    VARCHAR(100),
              issued_date     DATE,
              due_date        DATE,
              status          ENUM('Draft','Pending','Paid','Overdue','Cancelled') DEFAULT 'Draft',
              currency        VARCHAR(5) DEFAULT '₹',
              subtotal        DECIMAL(14,2) DEFAULT 0,
              discount_pct    DECIMAL(5,2) DEFAULT 0,
              discount_amt    DECIMAL(12,2) DEFAULT 0,
              gst_amount      DECIMAL(12,2) DEFAULT 0,
              grand_total     DECIMAL(14,2) DEFAULT 0,
              notes           TEXT,
              bank_details    TEXT,
              terms           TEXT,
              company_logo    TEXT,
              client_logo     TEXT,
              signature       TEXT,
              qr_code         TEXT,
              template_id     TINYINT DEFAULT 1,
              generated_by    VARCHAR(200) DEFAULT 'OPTMS Tech Invoice Manager',
              show_generated  TINYINT(1) DEFAULT 1,
              pdf_options     JSON,
              created_by      INT,
              created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $db->exec("CREATE TABLE IF NOT EXISTS invoice_items (
              id         INT AUTO_INCREMENT PRIMARY KEY,
              invoice_id INT NOT NULL,
              description VARCHAR(500) NOT NULL,
              quantity   DECIMAL(10,2) DEFAULT 1,
              rate       DECIMAL(12,2) DEFAULT 0,
              gst_rate   DECIMAL(5,2) DEFAULT 18,
              line_total DECIMAL(14,2) DEFAULT 0,
              sort_order INT DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $db->exec("CREATE TABLE IF NOT EXISTS payments (
              id             INT AUTO_INCREMENT PRIMARY KEY,
              invoice_id     INT,
              invoice_number VARCHAR(50),
              client_name    VARCHAR(200),
              amount         DECIMAL(14,2) NOT NULL,
              payment_date   DATE,
              method         VARCHAR(100),
              transaction_id VARCHAR(200),
              status         ENUM('Success','Pending','Failed') DEFAULT 'Success',
              notes          TEXT,
              created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $db->exec("CREATE TABLE IF NOT EXISTS activity_log (
              id          INT AUTO_INCREMENT PRIMARY KEY,
              user_id     INT,
              action      VARCHAR(100),
              entity_type VARCHAR(50),
              entity_id   INT,
              details     TEXT,
              ip_address  VARCHAR(45),
              created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // ── Admin user ──────────────────────────────────────
            $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
            // Remove existing admin if re-running setup
            $db->prepare("DELETE FROM users WHERE email = ?")->execute([$adminEmail]);
            $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,'admin')")
               ->execute([$adminName, $adminEmail, $hash]);

            // ── Default settings ────────────────────────────────
            $defaults = [
                'company_name'    => $company,
                'company_gst'     => $gst,
                'company_phone'   => $phone,
                'company_email'   => $adminEmail,
                'company_website' => 'www.optmstech.in',
                'company_address' => 'Patna, Bihar, India',
                'company_upi'     => '',
                'invoice_prefix'  => 'OT-' . date('Y') . '-',
                'default_gst'     => '18',
                'due_days'        => '15',
                'active_template' => '1',
                'company_logo'    => '',
                'company_sign'    => '',
            ];
            $stmt = $db->prepare("INSERT INTO settings (`key`, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?");
            foreach ($defaults as $k => $v) $stmt->execute([$k, $v, $v]);

            // ── Uploads directory ────────────────────────────────
            $uploadDir = __DIR__ . '/assets/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $message = "✅ Installation complete!<br>
              <strong>Admin Email:</strong> $adminEmail<br>
              <strong>Password:</strong> " . str_repeat('•', strlen($adminPass)) . "<br><br>
              <strong>⚠️ Delete setup.php now for security, then <a href='/' style='color:#00897B'>click here to login</a>.</strong>";

        } catch (Exception $e) {
            $error = 'Setup failed: ' . $e->getMessage();
        }
    }
}

// ── Detect if already installed ────────────────────────────────
$alreadyInstalled = false;
try {
    $db = getDB();
    $count = $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    $alreadyInstalled = ($count > 0);
} catch (Exception $e) { /* tables don't exist yet — fine */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OPTMS Invoice Manager — Setup</title>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Public Sans',sans-serif;background:linear-gradient(135deg,#1A2332,#263348 60%,#00897B);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:20px;padding:48px 44px;width:100%;max-width:480px;box-shadow:0 24px 64px rgba(0,0,0,.35)}
.logo{width:56px;height:56px;background:linear-gradient(135deg,#00897B,#4DB6AC);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;color:#fff;margin:0 auto 14px}
h1{text-align:center;font-size:22px;font-weight:800;color:#1A2332;margin-bottom:4px}
.sub{text-align:center;color:#9CA3AF;font-size:13px;margin-bottom:28px}
.step{display:flex;align-items:center;gap:10px;background:#E0F2F1;border-radius:10px;padding:12px 16px;margin-bottom:24px;font-size:13px;color:#00695C}
.step i{font-size:18px}
.field{margin-bottom:16px}
.field label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px}
.field input{width:100%;padding:11px 14px;border:1.5px solid #E5E7EB;border-radius:9px;font-family:inherit;font-size:14px;color:#111;outline:none;transition:.2s}
.field input:focus{border-color:#00897B;box-shadow:0 0 0 3px rgba(0,137,123,.12)}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.btn{width:100%;padding:14px;background:linear-gradient(135deg,#00897B,#00695C);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;margin-top:8px;transition:.2s}
.btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,137,123,.3)}
.msg{padding:14px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;line-height:1.7}
.msg.ok{background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7}
.msg.err{background:#FEE2E2;color:#DC2626;border:1px solid #FECACA}
.warn{background:#FEF3C7;border:1px solid #FCD34D;border-radius:10px;padding:12px 16px;font-size:12.5px;color:#92400E;margin-bottom:20px}
hr{border:none;border-top:1px solid #E5E7EB;margin:20px 0}
</style>
</head>
<body>
<div class="card">
  <div class="logo">OT</div>
  <h1>OPTMS Invoice Manager</h1>
  <div class="sub">One-time setup wizard</div>

  <?php if ($message): ?>
    <div class="msg ok"><?= $message ?></div>
  <?php elseif ($error): ?>
    <div class="msg err"><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!$message): ?>

  <?php if ($alreadyInstalled): ?>
  <div class="warn">
    ⚠️ <strong>Already installed.</strong> An admin user already exists.<br>
    Running setup again will reset the admin password.<br>
    <a href="/" style="color:#00897B;font-weight:600">← Go to Login</a>
  </div>
  <?php endif; ?>

  <div class="step"><i>📋</i> Fill in your details below to create the database tables and admin account.</div>

  <form method="POST">
    <input type="hidden" name="step" value="install">

    <div class="field">
      <label>Company Name</label>
      <input name="company" value="OPTMS Tech" required>
    </div>
    <div class="grid2">
      <div class="field">
        <label>Company Phone</label>
        <input name="phone" placeholder="+91 98765 43210">
      </div>
      <div class="field">
        <label>GST Number</label>
        <input name="gst" placeholder="22AAAAA0000A1Z5">
      </div>
    </div>

    <hr>

    <div class="field">
      <label>Admin Name</label>
      <input name="admin_name" value="Admin Kumar" required>
    </div>
    <div class="field">
      <label>Admin Email (used to login)</label>
      <input type="email" name="admin_email" placeholder="admin@optmstech.in" required>
    </div>
    <div class="grid2">
      <div class="field">
        <label>Password</label>
        <input type="password" name="admin_pass" placeholder="Min 6 characters" required minlength="6">
      </div>
      <div class="field">
        <label>Confirm Password</label>
        <input type="password" name="admin_pass2" placeholder="Repeat password" required>
      </div>
    </div>

    <button type="submit" class="btn">🚀 Install Now</button>
  </form>

  <?php endif; ?>
</div>
</body>
</html>
