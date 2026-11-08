
<?php

// Ελέγχουμε αν υπάρχει ήδη ενεργή συνεδρία.
// Το language.php πρέπει να έχει φορτωθεί πριν από αυτό το αρχείο.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ελέγχουμε αν ο επισκέπτης έχει συνδεθεί σε λογαριασμό.
function isLoggedIn(): bool {

    // Επιστρέφουμε true μόνο αν υπάρχει έγκυρο ID χρήστη.
    return isset($_SESSION["user_id"]) &&
           filter_var($_SESSION["user_id"], FILTER_VALIDATE_INT) !== false &&
           (int) $_SESSION["user_id"] > 0;
}

// Δημιουργούμε ή επιστρέφουμε το μοναδικό token του επισκέπτη.
function getGuestToken(): string {

    // Δημιουργούμε token μόνο αν δεν υπάρχει ήδη.
    if (!isset($_SESSION["guest_token"])) {

        // Παράγουμε 32 τυχαία bytes και τα μετατρέπουμε σε 64 δεκαεξαδικούς χαρακτήρες.
        $_SESSION["guest_token"] = bin2hex(random_bytes(32));
    }

    // Επιστρέφουμε το token της συνεδρίας.
    return $_SESSION["guest_token"];
}

// Επιστρέφουμε τον κατάλληλο SQL περιορισμό για τον ιδιοκτήτη των δεδομένων.
function getOwnerCondition(): string {

    // Ελέγχουμε αν πρόκειται για εγγεγραμμένο χρήστη.
    if (isLoggedIn()) {

        // Επιτρέπουμε πρόσβαση μόνο στα δεδομένα του συγκεκριμένου λογαριασμού.
        return "user_id = :user_id AND guest_token IS NULL";
    }

    // Επιτρέπουμε πρόσβαση μόνο στα δεδομένα της συγκεκριμένης guest συνεδρίας.
    return "user_id IS NULL AND guest_token = :guest_token";
}

// Επιστρέφουμε τις παραμέτρους που χρειάζεται το SQL ερώτημα.
function getOwnerParameters(): array {

    // Αν ο επισκέπτης έχει λογαριασμό...
    if (isLoggedIn()) {

        // Επιστρέφουμε το ID του χρήστη.
        return [
            ":user_id" => (int) $_SESSION["user_id"]
        ];
    }

    // Διαφορετικά επιστρέφουμε το token του guest.
    return [
        ":guest_token" => getGuestToken()
    ];
}

// Επιστρέφουμε τις τιμές ιδιοκτησίας για νέες εγγραφές στη βάση.
function getOwnerInsertValues(): array {

    // Αν πρόκειται για εγγεγραμμένο χρήστη...
    if (isLoggedIn()) {

        // Αποθηκεύουμε το ID του και αφήνουμε το guest_token κενό.
        return [
            ":user_id" => (int) $_SESSION["user_id"],
            ":guest_token" => null
        ];
    }

    // Αν πρόκειται για επισκέπτη...
    return [
        ":user_id" => null,
        ":guest_token" => getGuestToken()
    ];
}

// Δημιουργούμε ή επιστρέφουμε το CSRF token της συνεδρίας.
function getCsrfToken(): string {

    // Ελέγχουμε αν υπάρχει ήδη token.
    if (!isset($_SESSION["csrf_token"])) {

        // Δημιουργούμε ένα νέο ασφαλές τυχαίο token.
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    // Επιστρέφουμε το token.
    return $_SESSION["csrf_token"];
}

// Ελέγχουμε αν το CSRF token που υποβλήθηκε είναι έγκυρο.
function verifyCsrfToken($submittedToken): bool {

    // Ελέγχουμε ότι το token είναι συμβολοσειρά.
    if (!is_string($submittedToken)) {
        return false;
    }

    // Ελέγχουμε ότι υπάρχει token αποθηκευμένο στη συνεδρία.
    if (!isset($_SESSION["csrf_token"])) {
        return false;
    }

    // Συγκρίνουμε με ασφαλή τρόπο τα δύο tokens.
    return hash_equals($_SESSION["csrf_token"], $submittedToken);
}

// Δημιουργούμε token για κάθε ανώνυμη συνεδρία.
if (!isLoggedIn()) {
    getGuestToken();
}
