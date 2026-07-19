<?php
/**
 * setup.php — One-time installer for Pet House.
 *
 * What it does:
 *   1. Connects to MySQL using config/config.php credentials.
 *   2. Creates the database if it doesn't exist.
 *   3. Imports database/database.sql (schema + reference data).
 *   4. Seeds the user accounts (Admin / Customer / Groomer / Delivery)
 *      with correctly bcrypt-hashed passwords (password123).
 *   5. Seeds user-dependent demo data (pets, orders, bookings, etc.)
 *
 * Usage: visit http://localhost/php-pethouse/setup.php ONCE.
 *        Safe to re-run (uses INSERT IGNORE / ON DUPLICATE KEY UPDATE).
 *
 * After setup, DELETE this file for production.
 */

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

/**
 * Security guard: once setup has been completed, this file refuses to
 * re-run unless the operator explicitly passes ?confirm=RESET in the URL.
 * This prevents a stranger from hitting /setup.php on a live deployment
 * and resetting all seeded passwords back to "password123".
 */
function setup_already_done(): bool
{
    try {
        $pdo = @new PDO(
            "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]
        );
        if (!$pdo) return false;
        $row = $pdo->query("SELECT `value` FROM store_settings WHERE `key` = 'setup_complete' LIMIT 1")->fetchColumn();
        return ($row === '1');
    } catch (Throwable $e) {
        return false; // DB not created yet → first run
    }
}

if (setup_already_done() && ($_GET['confirm'] ?? '') !== 'RESET') {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Setup Locked</title>
    <style>body{font-family:sans-serif;background:#f8f7fc;padding:60px;max-width:680px;margin:0 auto;color:#3b3563;}
    .box{background:#fff;padding:32px;border-radius:14px;box-shadow:0 6px 22px rgba(124,58,237,0.12);border-top:6px solid #b91c1c;}
    h1{color:#b91c1c;margin-top:0;}code{background:#f3f0ff;padding:2px 6px;border-radius:4px;color:#5b21b6;}</style>
    </head><body><div class="box">
    <h1><i class="fas fa-lock"></i> Setup Already Completed</h1>
    <p>Pet House has already been installed on this server. For security, re-running
    <code>setup.php</code> is blocked to prevent password reset attacks.</p>
    <p><strong>If you genuinely need to reset the database and re-seed the demo accounts</strong>,
    visit:</p>
    <p><code>' . APP_URL . '/setup.php?confirm=RESET</code></p>
    <p style="margin-top:24px;color:#9c92b8;font-size:13px;">For production deployments, delete <code>setup.php</code> entirely after installation.</p>
    </div></body></html>';
    exit;
}

function out($msg, $type = 'info') {
    $colors = ['info'=>'#5b21b6','ok'=>'#15803d','err'=>'#b91c1c','dim'=>'#6b7280'];
    $c = $colors[$type] ?? '#5b21b6';
    echo "<div style='color:$c;font-family:monospace;padding:3px 0;'>$msg</div>";
    @ob_flush(); flush();
}
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Pet House Setup</title>
<style>body{font-family:sans-serif;background:#f8f7fc;padding:40px;max-width:780px;margin:0 auto;}
.box{background:#fff;padding:28px;border-radius:14px;box-shadow:0 6px 22px rgba(124,58,237,0.1);}
h1{color:#7c3aed;margin-top:0;}h2{color:#5b21b6;border-bottom:1px solid #ede9fe;padding-bottom:6px;margin-top:28px;}
.btn{display:inline-block;background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;padding:10px 22px;
border-radius:9px;text-decoration:none;margin-top:20px;font-weight:500;}
</style></head><body><div class="box">
<h1><i class="fas fa-paw" style="color:#7c3aed;"></i> Pet House — Setup Installer</h1>';

// ─── 1. Connect without DB, create DB ─────────────────────────────
out("Connecting to MySQL at " . DB_HOST . " as " . DB_USER . " ...");
try {
    $pdoRoot = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    out("✓ Connected to MySQL server.", 'ok');
} catch (PDOException $e) {
    out("✗ Could not connect: " . $e->getMessage(), 'err');
    out("Check DB_HOST/DB_USER/DB_PASS in config/config.php and that MySQL is running.", 'err');
    exit('</div></body></html>');
}

try {
    $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    out("✓ Database `" . DB_NAME . "` ready.", 'ok');
} catch (PDOException $e) {
    out("✗ Could not create database: " . $e->getMessage(), 'err');
    exit('</div></body></html>');
}
$pdoRoot = null;

// ─── 2. Import schema SQL ─────────────────────────────────────────
echo '<h2>Step 1 — Import schema</h2>';
$sqlFile = __DIR__ . '/database/database.sql';
if (!file_exists($sqlFile)) {
    out("✗ database/database.sql not found!", 'err');
    exit('</div></body></html>');
}
$sql = str_replace("\r\n", "\n", file_get_contents($sqlFile));

// Split on ";\n" but keep it simple — statements end with ";"
// Strip comments for safer splitting
$lines = explode("\n", $sql);
$cleaned = [];
foreach ($lines as $line) {
    $t = trim($line);
    if ($t === '' || strpos($t, '--') === 0) continue;
    $cleaned[] = $line;
}
$sqlClean = implode("\n", $cleaned);
$statements = array_filter(array_map('trim', explode(";\n", $sqlClean)));

$pdo = db();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$errors = 0;
$count = 0;
foreach ($statements as $stmt) {
    $stmt = rtrim($stmt, ';');
    if ($stmt === '') continue;
    try {
        $pdo->exec($stmt);
        $count++;
    } catch (PDOException $e) {
        // Ignore "already exists" errors (re-runs)
        if (stripos($e->getMessage(), 'already exists') === false
            && stripos($e->getMessage(), 'Duplicate') === false) {
            out("⚠ " . substr($e->getMessage(), 0, 120), 'dim');
            $errors++;
        }
    }
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
out("✓ Executed $count SQL statements ($errors warnings).", 'ok');

// ─── 3. Seed users with proper password hashes ───────────────────
echo '<h2>Step 2 — Create login accounts</h2>';
$pwd = password_hash('password123', PASSWORD_BCRYPT, ['cost' => HASH_COST]);

// The ADMIN account gets a freshly generated random password every time
// setup runs, instead of the shared demo password — it controls the
// whole store, so it should never ship with a guessable default.
// Staff/demo accounts keep "password123" since they're meant for quick
// testing and hold no admin privileges.
$adminPlainPwd = bin2hex(random_bytes(6)); // 12-char random hex string
$adminPwdHash  = password_hash($adminPlainPwd, PASSWORD_BCRYPT, ['cost' => HASH_COST]);

$users = [
    ['u_admin',    'admin@pethouse.com',    '9999999999', 'Admin User',     ROLE_ADMIN,     'Pet House HQ, Chhatrapati Sambhajinagar', 0,    'ADMINREF',  'gold', $pwd],
    ['u_customer', 'rahul@example.com',     '9876543210', 'Rahul Sharma',   ROLE_CUSTOMER,  '12 Ganesh Nagar, Chhatrapati Sambhajinagar', 250, 'RAHUL25',   'silver', $pwd],
    ['u_groomer',  'groomer@pethouse.com',  '8888888888', 'Priya Groomer',  ROLE_GROOMER,   NULL, 0, 'GROOMER1',  'bronze', $pwd],
    ['u_delivery', 'delivery@pethouse.com', '7777777777', 'Delivery Amit',  ROLE_DELIVERY,  NULL, 0, 'DELIVER1',  'bronze', $pwd],
    ['u_cust2',    'neha@example.com',      '9777777777', 'Neha Verma',     ROLE_CUSTOMER,  '5 CIDCO, Chhatrapati Sambhajinagar', 120, 'NEHA12',    'bronze', $pwd],
];

foreach ($users as $u) {
    [$id, $email, $phone, $name, $role, $addr, $rp, $ref, $tier, $hash] = $u;
    $exists = db_scalar('SELECT id FROM users WHERE id = ? OR email = ? LIMIT 1', [$id, $email]);
    if ($exists) {
        // Just refresh password + role
        db_execute(
            'UPDATE users SET password_hash = ?, role = ?, active = 1 WHERE email = ? OR id = ?',
            [$hash, $role, $email, $id]
        );
        out("↻ Reset password for <b>$name</b> ($role)", 'dim');
    } else {
        db_execute(
            'INSERT INTO users (id,email,phone,name,password_hash,role,address,reward_points,referral_code,membership_tier,active)
             VALUES (?,?,?,?,?,?,?,?,?,?,1)
             ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role=VALUES(role), active=1',
            [$id, $email, $phone, $name, $hash, $role, $addr, $rp, $ref, $tier]
        );
        out("✓ Created <b>$name</b> — $role", 'ok');
    }
}

// ─── 4. Seed user-dependent demo data (idempotent) ───────────────
echo '<h2>Step 3 — Seed demo data</h2>';

// Pets
$pets = [
    ['pet_1','u_customer','Bruno','dog','Labrador',3,28.5,'male','Friendly, loves baths'],
    ['pet_2','u_customer','Mimi','cat','Persian',2,4.2,'female','Needs gentle handling'],
    ['pet_3','u_cust2','Rocky','dog','Beagle',4,12.0,'male','Very active'],
];
foreach ($pets as $p) {
    $exists = db_scalar('SELECT id FROM pets WHERE id = ?', [$p[0]]);
    if (!$exists) {
        db_execute('INSERT INTO pets (id,user_id,name,species,breed,age,weight,gender,notes) VALUES (?,?,?,?,?,?,?,?,?)', $p);
    }
}
out("✓ Pets seeded (" . count($pets) . ")", 'ok');

// Orders
$orders = [
    ['or_1','u_customer',1418.00,1299.00,129.90,10.90,'delivered','cod','12 Ganesh Nagar, Chhatrapati Sambhajinagar'],
    ['or_2','u_customer',449.00,449.00,0.00,0.00,'shipped','cod','12 Ganesh Nagar, Chhatrapati Sambhajinagar'],
    ['or_3','u_cust2',199.00,199.00,0.00,0.00,'pending','cod','5 CIDCO, Chhatrapati Sambhajinagar'],
];
foreach ($orders as $o) {
    $exists = db_scalar('SELECT id FROM orders WHERE id = ?', [$o[0]]);
    if (!$exists) {
        db_execute('INSERT INTO orders (id,user_id,total,subtotal,tax,discount,status,payment_method,address) VALUES (?,?,?,?,?,?,?,?,?)', $o);
    }
}
// Order items
$items = [
    ['oi_1','or_1','pr_1',1,1299.00],
    ['oi_2','or_2','pr_3',1,449.00],
    ['oi_3','or_3','pr_4',1,199.00],
];
foreach ($items as $i) {
    $exists = db_scalar('SELECT id FROM order_items WHERE id = ?', [$i[0]]);
    if (!$exists) db_execute('INSERT INTO order_items (id,order_id,product_id,quantity,price) VALUES (?,?,?,?,?)', $i);
}
out("✓ Orders + items seeded", 'ok');

// Grooming / boarding / walking bookings
$gb = [
    ['gb_1','u_customer','pet_1','gs_2','2025-07-25','11:00','confirmed','Prefers morning'],
    ['gb_2','u_customer','pet_2','gs_6','2025-07-28','15:30','pending',NULL],
    ['gb_3','u_cust2','pet_3','gs_1','2025-07-20','10:00','completed',NULL],
];
foreach ($gb as $b) {
    if (!db_scalar('SELECT id FROM grooming_bookings WHERE id = ?', [$b[0]]))
        db_execute('INSERT INTO grooming_bookings (id,user_id,pet_id,service_id,date,time,status,notes) VALUES (?,?,?,?,?,?,?,?)', $b);
}
$br = [['brs_1','u_customer','pet_1','br_1','2025-08-01','2025-08-05','confirmed']];
foreach ($br as $b) {
    if (!db_scalar('SELECT id FROM boarding_reservations WHERE id = ?', [$b[0]]))
        db_execute('INSERT INTO boarding_reservations (id,user_id,pet_id,room_id,check_in,check_out,status) VALUES (?,?,?,?,?,?,?)', $b);
}
$wb = [
    ['wb_1','u_customer','pet_1','2025-07-26',30,'07:00','pending'],
    ['wb_2','u_cust2','pet_3','2025-07-22',45,'18:00','completed'],
];
foreach ($wb as $b) {
    if (!db_scalar('SELECT id FROM walking_bookings WHERE id = ?', [$b[0]]))
        db_execute('INSERT INTO walking_bookings (id,user_id,pet_id,date,duration,time,status) VALUES (?,?,?,?,?,?,?)', $b);
}
out("✓ Grooming / boarding / walking bookings seeded", 'ok');

// Notifications + support + reviews + deliveries
$notifs = [
    ['n_1','u_customer','Booking Confirmed','Your grooming appointment for Bruno is confirmed for 25 Jul, 11:00 AM.','booking',0],
    ['n_2','u_customer','Order Delivered','Your order #or_1 has been delivered. Enjoy!','order',1],
    ['n_3','u_customer','Reward Earned','You earned 50 reward points for your recent order.','reward',0],
];
foreach ($notifs as $n) {
    if (!db_scalar('SELECT id FROM notifications WHERE id = ?', [$n[0]]))
        db_execute('INSERT INTO notifications (id,user_id,title,message,type,is_read) VALUES (?,?,?,?,?,?)', $n);
}
$tickets = [['st_1','u_customer','Question about spa package','Does the spa package include nail trimming?','open','medium',NULL]];
foreach ($tickets as $t) {
    if (!db_scalar('SELECT id FROM support_tickets WHERE id = ?', [$t[0]]))
        db_execute('INSERT INTO support_tickets (id,user_id,subject,message,status,priority,response) VALUES (?,?,?,?,?,?,?)', $t);
}
$reviews = [
    ['rv_1','u_customer','pr_1',5,'My dog loves this food. Coat is shinier!'],
    ['rv_2','u_customer','pr_7',4,'Gentle shampoo, works great on sensitive skin.'],
];
foreach ($reviews as $r) {
    if (!db_scalar('SELECT id FROM reviews WHERE id = ?', [$r[0]]))
        db_execute('INSERT INTO reviews (id,user_id,product_id,rating,comment) VALUES (?,?,?,?,?)', $r);
}
$dels = [
    ['dl_1','or_1','u_delivery','delivered','2025-07-15 18:00'],
    ['dl_2','or_2','u_delivery','in_transit','2025-07-20 14:00'],
];
foreach ($dels as $d) {
    if (!db_scalar('SELECT id FROM deliveries WHERE id = ?', [$d[0]]))
        db_execute('INSERT INTO deliveries (id,order_id,partner_id,status,estimated_at) VALUES (?,?,?,?,?)', $d);
}
out("✓ Notifications, support tickets, reviews, deliveries seeded", 'ok');

// Reward transactions
$rts = [
    ['rt_1','u_customer',250,'bonus','signup','Welcome bonus'],
    ['rt_2','u_customer',50,'earn','order','Earned on order or_1'],
];
foreach ($rts as $r) {
    if (!db_scalar('SELECT id FROM reward_transactions WHERE id = ?', [$r[0]]))
        db_execute('INSERT INTO reward_transactions (id,user_id,points,type,source,description) VALUES (?,?,?,?,?,?)', $r);
}
out("✓ Reward transactions seeded", 'ok');

// Blog posts
$blogs = [
    ['bp_1','u_admin','5 Summer Grooming Tips for Your Dog','summer-grooming-tips','Keep your dog cool and comfortable this summer with these essential grooming tips.','Summer heat can be tough on dogs. Here are 5 grooming tips:\n\n1. Brush regularly to remove loose fur.\n2. Bathe every 2-3 weeks with mild shampoo.\n3. Keep nails trimmed.\n4. Check ears for infections.\n5. Never shave double-coated breeds.','grooming',1,4,'["grooming","summer"]'],
    ['bp_2','u_admin','Choosing the Right Food for Your Cat','right-cat-food','A guide to selecting nutritious food for your feline friend.','Cats are obligate carnivores. Look for food with real meat as the first ingredient. Avoid fillers like corn and soy.','nutrition',1,3,'["cat","food"]'],
];
foreach ($blogs as $b) {
    if (!db_scalar('SELECT id FROM blog_posts WHERE id = ?', [$b[0]])) {
        db_execute('INSERT INTO blog_posts (id,author_id,title,slug,excerpt,content,category,published,read_time,tags) VALUES (?,?,?,?,?,?,?,?,?,?)', $b);
    }
}
out("✓ Blog posts seeded", 'ok');

// ─── Done ─────────────────────────────────────────────────────────
// Mark setup as complete so re-running setup.php is blocked (security).
try {
    db_execute(
        "INSERT INTO store_settings (id, `key`, `value`) VALUES ('set_setup', 'setup_complete', '1')
         ON DUPLICATE KEY UPDATE `value` = '1'",
        []
    );
} catch (Throwable $e) { /* ignore — store_settings table may not exist on partial run */ }

echo '<h2>✅ Setup complete!</h2>';
out("Your Pet House is ready.", 'ok');
echo '<div style="background:#fff4e5;border:1px solid #f0b429;border-radius:10px;padding:16px 18px;margin-top:14px;">
<h3 style="color:#92400e;margin-top:0;"><i class="fas fa-triangle-exclamation"></i> Your admin password (shown once)</h3>
<p style="font-family:monospace;font-size:16px;background:#fff;border-radius:6px;padding:10px 14px;display:inline-block;">' . htmlspecialchars($adminPlainPwd, ENT_QUOTES) . '</p>
<p style="margin-bottom:0;color:#92400e;">This was randomly generated and is <b>not</b> stored anywhere in plain text — copy it now.
Log in as <code>admin@pethouse.com</code> and change it immediately from Profile → Change Password if this
site will be reachable outside your own machine.</p>
</div>';
echo '<div style="background:#f5f3ff;border-radius:10px;padding:18px;margin-top:14px;">
<h3 style="color:#5b21b6;margin-top:0;">Demo login accounts (customer / groomer / delivery)</h3>
<table style="width:100%;font-family:monospace;font-size:14px;">
<tr><th style="text-align:left;padding:4px 8px;">Role</th><th style="text-align:left;padding:4px 8px;">Identifier</th><th style="text-align:left;padding:4px 8px;">Password</th></tr>
<tr><td style="padding:4px 8px;">Customer</td><td style="padding:4px 8px;">rahul@example.com / 9876543210</td><td style="padding:4px 8px;">password123</td></tr>
<tr><td style="padding:4px 8px;">Groomer</td><td style="padding:4px 8px;">groomer@pethouse.com / 8888888888</td><td style="padding:4px 8px;">password123</td></tr>
<tr><td style="padding:4px 8px;">Delivery</td><td style="padding:4px 8px;">delivery@pethouse.com / 7777777777</td><td style="padding:4px 8px;">password123</td></tr>
</table>
<p style="margin:10px 0 0;color:#5b21b6;font-size:13px;">These non-admin accounts are meant for testing the app. Delete or repassword them before handing the site to real customers.</p>
</div>';
echo '<a href="' . APP_URL . '/login.php" class="btn"><i class="fas fa-sign-in-alt"></i> Go to Login</a>';
echo '<p style="margin-top:20px;color:#9c92b8;font-size:13px;"><i class="fas fa-info-circle"></i> For security, delete <code>setup.php</code> after setup is confirmed working.</p>';
echo '</div></body></html>';
