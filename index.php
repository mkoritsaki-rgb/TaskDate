

<?php

// Φορτώνουμε το σύστημα γλωσσών και τις μεταφράσεις.
require_once __DIR__ . "/includes/language.php";

// Συνδέουμε την αρχική σελίδα με τη βάση δεδομένων.
require_once __DIR__ . "/config/database.php";

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLanguage, ENT_QUOTES, "UTF-8") ?>">

<head>

    <meta charset="UTF-8">
    <!-- Ορίζουμε την κωδικοποίηση χαρακτήρων. -->

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Προσαρμόζουμε τη σελίδα σε κινητά και υπολογιστές. -->

    <title>TaskDate - <?= htmlspecialchars(t("home_subtitle"), ENT_QUOTES, "UTF-8") ?></title>
    <!-- Ορίζουμε τον τίτλο της καρτέλας στην επιλεγμένη γλώσσα. -->

    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Συνδέουμε την αρχική σελίδα με το κεντρικό CSS. -->

</head>

<body>

    <?php
    // Εμφανίζουμε το κοινό μενού πλοήγησης.
    require_once __DIR__ . "/includes/navigation.php";
    ?>

    <main class="home-container">
        <!-- Κεντρικό περιεχόμενο της αρχικής σελίδας. -->

        <header class="home-header">
            <!-- Κεφαλίδα της εφαρμογής. -->

            <p class="home-subtitle">
                <?= htmlspecialchars(t("home_subtitle"), ENT_QUOTES, "UTF-8") ?>
            </p>
            <!-- Εμφανίζουμε τον εισαγωγικό τίτλο στη σωστή γλώσσα. -->

            <h1>
                <?= htmlspecialchars(t("home_title"), ENT_QUOTES, "UTF-8") ?>
            </h1>
            <!-- Εμφανίζουμε το όνομα της εφαρμογής. -->

            <p>
                <?= htmlspecialchars(t("home_intro"), ENT_QUOTES, "UTF-8") ?>
            </p>
            <!-- Εμφανίζουμε την περιγραφή της εφαρμογής. -->

        </header>

        <section class="home-grid">
            <!-- Περιοχή επιλογής λειτουργιών. -->

            <a href="tasks/index.php" class="home-card">
                <!-- Σύνδεσμος προς τη διαχείριση εργασιών. -->

                <h2>
                    <?= htmlspecialchars(t("home_tasks_title"), ENT_QUOTES, "UTF-8") ?>
                </h2>

                <p>
                    <?= htmlspecialchars(t("home_tasks_description"), ENT_QUOTES, "UTF-8") ?>
                </p>

            </a>

            <a href="appointments/index.php" class="home-card">
                <!-- Σύνδεσμος προς τη διαχείριση ραντεβού. -->

                <h2>
                    <?= htmlspecialchars(t("home_appointments_title"), ENT_QUOTES, "UTF-8") ?>
                </h2>

                <p>
                    <?= htmlspecialchars(t("home_appointments_description"), ENT_QUOTES, "UTF-8") ?>
                </p>

            </a>

            <a href="calendar.php" class="home-card">
                <!-- Σύνδεσμος προς το εβδομαδιαίο ημερολόγιο. -->

                <h2>
                    <?= htmlspecialchars(t("home_calendar_title"), ENT_QUOTES, "UTF-8") ?>
                </h2>

                <p>
                    <?= htmlspecialchars(t("home_calendar_description"), ENT_QUOTES, "UTF-8") ?>
                </p>

            </a>

        </section>

        <footer class="home-footer">
            <!-- Υποσέλιδο της εφαρμογής. -->

            <p>
                <?= htmlspecialchars(t("footer_text"), ENT_QUOTES, "UTF-8") ?>
            </p>

        </footer>

    </main>

</body>
</html>
