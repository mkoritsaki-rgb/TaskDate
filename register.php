
<?php

// Φορτώνουμε πρώτα το σύστημα γλωσσών και συνεδριών.
require_once __DIR__ . "/includes/language.php";

// Συνδέουμε τη σελίδα με τη βάση δεδομένων.
require_once __DIR__ . "/config/database.php";

// Φορτώνουμε τις βοηθητικές λειτουργίες λογαριασμών.
require_once __DIR__ . "/includes/auth.php";

// Αν ο χρήστης είναι ήδη συνδεδεμένος, τον επιστρέφουμε στην αρχική.
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

// Αρχικοποιούμε το μήνυμα σφάλματος.
$errorMessage = "";

// Αρχικοποιούμε τις τιμές που θα διατηρήσουμε στη φόρμα.
$name = "";
$email = "";

// Ελέγχουμε αν υποβλήθηκε η φόρμα.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Διαβάζουμε και καθαρίζουμε το όνομα.
    $name = trim($_POST["name"] ?? "");

    // Διαβάζουμε και καθαρίζουμε το email.
    $email = strtolower(trim($_POST["email"] ?? ""));

    // Διαβάζουμε τον κωδικό πρόσβασης.
    $password = $_POST["password"] ?? "";

    // Διαβάζουμε την επιβεβαίωση του κωδικού.
    $confirmPassword = $_POST["confirm_password"] ?? "";

    // Ελέγχουμε πρώτα το CSRF token.
    if (!verifyCsrfToken($_POST["csrf_token"] ?? null)) {

        $errorMessage = t("auth_csrf_error");

    // Ελέγχουμε αν συμπληρώθηκε το όνομα.
    } elseif ($name === "") {

        $errorMessage = t("register_error_name");

    // Ελέγχουμε αν το όνομα είναι υπερβολικά μεγάλο.
    } elseif (mb_strlen($name, "UTF-8") > 100) {

        $errorMessage = t("register_error_name");

    // Ελέγχουμε αν το email έχει έγκυρη μορφή και μήκος.
    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 190
    ) {

        $errorMessage = t("register_error_email");

    // Ελέγχουμε αν ο κωδικός έχει τουλάχιστον 8 χαρακτήρες.
    } elseif (strlen($password) < 8) {

        $errorMessage = t("register_error_password");

    // Ελέγχουμε αν οι δύο κωδικοί είναι ίδιοι.
    } elseif ($password !== $confirmPassword) {

        $errorMessage = t("register_error_password_match");

    } else {

        // Παίρνουμε το token του επισκέπτη πριν από τη σύνδεσή του.
        $guestToken = $_SESSION["guest_token"] ?? null;

        // Δημιουργούμε ασφαλές hash του κωδικού.
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {

            // Ξεκινάμε συναλλαγή ώστε οι αλλαγές να γίνουν όλες μαζί.
            $pdo->beginTransaction();

            // Δημιουργούμε τον νέο λογαριασμό.
            $insertUser = $pdo->prepare(
                "INSERT INTO users (name, email, password_hash)
                 VALUES (:name, :email, :password_hash)"
            );

            // Εκτελούμε την εισαγωγή των στοιχείων.
            $insertUser->execute([
                ":name" => $name,
                ":email" => $email,
                ":password_hash" => $passwordHash
            ]);

            // Παίρνουμε το ID του νέου χρήστη.
            $newUserId = (int) $pdo->lastInsertId();

            // Ελέγχουμε αν ο επισκέπτης έχει υπάρχον token.
            if ($guestToken !== null) {

                // Μεταφέρουμε τις εργασίες του επισκέπτη στον λογαριασμό.
                $transferTasks = $pdo->prepare(
                    "UPDATE tasks
                     SET user_id = :user_id, guest_token = NULL
                     WHERE user_id IS NULL
                     AND guest_token = :guest_token"
                );

                // Εκτελούμε τη μεταφορά των εργασιών.
                $transferTasks->execute([
                    ":user_id" => $newUserId,
                    ":guest_token" => $guestToken
                ]);

                // Μεταφέρουμε τα ραντεβού του επισκέπτη στον λογαριασμό.
                $transferAppointments = $pdo->prepare(
                    "UPDATE appointments
                     SET user_id = :user_id, guest_token = NULL
                     WHERE user_id IS NULL
                     AND guest_token = :guest_token"
                );

                // Εκτελούμε τη μεταφορά των ραντεβού.
                $transferAppointments->execute([
                    ":user_id" => $newUserId,
                    ":guest_token" => $guestToken
                ]);
            }

            // Οριστικοποιούμε όλες τις αλλαγές στη βάση.
            $pdo->commit();

            // Αλλάζουμε το αναγνωριστικό συνεδρίας για ασφάλεια.
            session_regenerate_id(true);

            // Συνδέουμε αυτόματα τον νέο χρήστη.
            $_SESSION["user_id"] = $newUserId;

            // Αποθηκεύουμε το όνομα του χρήστη στη συνεδρία.
            $_SESSION["user_name"] = $name;

            // Αφαιρούμε το token επισκέπτη από τη συνεδρία.
            unset($_SESSION["guest_token"]);

            // Αφαιρούμε το παλιό CSRF token.
            unset($_SESSION["csrf_token"]);

            // Επιστρέφουμε τον χρήστη στην αρχική σελίδα.
            header("Location: index.php?registered=1");
            exit;

        } catch (PDOException $error) {

            // Αν υπάρχει ενεργή συναλλαγή, ακυρώνουμε τις αλλαγές.
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Καταγράφουμε το πραγματικό σφάλμα.
            error_log($error->getMessage());

            // Ελέγχουμε αν το email υπάρχει ήδη.
            if ($error->getCode() === "23000") {

                $errorMessage = t("register_error_email_exists");

            } else {

                // Εμφανίζουμε γενικό μήνυμα σε περίπτωση άλλου σφάλματος.
                $errorMessage = t("register_error_general");
            }

        } catch (Throwable $error) {

            // Ακυρώνουμε τυχόν ενεργή συναλλαγή.
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Καταγράφουμε το σφάλμα χωρίς να το εμφανίζουμε στον χρήστη.
            error_log($error->getMessage());

            // Εμφανίζουμε γενικό μήνυμα.
            $errorMessage = t("register_error_general");
        }
    }
}

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLanguage, ENT_QUOTES, "UTF-8") ?>">

<head>

    <!-- Ορίζουμε την κωδικοποίηση χαρακτήρων. -->
    <meta charset="UTF-8">

    <!-- Προσαρμόζουμε τη σελίδα σε κινητά και tablet. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Ορίζουμε τον τίτλο της σελίδας. -->
    <title><?= htmlspecialchars(t("register_title"), ENT_QUOTES, "UTF-8") ?> | TaskDate</title>

    <!-- Φορτώνουμε το κεντρικό CSS της εφαρμογής. -->
    <link rel="stylesheet" href="assets/css/style.css">

</head>

<body>

    <!-- Φορτώνουμε το κεντρικό μενού. -->
    <?php require_once __DIR__ . "/includes/navigation.php"; ?>

    <main class="page-container">

        <!-- Κεφαλίδα της σελίδας εγγραφής. -->
        <section class="page-heading">

            <span class="page-eyebrow">TASKDATE ACCOUNT</span>

            <h1>
                <?= htmlspecialchars(t("register_title"), ENT_QUOTES, "UTF-8") ?>
            </h1>

            <p class="page-intro">
                <?= htmlspecialchars(t("register_intro"), ENT_QUOTES, "UTF-8") ?>
            </p>

        </section>

        <!-- Κεντρική κάρτα εγγραφής. -->
        <section class="content-card account-panel">

            <!-- Εμφανίζουμε το μήνυμα σφάλματος, αν υπάρχει. -->
            <?php if ($errorMessage !== "") { ?>

                <div class="alert alert-error" role="alert">
                    <?= htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8") ?>
                </div>

            <?php } ?>

            <!-- Φόρμα δημιουργίας λογαριασμού. -->
            <form method="POST" action="register.php" class="account-form">

                <!-- Κρυφό CSRF token για προστασία της φόρμας. -->
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, "UTF-8") ?>"
                >

                <!-- Πεδίο ονόματος. -->
                <div class="form-field">

                    <label for="name">
                        <?= htmlspecialchars(t("register_name_label"), ENT_QUOTES, "UTF-8") ?>
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        maxlength="100"
                        autocomplete="name"
                        value="<?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?>"
                        required
                    >

                </div>

                <!-- Πεδίο email. -->
                <div class="form-field">

                    <label for="email">
                        <?= htmlspecialchars(t("register_email_label"), ENT_QUOTES, "UTF-8") ?>
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="190"
                        autocomplete="email"
                        value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
                        required
                    >

                </div>

                <!-- Πεδίο κωδικού πρόσβασης. -->
                <div class="form-field">

                    <label for="password">
                        <?= htmlspecialchars(t("register_password_label"), ENT_QUOTES, "UTF-8") ?>
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                </div>

                <!-- Πεδίο επιβεβαίωσης κωδικού. -->
                <div class="form-field">

                    <label for="confirm_password">
                        <?= htmlspecialchars(t("register_confirm_password_label"), ENT_QUOTES, "UTF-8") ?>
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                </div>

                <!-- Κουμπί υποβολής της εγγραφής. -->
                <button type="submit" class="button-primary">
                    <?= htmlspecialchars(t("register_button"), ENT_QUOTES, "UTF-8") ?>
                </button>

            </form>

            <!-- Σύνδεσμος προς τη σελίδα σύνδεσης. -->
            <p class="account-switch">
                <?= htmlspecialchars(t("register_have_account"), ENT_QUOTES, "UTF-8") ?>

                <a href="login.php">
                    <?= htmlspecialchars(t("register_login_link"), ENT_QUOTES, "UTF-8") ?>
                </a>
            </p>

        </section>

    </main>

</body>

</html>
