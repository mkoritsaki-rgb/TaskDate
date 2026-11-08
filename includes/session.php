
<?php

// Ελέγχουμε αν υπάρχει ήδη ενεργή συνεδρία.
if (session_status() === PHP_SESSION_NONE) {

    // Ορίζουμε ξεχωριστό όνομα συνεδρίας για το TaskDate.
    session_name("TASKDATE_SESSION");

    // Ελέγχουμε αν η σύνδεση χρησιμοποιεί HTTPS.
    $isSecure = (
        !empty($_SERVER["HTTPS"]) &&
        $_SERVER["HTTPS"] !== "off"
    );

    // Ενεργοποιούμε αυστηρότερο έλεγχο των αναγνωριστικών συνεδρίας.
    ini_set("session.use_strict_mode", "1");

    // Επιτρέπουμε τη μεταφορά του αναγνωριστικού μόνο μέσω cookie.
    ini_set("session.use_only_cookies", "1");

    // Ορίζουμε τις ασφαλείς παραμέτρους του cookie.
    session_set_cookie_params([
        "lifetime" => 0,          // Το cookie διαρκεί όσο η συνεδρία του browser.
        "path" => "/TaskDate/",   // Το cookie αφορά το TaskDate.
        "secure" => $isSecure,   // Χρήση HTTPS όταν είναι διαθέσιμο.
        "httponly" => true,      // Αποτροπή πρόσβασης μέσω JavaScript.
        "samesite" => "Lax"      // Περιορισμός αποστολής σε cross-site αιτήματα.
    ]);

    // Ξεκινάμε τη συνεδρία.
    session_start();
}
