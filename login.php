
<?php

// Φορτώνουμε το σύστημα γλωσσών και ξεκινάμε τη συνεδρία.
require_once __DIR__ . "/includes/language.php";

// Συνδέουμε τη σελίδα με τη βάση δεδομένων.
require_once __DIR__ . "/config/database.php";

// Φορτώνουμε τις λειτουργίες αυθεντικοποίησης.
require_once __DIR__ . "/includes/auth.php";

// Αρχικοποιούμε το μήνυμα λάθους.
$errorMessage = "";

// Αρχικοποιούμε το μήνυμα επιτυχίας.
$successMessage = "";

// Ελέγχουμε αν ο χρήστης είναι ήδη συνδεδεμένος.
if (isLoggedIn()) {

    // Εμφανίζουμε μήνυμα επιτυχίας μετά την επιτυχημένη σύνδεση.
    if (isset($_GET["success"])) {
        $successMessage = t("login_success");

    } else {

        // Αν είναι ήδη συνδεδεμένος, τον οδηγούμε στις εργασίες του.
        header("Location: tasks/index.php");
        exit;
    }
}

// Ελέγχουμε αν έχει υποβληθεί η φόρμα σύνδεσης.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Ελέγχουμε αν το CSRF token είναι έγκυρο.
    if (!verifyCsrfToken($_POST["csrf_token"] ?? null)) {

        // Εμφανίζουμε μήνυμα λάθους για μη έγκυρο token.
        $errorMessage = t("auth_csrf_error");

    } else {

        // Παίρνουμε το email και αφαιρούμε τα περιττά κενά.
        $email = trim($_POST["email"] ?? "");

        // Παίρνουμε τον κωδικό πρόσβασης.
        $password = $_POST["password"] ?? "";

        // Ελέγχουμε αν το email έχει έγκυρη μορφή.
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password)) {

            // Χρησιμοποιούμε γενικό μήνυμα για αποτυχημένη σύνδεση.
            $errorMessage = t("login_error_credentials");

        } else {

            try {

                // Αναζητούμε τον λογαριασμό με βάση το email.
                $statement = $pdo->prepare(
                    "SELECT id, name, email, password_hash
                     FROM users
                     WHERE email = :email
                     LIMIT 1"
                );

                // Εκτελούμε την αναζήτηση.
                $statement->execute([
                    ":email" => $email
                ]);

                // Παίρνουμε τα στοιχεία του λογαριασμού.
                $user = $statement->fetch();

                // Ελέγχουμε αν υπάρχει ο χρήστης και αν ο κωδικός είναι σωστός.
                if ($user && password_verify($password, $user["password_hash"])) {

                    // Ανανεώνουμε το αναγνωριστικό της συνεδρίας για ασφάλεια.
                    session_regenerate_id(true);

                    // Αποθηκεύουμε το ID του συνδεδεμένου χρήστη.
                    $_SESSION["user_id"] = (int) $user["id"];

                    // Αποθηκεύουμε το όνομα του χρήστη.
                    $_SESSION["user_name"] = $user["name"];

                    // Διατηρούμε το guest token χωρίς να μεταφέρουμε τα δεδομένα επισκέπτη.
                    // Έτσι θα είναι ξανά διαθέσιμα όταν γίνει αποσύνδεση.

                    // Δημιουργούμε νέο CSRF token για τη νέα κατάσταση σύνδεσης.
                    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                    // Οδηγούμε τον χρήστη στη σελίδα σύνδεσης με μήνυμα επιτυχίας.
                    header("Location: login.php?success=1");
                    exit;

                } else {

                    // Δεν αποκαλύπτουμε αν δεν υπάρχει email ή αν είναι λάθος ο κωδικός.
                    $errorMessage = t("login_error_credentials");
                }

            } catch (PDOException $error) {

                // Καταγράφουμε το τεχνικό σφάλμα στο αρχείο καταγραφής.
                error_log($error->getMessage());

                // Εμφανίζουμε γενικό μήνυμα λάθους.
                $errorMessage = t("register_error_general");
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLanguage, ENT_QUOTES, "UTF-8") ?>">

<head>

    <meta charset="UTF-8">
    <!-- Ορίζουμε την κωδικοποίηση χαρακτήρων. -->

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Προσαρμόζουμε τη σελίδα σε κινητά. -->

    <title>TaskDate - <?= htmlspecialchars(t("login_title"), ENT_QUOTES, "UTF-8") ?></title>
    <!-- Ορίζουμε τον τίτλο της σελίδας. -->

    <link rel="stylesheet" href="/TaskDate/assets/css/style.css?v=6">
    <!-- Φορτώνουμε το κεντρικό CSS. -->

</head>

<body>

    <?php
    // Φορτώνουμε το κοινό μενού πλοήγησης.
    require_once __DIR__ . "/includes/navigation.php";
    ?>

    <main class="page-container">

        <section class="account-panel">
            <!-- Κεντρική περιοχή της σελίδας σύνδεσης. -->

            <header class="page-heading">

                <h1>
                    <?= htmlspecialchars(t("login_title"), ENT_QUOTES, "UTF-8") ?>
                </h1>

                <p class="page-intro">
                    <?= htmlspecialchars(t("login_intro"), ENT_QUOTES, "UTF-8") ?>
                </p>

            </header>

            <?php if ($successMessage !== "") { ?>

                <!-- Εμφανίζουμε μήνυμα επιτυχούς σύνδεσης. -->
                <div class="alert alert-success">
                    <?= htmlspecialchars($successMessage, ENT_QUOTES, "UTF-8") ?>
                </div>

                <p class="account-switch">
                    <a href="/TaskDate/tasks/index.php">
                        <?= htmlspecialchars(t("nav_tasks"), ENT_QUOTES, "UTF-8") ?>
                    </a>
                </p>

            <?php } else { ?>

                <?php if ($errorMessage !== "") { ?>

                    <!-- Εμφανίζουμε μήνυμα λάθους. -->
                    <div class="alert alert-error">
                        <?= htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8") ?>
                    </div>

                <?php } ?>

                <form method="POST" action="" class="account-form">
                    <!-- Φόρμα σύνδεσης στον λογαριασμό. -->

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, "UTF-8") ?>"
                    >
                    <!-- Στέλνουμε το CSRF token για προστασία της φόρμας. -->

                    <div class="form-field">

                        <label for="email">
                            <?= htmlspecialchars(t("login_email_label"), ENT_QUOTES, "UTF-8") ?>
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="190"
                            autocomplete="email"
                            required
                        >
                        <!-- Πεδίο email. -->

                    </div>

                    <div class="form-field">

                        <label for="password">
                            <?= htmlspecialchars(t("login_password_label"), ENT_QUOTES, "UTF-8") ?>
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                        >
                        <!-- Πεδίο κωδικού πρόσβασης. -->

                    </div>

                    <button type="submit" class="button-primary">
                        <?= htmlspecialchars(t("login_button"), ENT_QUOTES, "UTF-8") ?>
                    </button>
                    <!-- Κουμπί σύνδεσης. -->

                </form>

                <div class="account-switch">

                    <p>
                        <?= htmlspecialchars(t("login_no_account"), ENT_QUOTES, "UTF-8") ?>
                    </p>

                    <a href="/TaskDate/register.php">
                        <?= htmlspecialchars(t("login_register_link"), ENT_QUOTES, "UTF-8") ?>
                    </a>

                </div>

            <?php } ?>

        </section>

    </main>

</body>
</html>
