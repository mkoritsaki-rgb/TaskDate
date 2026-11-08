
<?php

// Ορίζουμε τη διαδρομή του αρχείου σύνδεσης για το online περιβάλλον.
$productionConfig = __DIR__ . "/database.production.php";

// Ορίζουμε τη διαδρομή του αρχείου σύνδεσης για το τοπικό περιβάλλον.
$localConfig = __DIR__ . "/database.local.php";

// Ελέγχουμε αν υπάρχει το αρχείο ρυθμίσεων του online περιβάλλοντος.
if (is_file($productionConfig)) {

    // Φορτώνουμε τις ρυθμίσεις της online βάσης.
    $config = require $productionConfig;

// Διαφορετικά, ελέγχουμε αν υπάρχει το τοπικό αρχείο ρυθμίσεων.
} elseif (is_file($localConfig)) {

    // Φορτώνουμε τις ρυθμίσεις της τοπικής βάσης.
    $config = require $localConfig;

// Αν δεν υπάρχει κανένα αρχείο ρυθμίσεων, εμφανίζουμε οδηγίες.
} else {

    // Σταματάμε την εκτέλεση με κατανοητό μήνυμα.
    die(
        "Database configuration is missing. " .
        "Copy config/database.local.example.php to " .
        "config/database.local.php and enter your database settings."
    );
}

// Ορίζουμε τις επιλογές λειτουργίας του PDO.
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
];

// Προσπαθούμε να δημιουργήσουμε τη σύνδεση με τη βάση.
try {
    $pdo = new PDO(
        $config["dsn"],
        $config["username"],
        $config["password"],
        $options
    );
} catch (PDOException $error) {

    // Καταγράφουμε το πραγματικό σφάλμα μόνο στο αρχείο καταγραφής.
    error_log($error->getMessage());

    // Δεν αποκαλύπτουμε τεχνικές λεπτομέρειες στον επισκέπτη.
    die("Database connection failed.");
}
