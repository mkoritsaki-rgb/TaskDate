
<?php

// Φορτώνουμε το σύστημα γλωσσών και συνεδριών.
require_once __DIR__ . "/../includes/language.php";

// Συνδέουμε τη σελίδα με τη βάση δεδομένων.
require_once __DIR__ . "/../config/database.php";

// Φορτώνουμε τις λειτουργίες ελέγχου ιδιοκτησίας και CSRF.
require_once __DIR__ . "/../includes/auth.php";

// Παίρνουμε το ID της εργασίας από τη διεύθυνση URL.
$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

// Ελέγχουμε αν το ID είναι έγκυρος θετικός αριθμός.
if ($id === false || $id === null || $id < 1) {
    exit(htmlspecialchars(t("task_error_invalid"), ENT_QUOTES, "UTF-8"));
}

// Αναζητούμε την εργασία μόνο αν ανήκει στον τρέχοντα ιδιοκτήτη.
$statement = $pdo->prepare(
    "SELECT * FROM tasks
     WHERE id = :id AND " . getOwnerCondition()
);

// Εκτελούμε την αναζήτηση με το ID και τα στοιχεία ιδιοκτησίας.
$statement->execute(
    array_merge(
        [":id" => $id],
        getOwnerParameters()
    )
);

// Παίρνουμε τα στοιχεία της εργασίας.
$task = $statement->fetch();

// Ελέγχουμε αν βρέθηκε εργασία που ανήκει στον τρέχοντα ιδιοκτήτη.
if (!$task) {

    // Ορίζουμε τον κωδικό απόκρισης 404.
    http_response_code(404);

    // Εμφανίζουμε μεταφρασμένο μήνυμα.
    exit(htmlspecialchars(t("task_edit_not_found"), ENT_QUOTES, "UTF-8"));
}

// Δημιουργούμε πίνακα με τα στοιχεία της φόρμας.
$formData = [
    "title" => $task["title"],
    "description" => $task["description"] ?? "",
    "due_date" => $task["due_date"] ?? "",
    "scheduled_date" => $task["scheduled_date"] ?? "",
    "start_time" => !empty($task["start_time"])
        ? substr($task["start_time"], 0, 5)
        : "",
    "end_time" => !empty($task["end_time"])
        ? substr($task["end_time"], 0, 5)
        : ""
];

// Δημιουργούμε μεταβλητή για το μήνυμα λάθους.
$errorMessage = "";

// Ελέγχουμε αν έχει υποβληθεί η φόρμα.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Ελέγχουμε πρώτα το CSRF token.
    if (!verifyCsrfToken($_POST["csrf_token"] ?? null)) {

        $errorMessage = t("auth_csrf_error");

    } else {

        // Παίρνουμε τον νέο τίτλο και αφαιρούμε τα περιττά κενά.
        $formData["title"] = trim($_POST["title"] ?? "");

        // Παίρνουμε τη νέα περιγραφή.
        $formData["description"] = trim($_POST["description"] ?? "");

        // Παίρνουμε τη νέα ημερομηνία προθεσμίας.
        $formData["due_date"] = trim($_POST["due_date"] ?? "");

        // Παίρνουμε την ημερομηνία προγραμματισμού.
        $formData["scheduled_date"] = trim($_POST["scheduled_date"] ?? "");

        // Παίρνουμε την ώρα έναρξης.
        $formData["start_time"] = trim($_POST["start_time"] ?? "");

        // Παίρνουμε την ώρα λήξης.
        $formData["end_time"] = trim($_POST["end_time"] ?? "");

        // Ελέγχουμε αν ο τίτλος είναι κενός ή υπερβολικά μεγάλος.
        if (
            $formData["title"] === "" ||
            mb_strlen($formData["title"], "UTF-8") > 255
        ) {

            $errorMessage = t("task_error_required_title");

        } else {

            // Αρχικοποιούμε τον έλεγχο της ημερομηνίας προθεσμίας.
            $validDueDate = true;

            // Ελέγχουμε αν έχει συμπληρωθεί ημερομηνία προθεσμίας.
            if ($formData["due_date"] !== "") {

                // Μετατρέπουμε την ημερομηνία σε αντικείμενο DateTime.
                $dateObject = DateTime::createFromFormat(
                    "!Y-m-d",
                    $formData["due_date"]
                );

                // Επιβεβαιώνουμε ότι η ημερομηνία έχει σωστή μορφή.
                $validDueDate = $dateObject &&
                    $dateObject->format("Y-m-d") === $formData["due_date"];
            }

            // Ελέγχουμε αν έχει συμπληρωθεί κάποιο από τα τρία πεδία προγραμματισμού.
            $hasScheduleData =
                $formData["scheduled_date"] !== "" ||
                $formData["start_time"] !== "" ||
                $formData["end_time"] !== "";

            // Ελέγχουμε αν έχουν συμπληρωθεί και τα τρία πεδία.
            $hasCompleteSchedule =
                $formData["scheduled_date"] !== "" &&
                $formData["start_time"] !== "" &&
                $formData["end_time"] !== "";

            // Αρχικοποιούμε τους ελέγχους προγραμματισμού.
            $validScheduleDate = true;
            $validStartTime = true;
            $validEndTime = true;

            // Ελέγχουμε την ημερομηνία προγραμματισμού, αν έχει συμπληρωθεί.
            if ($formData["scheduled_date"] !== "") {

                // Μετατρέπουμε την ημερομηνία σε αντικείμενο DateTime.
                $scheduleDateObject = DateTime::createFromFormat(
                    "!Y-m-d",
                    $formData["scheduled_date"]
                );

                // Επιβεβαιώνουμε τη σωστή μορφή της ημερομηνίας.
                $validScheduleDate = $scheduleDateObject &&
                    $scheduleDateObject->format("Y-m-d") === $formData["scheduled_date"];
            }

            // Ελέγχουμε τη μορφή της ώρας έναρξης.
            if ($formData["start_time"] !== "") {
                $validStartTime = preg_match(
                    '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                    $formData["start_time"]
                ) === 1;
            }

            // Ελέγχουμε τη μορφή της ώρας λήξης.
            if ($formData["end_time"] !== "") {
                $validEndTime = preg_match(
                    '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                    $formData["end_time"]
                ) === 1;
            }

            // Ελέγχουμε αν η ημερομηνία προθεσμίας είναι έγκυρη.
            if (!$validDueDate) {

                $errorMessage = t("task_edit_invalid_date");

            // Ελέγχουμε αν έχουν συμπληρωθεί μερικά μόνο πεδία προγραμματισμού.
            } elseif ($hasScheduleData && !$hasCompleteSchedule) {

                $errorMessage = "Για τον προγραμματισμό πρέπει να συμπληρώσεις και τα τρία πεδία ή να τα αφήσεις όλα κενά.";

            // Ελέγχουμε αν η ημερομηνία προγραμματισμού είναι έγκυρη.
            } elseif (!$validScheduleDate) {

                $errorMessage = "Η ημερομηνία προγραμματισμού δεν είναι έγκυρη.";

            // Ελέγχουμε αν οι ώρες έχουν σωστή μορφή.
            } elseif (!$validStartTime || !$validEndTime) {

                $errorMessage = "Η ώρα έναρξης ή λήξης δεν είναι έγκυρη.";

            // Ελέγχουμε αν η ώρα λήξης είναι μεταγενέστερη της έναρξης.
            } elseif (
                $hasCompleteSchedule &&
                $formData["end_time"] <= $formData["start_time"]
            ) {

                $errorMessage = "Η ώρα λήξης πρέπει να είναι μεταγενέστερη της ώρας έναρξης.";

            } else {

                // Δημιουργούμε SQL εντολή ενημέρωσης μόνο για την εργασία του ιδιοκτήτη.
                $update = $pdo->prepare(
                    "UPDATE tasks
                     SET title = :title,
                         description = :description,
                         due_date = :due_date,
                         scheduled_date = :scheduled_date,
                         start_time = :start_time,
                         end_time = :end_time
                     WHERE id = :id AND " . getOwnerCondition()
                );

                // Δημιουργούμε τις παραμέτρους της ενημέρωσης.
                $parameters = [
                    ":title" => $formData["title"],
                    ":description" => $formData["description"] !== ""
                        ? $formData["description"]
                        : null,
                    ":due_date" => $formData["due_date"] !== ""
                        ? $formData["due_date"]
                        : null,
                    ":scheduled_date" => $hasCompleteSchedule
                        ? $formData["scheduled_date"]
                        : null,
                    ":start_time" => $hasCompleteSchedule
                        ? $formData["start_time"]
                        : null,
                    ":end_time" => $hasCompleteSchedule
                        ? $formData["end_time"]
                        : null,
                    ":id" => $id
                ];

                // Προσθέτουμε τις παραμέτρους ιδιοκτησίας.
                $parameters = array_merge(
                    $parameters,
                    getOwnerParameters()
                );

                // Εκτελούμε την ασφαλή ενημέρωση.
                $update->execute($parameters);

                // Επιστρέφουμε στη λίστα με μήνυμα επιτυχίας.
                header("Location: index.php?updated=1");

                // Σταματάμε την εκτέλεση μετά την ανακατεύθυνση.
                exit;
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
    <!-- Προσαρμόζουμε τη σελίδα σε κινητά και υπολογιστές. -->

    <title>
        TaskDate - <?= htmlspecialchars(t("task_edit_page_title"), ENT_QUOTES, "UTF-8") ?>
    </title>
    <!-- Ορίζουμε τον μεταφρασμένο τίτλο της σελίδας. -->

    <link rel="stylesheet" href="/TaskDate/assets/css/style.css?v=6">
    <!-- Φορτώνουμε το κεντρικό CSS. -->

</head>

<body>

    <?php
    // Φορτώνουμε το κοινό μενού πλοήγησης.
    require_once __DIR__ . "/../includes/navigation.php";
    ?>

    <main class="page-container">

        <header class="page-heading">
            <!-- Κεφαλίδα της σελίδας. -->

            <p class="page-eyebrow">
                <?= htmlspecialchars(t("tasks_eyebrow"), ENT_QUOTES, "UTF-8") ?>
            </p>

            <h1>
                <?= htmlspecialchars(t("task_edit_page_title"), ENT_QUOTES, "UTF-8") ?>
            </h1>

            <p class="page-intro">
                <?= htmlspecialchars(t("task_edit_page_intro"), ENT_QUOTES, "UTF-8") ?>
            </p>

        </header>

        <?php if ($errorMessage !== "") { ?>

            <!-- Εμφανίζουμε μήνυμα λάθους. -->
            <div class="alert alert-error">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8") ?>
            </div>

        <?php } ?>

        <section class="edit-panel">
            <!-- Κεντρική κάρτα επεξεργασίας. -->

            <div class="section-heading">

                <div>

                    <h2>
                        <?= htmlspecialchars(t("task_edit_section_title"), ENT_QUOTES, "UTF-8") ?>
                    </h2>

                    <p>
                        <?= htmlspecialchars(t("task_edit_section_intro"), ENT_QUOTES, "UTF-8") ?>
                    </p>

                </div>

            </div>

            <form method="POST" action="" class="edit-form">
                <!-- Φόρμα επεξεργασίας εργασίας. -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, "UTF-8") ?>"
                >
                <!-- Προσθέτουμε το CSRF token για προστασία της φόρμας. -->

                <div class="form-field">

                    <label for="title">
                        <?= htmlspecialchars(t("task_title_label"), ENT_QUOTES, "UTF-8") ?>
                    </label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="255"
                        value="<?= htmlspecialchars($formData["title"], ENT_QUOTES, "UTF-8") ?>"
                        required
                    >
                    <!-- Εμφανίζουμε τον υπάρχοντα τίτλο. -->

                </div>

                <div class="form-field">

                    <label for="description">
                        <?= htmlspecialchars(t("task_description_label"), ENT_QUOTES, "UTF-8") ?>
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        placeholder="<?= htmlspecialchars(t("task_edit_description_placeholder"), ENT_QUOTES, "UTF-8") ?>"
                    ><?= htmlspecialchars($formData["description"], ENT_QUOTES, "UTF-8") ?></textarea>
                    <!-- Εμφανίζουμε την υπάρχουσα περιγραφή. -->

                </div>

                <div class="form-field">

                    <label for="due_date">
                        <?= htmlspecialchars(t("task_due_date_label"), ENT_QUOTES, "UTF-8") ?>
                    </label>

                    <input
                        type="date"
                        id="due_date"
                        name="due_date"
                        value="<?= htmlspecialchars($formData["due_date"], ENT_QUOTES, "UTF-8") ?>"
                    >
                    <!-- Εμφανίζουμε την υπάρχουσα ημερομηνία προθεσμίας. -->

                </div>

                <div class="task-schedule-section">
                    <!-- Ξεχωριστή ενότητα προγραμματισμού της εργασίας. -->

                    <div class="section-heading">

                        <h3>Προγραμματισμός εργασίας</h3>

                        <p>Προαιρετικά, όρισε πότε σκοπεύεις να ασχοληθείς με την εργασία.</p>

                    </div>

                    <div class="form-field">

                        <label for="scheduled_date">Ημερομηνία προγραμματισμού</label>

                        <input
                            type="date"
                            id="scheduled_date"
                            name="scheduled_date"
                            value="<?= htmlspecialchars($formData["scheduled_date"], ENT_QUOTES, "UTF-8") ?>"
                        >
                        <!-- Εμφανίζουμε την υπάρχουσα ημερομηνία προγραμματισμού. -->

                    </div>

                    <div class="form-field">

                        <label for="start_time">Ώρα έναρξης</label>

                        <input
                            type="time"
                            id="start_time"
                            name="start_time"
                            value="<?= htmlspecialchars($formData["start_time"], ENT_QUOTES, "UTF-8") ?>"
                        >
                        <!-- Εμφανίζουμε την υπάρχουσα ώρα έναρξης. -->

                    </div>

                    <div class="form-field">

                        <label for="end_time">Ώρα λήξης</label>

                        <input
                            type="time"
                            id="end_time"
                            name="end_time"
                            value="<?= htmlspecialchars($formData["end_time"], ENT_QUOTES, "UTF-8") ?>"
                        >
                        <!-- Εμφανίζουμε την υπάρχουσα ώρα λήξης. -->

                    </div>

                </div>

                <div class="edit-actions">

                    <button type="submit" class="button-primary">
                        <?= htmlspecialchars(t("task_edit_save_button"), ENT_QUOTES, "UTF-8") ?>
                    </button>
                    <!-- Κουμπί αποθήκευσης. -->

                    <a href="index.php" class="button-secondary">
                        <?= htmlspecialchars(t("task_edit_back_button"), ENT_QUOTES, "UTF-8") ?>
                    </a>
                    <!-- Σύνδεσμος επιστροφής. -->

                </div>

            </form>

        </section>

    </main>

</body>
</html>
