
<?php

// Φορτώνουμε το σύστημα γλωσσών και ξεκινάμε τη συνεδρία.
require_once __DIR__ . "/includes/language.php";

// Φορτώνουμε τις λειτουργίες αυθεντικοποίησης.
require_once __DIR__ . "/includes/auth.php";

// Επιτρέπουμε μόνο αιτήματα POST για αποσύνδεση.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

// Ελέγχουμε αν το CSRF token είναι έγκυρο.
if (!verifyCsrfToken($_POST["csrf_token"] ?? null)) {
    die(htmlspecialchars(t("auth_csrf_error"), ENT_QUOTES, "UTF-8"));
}

// Διατηρούμε το guest token της τρέχουσας συνεδρίας.
$guestToken = getGuestToken();

// Ανανεώνουμε το αναγνωριστικό της συνεδρίας.
session_regenerate_id(true);

// Διαγράφουμε τα στοιχεία του συνδεδεμένου λογαριασμού.
unset($_SESSION["user_id"]);
unset($_SESSION["user_name"]);

// Διατηρούμε τη γλώσσα που είχε επιλέξει ο χρήστης.
$currentLanguage = $_SESSION["language"] ?? "el";

// Διατηρούμε το guest token για πρόσβαση στα προηγούμενα δεδομένα επισκέπτη.
$_SESSION["guest_token"] = $guestToken;

// Δημιουργούμε νέο CSRF token για τη συνεδρία επισκέπτη.
$_SESSION["csrf_token"] = bin2hex(random_bytes(32));

// Επιστρέφουμε στην αρχική σελίδα με μήνυμα επιτυχίας.
header("Location: index.php?logout=1");
exit;
