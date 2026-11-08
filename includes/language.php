
<?php

// Φορτώνουμε την ασφαλή διαχείριση συνεδρίας.
require_once __DIR__ . "/session.php";

// Ορίζουμε τις διαθέσιμες γλώσσες.
$availableLanguages = [
    "el" => "Ελληνικά",
    "en" => "English",
    "es" => "Español"
];

// Ελέγχουμε αν ο χρήστης επέλεξε νέα γλώσσα.
if (isset($_GET["lang"])) {

    // Διαβάζουμε την επιλεγμένη γλώσσα.
    $selectedLanguage = $_GET["lang"];

    // Ελέγχουμε ότι η επιλογή είναι έγκυρη.
    if (
        is_string($selectedLanguage) &&
        array_key_exists($selectedLanguage, $availableLanguages)
    ) {
        // Αποθηκεύουμε τη γλώσσα στη συνεδρία.
        $_SESSION["language"] = $selectedLanguage;
    }
}

// Παίρνουμε την επιλεγμένη γλώσσα ή χρησιμοποιούμε τα ελληνικά.
$currentLanguage = $_SESSION["language"] ?? "el";

// Φορτώνουμε τα τρία αρχεία μεταφράσεων.
$translations = [
    "el" => require __DIR__ . "/../languages/el.php",
    "en" => require __DIR__ . "/../languages/en.php",
    "es" => require __DIR__ . "/../languages/es.php"
];

// Δημιουργούμε συνάρτηση αναζήτησης μετάφρασης.
function t($key) {

    // Κάνουμε διαθέσιμες τις μεταβλητές μεταφράσεων μέσα στη συνάρτηση.
    global $translations, $currentLanguage;

    // Επιστρέφουμε τη μετάφραση της επιλεγμένης γλώσσας.
    // Αν δεν υπάρχει, χρησιμοποιούμε την ελληνική.
    // Αν δεν υπάρχει ούτε εκεί, επιστρέφουμε το ίδιο το κλειδί.
    return $translations[$currentLanguage][$key]
        ?? $translations["el"][$key]
        ?? $key;
}
