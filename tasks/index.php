
<?php

// Φορτώνουμε το σύστημα γλωσσών.
require_once __DIR__ . "/../includes/language.php";

// Συνδέουμε τη σελίδα με τη βάση δεδομένων.
require_once __DIR__ . "/../config/database.php";

// Φορτώνουμε τις λειτουργίες σύνδεσης και διαχείρισης ιδιοκτησίας.
require_once __DIR__ . "/../includes/auth.php";

// Δημιουργούμε μεταβλητές για τα μηνύματα.
$errorMessage = "";
$successMessage = "";

// Αρχικοποιούμε τις τιμές της φόρμας για να διατηρούνται αν υπάρξει σφάλμα.
$formValues = [
    "title" => "",
    "description" => "",
    "due_date" => "",
    "scheduled_date" => "",
    "start_time" => "",
    "end_time" => ""
];

// Δημιουργούμε τις μεταφράσεις της νέας ενότητας προγραμματισμού.
$scheduleLabels = [
    "el" => [
        "title" => "Προγραμματισμός εργασίας",
        "intro" => "Προαιρετικά, όρισε πότε σκοπεύεις να εργαστείς.",
        "date" => "Ημερομηνία προγραμματισμού",
        "start" => "Ώρα έναρξης",
        "end" => "Ώρα λήξης",
         "date_time" => "Προγραμματισμός",
        "error_incomplete" => "Για τον προγραμματισμό πρέπει να συμπληρώσεις και τα τρία πεδία ή να τα αφήσεις όλα κενά.",
        "error_date" => "Η ημερομηνία προγραμματισμού δεν είναι έγκυρη.",
        "error_time" => "Οι ώρες πρέπει να είναι έγκυρες και η ώρα λήξης μεταγενέστερη της έναρξης."
    ],
    "en" => [
        "title" => "Task scheduling",
        "intro" => "Optionally, choose when you plan to work on this task.",
        "date" => "Scheduled date",
        "start" => "Start time",
        "end" => "End time",
        "date_time" => "Schedule",
        "error_incomplete" => "Please complete all three scheduling fields or leave all three empty.",
        "error_date" => "The scheduled date is invalid.",
        "error_time" => "The times must be valid and the end time must be later than the start time."
    ],
    "es" => [
        "title" => "Programación de tareas",
        "intro" => "Opcionalmente, elige cuándo tienes previsto trabajar en esta tarea.",
        "date" => "Fecha programada",
        "start" => "Hora de inicio",
        "end" => "Hora de finalización",
         "date_time" => "Programación",
        "error_incomplete" => "Completa los tres campos de programación o deja los tres vacíos.",
        "error_date" => "La fecha programada no es válida.",
        "error_time" => "Las horas deben ser válidas y la hora de finalización debe ser posterior a la de inicio."
    ]
];

// Επιλέγουμε τις μεταφράσεις της τρέχουσας γλώσσας.
$scheduleText = $scheduleLabels[$currentLanguage] ?? $scheduleLabels["el"];

// Ελέγχουμε αν έχει υποβληθεί κάποια φόρμα.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Ελέγχουμε πρώτα αν το CSRF token είναι έγκυρο.
    if (!verifyCsrfToken($_POST["csrf_token"] ?? null)) {

        // Εμφανίζουμε μήνυμα λάθους αν το token δεν είναι έγκυρο.
        $errorMessage = t("auth_csrf_error");

    } else {

        // Παίρνουμε την ενέργεια που ζητά ο χρήστης.
        $action = $_POST["action"] ?? "";

        // Ελέγχουμε αν θέλει να προσθέσει εργασία.
        if ($action === "add") {

            // Παίρνουμε τον τίτλο και αφαιρούμε τα περιττά κενά.
            $title = trim($_POST["title"] ?? "");

            // Παίρνουμε την περιγραφή.
            $description = trim($_POST["description"] ?? "");

            // Παίρνουμε την ημερομηνία προθεσμίας.
            $dueDate = $_POST["due_date"] ?? "";

            // Παίρνουμε τα νέα πεδία προγραμματισμού.
            $scheduledDate = $_POST["scheduled_date"] ?? "";
            $startTime = $_POST["start_time"] ?? "";
            $endTime = $_POST["end_time"] ?? "";

            // Διατηρούμε τις τιμές στη φόρμα σε περίπτωση σφάλματος.
            $formValues = [
                "title" => $title,
                "description" => $description,
                "due_date" => $dueDate,
                "scheduled_date" => $scheduledDate,
                "start_time" => $startTime,
                "end_time" => $endTime
            ];

            // Ελέγχουμε αν ο τίτλος είναι κενός.
            if ($title === "") {

                // Εμφανίζουμε μεταφρασμένο μήνυμα λάθους.
                $errorMessage = t("task_error_required_title");

            // Ελέγχουμε αν τα τρία πεδία προγραμματισμού είναι συμπληρωμένα μαζί.
            } elseif (
                (
                    $scheduledDate === "" ||
                    $startTime === "" ||
                    $endTime === ""
                ) &&
                (
                    $scheduledDate !== "" ||
                    $startTime !== "" ||
                    $endTime !== ""
                )
            ) {

                // Απαιτούμε είτε και τα τρία πεδία είτε κανένα.
                $errorMessage = $scheduleText["error_incomplete"];

            } else {

                // Αρχικοποιούμε τις τιμές προγραμματισμού ως NULL.
                $validScheduledDate = null;
                $validStartTime = null;
                $validEndTime = null;

                // Ελέγχουμε την ημερομηνία μόνο αν έχει συμπληρωθεί.
                if ($scheduledDate !== "") {

                    // Μετατρέπουμε την ημερομηνία σε αντικείμενο DateTime.
                    $scheduledDateObject = DateTime::createFromFormat(
                        "!Y-m-d",
                        $scheduledDate
                    );

                    // Ελέγχουμε αν η ημερομηνία είναι πραγματικά έγκυρη.
                    if (
                        !$scheduledDateObject ||
                        $scheduledDateObject->format("Y-m-d") !== $scheduledDate
                    ) {

                        // Εμφανίζουμε μήνυμα για μη έγκυρη ημερομηνία.
                        $errorMessage = $scheduleText["error_date"];

                    } else {

                        // Διατηρούμε την έγκυρη ημερομηνία.
                        $validScheduledDate = $scheduledDate;
                    }

                    // Ελέγχουμε τις ώρες μόνο αν δεν υπάρχει προηγούμενο σφάλμα.
                    if ($errorMessage === "") {

                        // Μετατρέπουμε την ώρα έναρξης σε αντικείμενο DateTime.
                        $startTimeObject = DateTime::createFromFormat(
                            "!H:i",
                            $startTime
                        );

                        // Μετατρέπουμε την ώρα λήξης σε αντικείμενο DateTime.
                        $endTimeObject = DateTime::createFromFormat(
                            "!H:i",
                            $endTime
                        );

                        // Ελέγχουμε την εγκυρότητα και τη χρονική σειρά των ωρών.
                        if (
                            !$startTimeObject ||
                            !$endTimeObject ||
                            $startTimeObject->format("H:i") !== $startTime ||
                            $endTimeObject->format("H:i") !== $endTime ||
                            $endTime <= $startTime
                        ) {

                            // Εμφανίζουμε μήνυμα για μη έγκυρες ώρες.
                            $errorMessage = $scheduleText["error_time"];

                        } else {

                            // Διατηρούμε τις έγκυρες ώρες.
                            $validStartTime = $startTime;
                            $validEndTime = $endTime;
                        }
                    }
                }

                // Συνεχίζουμε μόνο αν δεν υπάρχει σφάλμα.
                if ($errorMessage === "") {

                    // Παίρνουμε τα στοιχεία ιδιοκτησίας του χρήστη ή επισκέπτη.
                    $ownerValues = getOwnerInsertValues();

                    // Δημιουργούμε SQL εντολή για την προσθήκη.
                    $statement = $pdo->prepare(
                        "INSERT INTO tasks
                        (
                            title,
                            description,
                            due_date,
                            scheduled_date,
                            start_time,
                            end_time,
                            user_id,
                            guest_token
                        )
                        VALUES
                        (
                            :title,
                            :description,
                            :due_date,
                            :scheduled_date,
                            :start_time,
                            :end_time,
                            :user_id,
                            :guest_token
                        )"
                    );

                    // Εκτελούμε την εισαγωγή μαζί με τα στοιχεία ιδιοκτησίας.
                    $statement->execute(array_merge([
                        ":title" => $title,
                        ":description" => $description !== "" ? $description : null,
                        ":due_date" => $dueDate !== "" ? $dueDate : null,
                        ":scheduled_date" => $validScheduledDate,
                        ":start_time" => $validStartTime,
                        ":end_time" => $validEndTime
                    ], $ownerValues));

                    // Επιστρέφουμε στη λίστα με μήνυμα επιτυχίας.
                    header("Location: index.php?added=1");
                    exit;
                }
            }

        // Ελέγχουμε αν θέλει να αλλάξει την κατάσταση εργασίας.
        } elseif ($action === "complete") {

            // Ελέγχουμε αν το ID είναι έγκυρος ακέραιος.
            $taskId = filter_var(
                $_POST["task_id"] ?? null,
                FILTER_VALIDATE_INT
            );

            // Ελέγχουμε αν το ID είναι θετικό.
            if ($taskId === false || $taskId === null || $taskId < 1) {

                // Εμφανίζουμε μεταφρασμένο μήνυμα λάθους.
                $errorMessage = t("task_error_invalid");

            } else {

                // Παίρνουμε τα στοιχεία ιδιοκτησίας του χρήστη ή επισκέπτη.
                $ownerParameters = getOwnerParameters();

                // Αλλάζουμε την κατάσταση μόνο στη δική του εργασία.
                $statement = $pdo->prepare(
                    "UPDATE tasks
                     SET status = CASE
                         WHEN status = 'completed' THEN 'pending'
                         ELSE 'completed'
                     END
                     WHERE id = :id AND " . getOwnerCondition()
                );

                // Εκτελούμε την ενημέρωση με έλεγχο ιδιοκτησίας.
                $statement->execute(array_merge([
                    ":id" => $taskId
                ], $ownerParameters));

                // Επιστρέφουμε με μήνυμα επιτυχίας.
                header("Location: index.php?status_updated=1");
                exit;
            }

        // Ελέγχουμε αν θέλει να διαγράψει εργασία.
        } elseif ($action === "delete") {

            // Παίρνουμε το ID της εργασίας.
            $taskId = filter_var(
                $_POST["task_id"] ?? null,
                FILTER_VALIDATE_INT
            );

            // Ελέγχουμε αν το ID είναι έγκυρο.
            if ($taskId === false || $taskId === null || $taskId < 1) {

                // Εμφανίζουμε μεταφρασμένο μήνυμα λάθους.
                $errorMessage = t("task_error_invalid");

            } else {

                // Παίρνουμε τα στοιχεία ιδιοκτησίας του χρήστη ή επισκέπτη.
                $ownerParameters = getOwnerParameters();

                // Δημιουργούμε SQL εντολή διαγραφής με έλεγχο ιδιοκτησίας.
                $statement = $pdo->prepare(
                    "DELETE FROM tasks
                     WHERE id = :id AND " . getOwnerCondition()
                );

                // Εκτελούμε τη διαγραφή.
                $statement->execute(array_merge([
                    ":id" => $taskId
                ], $ownerParameters));

                // Επιστρέφουμε με μήνυμα επιτυχίας.
                header("Location: index.php?deleted=1");
                exit;
            }
        }
    }
}

// Ελέγχουμε αν προστέθηκε νέα εργασία.
if (isset($_GET["added"])) {
    $successMessage = t("task_success_added");
}

// Ελέγχουμε αν επεξεργάστηκε εργασία.
if (isset($_GET["updated"])) {
    $successMessage = t("task_success_updated");
}

// Ελέγχουμε αν άλλαξε η κατάσταση εργασίας.
if (isset($_GET["status_updated"])) {
    $successMessage = t("task_success_status");
}

// Ελέγχουμε αν διαγράφηκε εργασία.
if (isset($_GET["deleted"])) {
    $successMessage = t("task_success_deleted");
}

// Ανακτούμε μόνο τις εργασίες του συγκεκριμένου χρήστη ή επισκέπτη.
$statement = $pdo->prepare(
    "SELECT * FROM tasks
     WHERE " . getOwnerCondition() . "
     ORDER BY created_at DESC"
);

// Εκτελούμε το ερώτημα με τα στοιχεία ιδιοκτησίας.
$statement->execute(getOwnerParameters());

// Αποθηκεύουμε τις εργασίες σε πίνακα.
$tasks = $statement->fetchAll();

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLanguage, ENT_QUOTES, "UTF-8") ?>">

<head>

    <meta charset="UTF-8">
    <!-- Ορίζουμε την κωδικοποίηση χαρακτήρων. -->

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Προσαρμόζουμε τη σελίδα σε κινητά. -->

    <title>TaskDate - <?= htmlspecialchars(t("tasks_page_title"), ENT_QUOTES, "UTF-8") ?></title>
    <!-- Ορίζουμε τον τίτλο της σελίδας στη σωστή γλώσσα. -->

    <link rel="stylesheet" href="/TaskDate/assets/css/style.css?v=4">
    <!-- Φορτώνουμε το κεντρικό CSS με νέα έκδοση. -->

</head>

<body>

    <?php
    // Φορτώνουμε το κοινό μενού πλοήγησης.
    require_once __DIR__ . "/../includes/navigation.php";
    ?>

    <main class="page-container tasks-page">
        <!-- Κεντρικό περιεχόμενο εργασιών. -->

        <header class="page-heading">
            <!-- Κεφαλίδα της ενότητας. -->

            <p class="page-eyebrow">
                <?= htmlspecialchars(t("tasks_eyebrow"), ENT_QUOTES, "UTF-8") ?>
            </p>

            <h1>
                <?= htmlspecialchars(t("tasks_page_title"), ENT_QUOTES, "UTF-8") ?>
            </h1>

            <p class="page-intro">
                <?= htmlspecialchars(t("tasks_page_intro"), ENT_QUOTES, "UTF-8") ?>
            </p>

        </header>

        <?php if ($successMessage !== "") { ?>

            <!-- Εμφανίζουμε μήνυμα επιτυχίας. -->
            <div class="alert alert-success">
                <?= htmlspecialchars($successMessage, ENT_QUOTES, "UTF-8") ?>
            </div>

        <?php } ?>

        <?php if ($errorMessage !== "") { ?>

            <!-- Εμφανίζουμε μήνυμα λάθους. -->
            <div class="alert alert-error">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8") ?>
            </div>

        <?php } ?>

        <section class="task-create-panel">
            <!-- Περιοχή προσθήκης νέας εργασίας. -->

            <div class="section-heading">

                <h2>
                    <?= htmlspecialchars(t("task_new_title"), ENT_QUOTES, "UTF-8") ?>
                </h2>

                <p>
                    <?= htmlspecialchars(t("task_new_intro"), ENT_QUOTES, "UTF-8") ?>
                </p>

            </div>

            <form method="POST" action="" class="task-form">
                <!-- Φόρμα προσθήκης εργασίας. -->

                <input type="hidden" name="action" value="add">
                <!-- Δηλώνουμε ότι πρόκειται για νέα εργασία. -->

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, "UTF-8") ?>">
                <!-- Στέλνουμε το CSRF token για την προστασία της φόρμας. -->

                <div class="form-field">

                    <label for="title">
                        <?= htmlspecialchars(t("task_title_label"), ENT_QUOTES, "UTF-8") ?>
                    </label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="255"
                        value="<?= htmlspecialchars($formValues["title"], ENT_QUOTES, "UTF-8") ?>"
                        placeholder="<?= htmlspecialchars(t("task_title_placeholder"), ENT_QUOTES, "UTF-8") ?>"
                        required
                    >
                    <!-- Πεδίο τίτλου. -->

                </div>

                <div class="form-field">

                    <label for="description">
                        <?= htmlspecialchars(t("task_description_label"), ENT_QUOTES, "UTF-8") ?>
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        placeholder="<?= htmlspecialchars(t("task_description_placeholder"), ENT_QUOTES, "UTF-8") ?>"
                    ><?= htmlspecialchars($formValues["description"], ENT_QUOTES, "UTF-8") ?></textarea>
                    <!-- Πεδίο περιγραφής. -->

                </div>

                <div class="form-field">

                    <label for="due_date">
                        <?= htmlspecialchars(t("task_due_date_label"), ENT_QUOTES, "UTF-8") ?>
                    </label>

                    <input
                        type="date"
                        id="due_date"
                        name="due_date"
                        value="<?= htmlspecialchars($formValues["due_date"], ENT_QUOTES, "UTF-8") ?>"
                    >
                    <!-- Πεδίο επιλογής ημερομηνίας προθεσμίας. -->

                </div>

                <fieldset class="task-schedule-fields">

                    <legend>
                        <?= htmlspecialchars($scheduleText["title"], ENT_QUOTES, "UTF-8") ?>
                    </legend>

                    <p>
                        <?= htmlspecialchars($scheduleText["intro"], ENT_QUOTES, "UTF-8") ?>
                    </p>

                    <div class="form-field">

                        <label for="scheduled_date">
                            <?= htmlspecialchars($scheduleText["date"], ENT_QUOTES, "UTF-8") ?>
                        </label>

                        <input
                            type="date"
                            id="scheduled_date"
                            name="scheduled_date"
                            value="<?= htmlspecialchars($formValues["scheduled_date"], ENT_QUOTES, "UTF-8") ?>"
                        >
                        <!-- Πεδίο ημερομηνίας προγραμματισμού. -->

                    </div>

                    <div class="form-field">

                        <label for="start_time">
                            <?= htmlspecialchars($scheduleText["start"], ENT_QUOTES, "UTF-8") ?>
                        </label>

                        <input
                            type="time"
                            id="start_time"
                            name="start_time"
                            value="<?= htmlspecialchars($formValues["start_time"], ENT_QUOTES, "UTF-8") ?>"
                        >
                        <!-- Πεδίο ώρας έναρξης. -->

                    </div>

                    <div class="form-field">

                        <label for="end_time">
                            <?= htmlspecialchars($scheduleText["end"], ENT_QUOTES, "UTF-8") ?>
                        </label>

                        <input
                            type="time"
                            id="end_time"
                            name="end_time"
                            value="<?= htmlspecialchars($formValues["end_time"], ENT_QUOTES, "UTF-8") ?>"
                        >
                        <!-- Πεδίο ώρας λήξης. -->

                    </div>

                </fieldset>

                <button type="submit" class="button-primary">
                    + <?= htmlspecialchars(t("task_add_button"), ENT_QUOTES, "UTF-8") ?>
                </button>
                <!-- Κουμπί αποθήκευσης. -->

            </form>

        </section>

        <section class="tasks-section">
            <!-- Περιοχή εμφάνισης καταχωρημένων εργασιών. -->

            <div class="section-heading">

                <div>

                    <h2>
                        <?= htmlspecialchars(t("task_list_title"), ENT_QUOTES, "UTF-8") ?>
                    </h2>

                    <p>
                        <?= htmlspecialchars(t("task_list_intro"), ENT_QUOTES, "UTF-8") ?>
                    </p>

                </div>

                <span class="task-counter">
                    <?= count($tasks) ?>
                    <?= htmlspecialchars(t("task_count_label"), ENT_QUOTES, "UTF-8") ?>
                </span>
                <!-- Εμφανίζουμε τον συνολικό αριθμό εργασιών. -->

            </div>

            <?php if (empty($tasks)) { ?>

                <!-- Εμφανίζουμε μήνυμα όταν δεν υπάρχουν εργασίες. -->
                <div class="empty-state">

                    <h3>
                        <?= htmlspecialchars(t("task_empty_title"), ENT_QUOTES, "UTF-8") ?>
                    </h3>

                    <p>
                        <?= htmlspecialchars(t("task_empty_description"), ENT_QUOTES, "UTF-8") ?>
                    </p>

                </div>

            <?php } else { ?>

                <div class="task-list">
                    <!-- Δημιουργούμε τη λίστα των καρτών εργασιών. -->

                    <?php foreach ($tasks as $task) { ?>

                        <?php

                        // Ελέγχουμε αν η εργασία έχει ολοκληρωθεί.
                        $isCompleted = $task["status"] === "completed";

                        // Μετατρέπουμε την ημερομηνία προθεσμίας σε αντικείμενο DateTime.
                        $dueDateObject = !empty($task["due_date"])
                            ? DateTime::createFromFormat("!Y-m-d", $task["due_date"])
                            : false;

                        // Μετατρέπουμε την ημερομηνία προγραμματισμού.
                        $scheduledDateObject = !empty($task["scheduled_date"])
                            ? DateTime::createFromFormat("!Y-m-d", $task["scheduled_date"])
                            : false;

                        ?>

                        <article class="task-card <?= $isCompleted ? "is-completed" : "" ?>">
                            <!-- Κάρτα συγκεκριμένης εργασίας. -->

                            <div class="task-card-content">

                                <div class="task-title-row">
                                    <!-- Τίτλος και κατάσταση εργασίας. -->

                                    <h3>
                                        <?= htmlspecialchars($task["title"], ENT_QUOTES, "UTF-8") ?>
                                    </h3>

                                    <span class="task-status <?= $isCompleted ? "status-completed" : "status-pending" ?>">
                                        <?= htmlspecialchars(
                                            $isCompleted
                                                ? t("task_status_completed")
                                                : t("task_status_pending"),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </span>
                                    <!-- Εμφανίζουμε την κατάσταση. -->

                                </div>

                                <p class="task-description">
                                    <?= htmlspecialchars($task["description"] ?? "", ENT_QUOTES, "UTF-8") ?>
                                </p>
                                <!-- Εμφανίζουμε την περιγραφή. -->

                                <div class="task-meta">
                                    <!-- Περιοχή πληροφοριών εργασίας. -->

                                    <span>

                                        <strong>
                                            <?= htmlspecialchars(t("task_due_label"), ENT_QUOTES, "UTF-8") ?>
                                        </strong>

                                        <?php if ($dueDateObject) { ?>

                                            <?= htmlspecialchars($dueDateObject->format("d/m/Y"), ENT_QUOTES, "UTF-8") ?>

                                        <?php } else { ?>

                                            <?= htmlspecialchars(t("task_no_due_date"), ENT_QUOTES, "UTF-8") ?>

                                        <?php } ?>

                                    </span>

                                    <?php if ($scheduledDateObject) { ?>

                                        <span>

                                            <strong>
                                                <?= htmlspecialchars($scheduleText["date_time"], ENT_QUOTES, "UTF-8") ?>:
                                            </strong>

                                            <?= htmlspecialchars($scheduledDateObject->format("d/m/Y"), ENT_QUOTES, "UTF-8") ?>

                                            <?= htmlspecialchars(substr($task["start_time"], 0, 5), ENT_QUOTES, "UTF-8") ?>

                                            –

                                            <?= htmlspecialchars(substr($task["end_time"], 0, 5), ENT_QUOTES, "UTF-8") ?>

                                        </span>
                                        <!-- Εμφανίζουμε την ημερομηνία και το χρονικό διάστημα προγραμματισμού. -->

                                    <?php } ?>

                                </div>

                            </div>

                            <div class="task-actions">
                                <!-- Περιοχή ενεργειών. -->

                                <a
                                    href="edit.php?id=<?= (int)$task["id"] ?>"
                                    class="button-secondary"
                                >
                                    <?= htmlspecialchars(t("task_edit_button"), ENT_QUOTES, "UTF-8") ?>
                                </a>
                                <!-- Σύνδεσμος επεξεργασίας. -->

                                <form method="POST" action="" class="inline-action-form">

                                    <input type="hidden" name="action" value="complete">

                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, "UTF-8") ?>">
                                    <!-- Προστατεύουμε την αλλαγή κατάστασης με CSRF token. -->

                                    <input
                                        type="hidden"
                                        name="task_id"
                                        value="<?= (int)$task["id"] ?>"
                                    >
                                    <!-- Στέλνουμε το ID της εργασίας. -->

                                    <button
                                        type="submit"
                                        class="button-complete"
                                    >
                                        <?= htmlspecialchars(
                                            $isCompleted
                                                ? t("task_restore_button")
                                                : t("task_complete_button"),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </button>
                                    <!-- Κουμπί αλλαγής κατάστασης. -->

                                </form>

                                <form
                                    method="POST"
                                    action=""
                                    class="inline-action-form"
                                    onsubmit="return confirm(<?= htmlspecialchars(json_encode(t("task_confirm_delete"), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, "UTF-8") ?>);"
                                >

                                    <input type="hidden" name="action" value="delete">

                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, "UTF-8") ?>">
                                    <!-- Προστατεύουμε τη διαγραφή με CSRF token. -->

                                    <input
                                        type="hidden"
                                        name="task_id"
                                        value="<?= (int)$task["id"] ?>"
                                    >
                                    <!-- Στέλνουμε το ID για διαγραφή. -->

                                    <button
                                        type="submit"
                                        class="button-delete"
                                    >
                                        <?= htmlspecialchars(t("task_delete_button"), ENT_QUOTES, "UTF-8") ?>
                                    </button>
                                    <!-- Κουμπί διαγραφής. -->

                                </form>

                            </div>

                        </article>

                    <?php } ?>

                </div>

            <?php } ?>

        </section>

    </main>

</body>
</html>
