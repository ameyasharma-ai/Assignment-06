<?php
// contact.php - Customer Contact & Feedback Form
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = getDBConnection();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $error = 'Please fill out all fields in the contact form.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Save contact details to the feedback table
            $stmt = $db->prepare("INSERT INTO feedback (name, email, message) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $message]);

            // Mock Email Notification Trigger
            // In a production server, this uses mail() or PHPMailer. Here we log it to support audit.
            $emailSubject = "OmniMart Customer Feedback: $name";
            $emailBody = "Name: $name\nEmail: $email\nMessage:\n$message";
            $emailHeaders = "From: webmaster@omnimart.com";
            
            // Log mock email to errors or logs file
            error_log("MOCK EMAIL SENT:\nSubject: $emailSubject\nHeaders: $emailHeaders\nBody:\n$emailBody");

            $success = 'Thank you! Your feedback has been recorded, and an email notification has been dispatched to our support queue.';
        } catch (Exception $e) {
            $error = 'Could not save feedback: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 48px; margin-top: 20px; align-items: center;">
    <!-- Contact Info -->
    <div>
        <span class="badge badge-primary" style="margin-bottom: 12px; padding: 6px 12px;">Get In Touch</span>
        <h2 style="font-size: 3rem; line-height: 1.1; margin-bottom: 20px;">We'd Love to Hear From You</h2>
        <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 30px; line-height: 1.6;">
            Have questions about vendor registration, order delivery status, or custom discount partnerships? Send us a message and our support team will respond within 24 hours.
        </p>

        <div style="display: flex; flex-direction: column; gap: 20px;">
            <div style="display: flex; gap: 16px; align-items: center;">
                <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(139, 92, 246, 0.1); color: var(--primary-light); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="fa-solid fa-envelope"></i>
                </div>
                <div>
                    <span style="font-size: 0.8rem; color: var(--text-muted); display: block; text-transform: uppercase;">Email Us</span>
                    <strong>support@omnimart.com</strong>
                </div>
            </div>
            <div style="display: flex; gap: 16px; align-items: center;">
                <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(14, 165, 233, 0.1); color: var(--secondary); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="fa-solid fa-map-location-dot"></i>
                </div>
                <div>
                    <span style="font-size: 0.8rem; color: var(--text-muted); display: block; text-transform: uppercase;">Headquarters</span>
                    <strong>100 Silicon Blvd, Suite 400, San Jose, CA</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Form Card -->
    <div class="glass-panel" style="padding: 40px; border-radius: 20px;">
        <h3 style="margin-bottom: 24px;">Send Message</h3>

        <?php if (!empty($error)): ?>
            <div class="badge badge-danger" style="display: block; padding: 12px; margin-bottom: 20px; border-radius: 8px;">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="badge badge-success" style="display: block; padding: 12px; margin-bottom: 20px; border-radius: 8px;">
                <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form action="contact.php" method="POST">
            <div class="form-group">
                <label for="name">Your Name</label>
                <input type="text" name="name" id="name" class="form-control" required placeholder="e.g. Alice Smith"
                       value="<?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" required placeholder="e.g. alice@example.com"
                       value="<?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="message">Message Body</label>
                <textarea name="message" id="message" rows="5" class="form-control" required placeholder="How can we help you today?"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 12px;">
                Submit Feedback <i class="fa-regular fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
