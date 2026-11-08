
<?php

// Φορτώνουμε τη διαχείριση λογαριασμών και συνεδριών.
// Προϋπόθεση: έχει προηγηθεί η φόρτωση του language.php.
require_once __DIR__ . "/auth.php";

// Ορίζουμε τη βασική διαδρομή της εφαρμογής.
$baseUrl = "/TaskDate";

// Παίρνουμε τη διαδρομή της τρέχουσας σελίδας.
$currentPath = parse_url(
    $_SERVER["REQUEST_URI"] ?? "",
    PHP_URL_PATH
);

// Ορίζουμε ως προεπιλεγμένη σελίδα την αρχική.
$currentPage = "home";

// Ελέγχουμε αν ο χρήστης βρίσκεται στις εργασίες.
if (strpos($currentPath, "/tasks/") !== false) {

    $currentPage = "tasks";

// Ελέγχουμε αν βρίσκεται στα ραντεβού.
} elseif (strpos($currentPath, "/appointments/") !== false) {

    $currentPage = "appointments";

// Ελέγχουμε αν βρίσκεται στο ημερολόγιο.
} elseif (strpos($currentPath, "/calendar.php") !== false) {

    $currentPage = "calendar";

}

// Κρατάμε τις υπάρχουσες παραμέτρους της διεύθυνσης.
$queryParameters = $_GET;

?>

<nav class="main-navigation">
    <!-- Δημιουργούμε το κεντρικό μενού. -->

    <a href="<?= $baseUrl ?>/" class="nav-brand">
        <!-- Σύνδεσμος αρχικής σελίδας. -->
        TaskDate
    </a>

    <div class="nav-links">
        <!-- Περιοχή βασικών συνδέσμων πλοήγησης. -->

        <a href="<?= $baseUrl ?>/"
           class="<?= $currentPage === 'home' ? 'active' : '' ?>">
            <?= htmlspecialchars(t("nav_home"), ENT_QUOTES, "UTF-8") ?>
        </a>

        <a href="<?= $baseUrl ?>/tasks/"
           class="<?= $currentPage === 'tasks' ? 'active' : '' ?>">
            <?= htmlspecialchars(t("nav_tasks"), ENT_QUOTES, "UTF-8") ?>
        </a>

        <a href="<?= $baseUrl ?>/appointments/"
           class="<?= $currentPage === 'appointments' ? 'active' : '' ?>">
            <?= htmlspecialchars(t("nav_appointments"), ENT_QUOTES, "UTF-8") ?>
        </a>

        <a href="<?= $baseUrl ?>/calendar.php"
           class="<?= $currentPage === 'calendar' ? 'active' : '' ?>">
            <?= htmlspecialchars(t("nav_calendar"), ENT_QUOTES, "UTF-8") ?>
        </a>

    </div>

    <div class="nav-account">
        <!-- Περιοχή σύνδεσης και αποσύνδεσης. -->

        <?php if (isLoggedIn()) { ?>

            <!-- Εμφανίζουμε το όνομα του συνδεδεμένου χρήστη. -->
            <span class="nav-user-name">
                <?= htmlspecialchars(
                    $_SESSION["user_name"] ?? "",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </span>

            <!-- Η αποσύνδεση γίνεται με POST και έλεγχο CSRF. -->
            <form
                action="<?= $baseUrl ?>/logout.php"
                method="POST"
                class="nav-logout-form"
            >

                <!-- Στέλνουμε το CSRF token για έλεγχο ασφαλείας. -->
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        getCsrfToken(),
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >

                <!-- Κουμπί αποσύνδεσης. -->
                <button type="submit" class="nav-logout-button">
                    <?= htmlspecialchars(t("nav_logout"), ENT_QUOTES, "UTF-8") ?>
                </button>

            </form>

        <?php } else { ?>

            <!-- Σύνδεσμος εγγραφής για ανώνυμους επισκέπτες. -->
            <a href="<?= $baseUrl ?>/register.php">
                <?= htmlspecialchars(t("nav_register"), ENT_QUOTES, "UTF-8") ?>
            </a>

            <!-- Σύνδεσμος σύνδεσης για ανώνυμους επισκέπτες. -->
            <a href="<?= $baseUrl ?>/login.php">
                <?= htmlspecialchars(t("nav_login"), ENT_QUOTES, "UTF-8") ?>
            </a>

        <?php } ?>

    </div>

    <div class="nav-language">
        <!-- Περιοχή επιλογής γλώσσας. -->

        <span>
            <?= htmlspecialchars(t("language_label"), ENT_QUOTES, "UTF-8") ?>:
        </span>

        <?php foreach ($availableLanguages as $code => $label) { ?>

            <?php

            // Διατηρούμε τις παραμέτρους της τρέχουσας σελίδας.
            $languageParameters = $queryParameters;

            // Αλλάζουμε μόνο τη γλώσσα.
            $languageParameters["lang"] = $code;

            // Δημιουργούμε τη νέα διεύθυνση.
            $languageUrl = $_SERVER["PHP_SELF"] . "?" .
                http_build_query($languageParameters);

            ?>

            <a
                href="<?= htmlspecialchars($languageUrl, ENT_QUOTES, "UTF-8") ?>"
                class="<?= $currentLanguage === $code ? 'active-language' : '' ?>"
            >
                <?= htmlspecialchars($label, ENT_QUOTES, "UTF-8") ?>
            </a>

        <?php } ?>

    </div>

</nav>
