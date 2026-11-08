<?php

// Φορτώνουμε το σύστημα γλωσσών και ξεκινάμε τη συνεδρία.
require_once __DIR__ . "/../includes/language.php";

// Συνδέουμε τη σελίδα με τη βάση δεδομένων.
require_once __DIR__ . "/../config/database.php";

// Φορτώνουμε τις λειτουργίες αυθεντικοποίησης και ιδιοκτησίας.
require_once __DIR__ . "/../includes/auth.php";

// Παίρνουμε το ID του ραντεβού από τη διεύθυνση URL.
$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

// Ελέγχουμε αν το ID είναι έγκυρο.
if ($id === false || $id === null || $id < 1) {
    exit(t("appointment_error_invalid"));
}

// Αναζητούμε το ραντεβού μόνο ανάμεσα στα δεδομένα του τρέχοντος χρήστη.
$statement = $pdo->prepare(
    "SELECT * FROM appointments
     WHERE id = :id AND " . getOwnerCondition()
);

// Εκτελούμε την αναζήτηση με το ID και τα στοιχεία ιδιοκτησίας.
$statement->execute(
    array_merge(
        [":id" => $id],
        getOwnerParameters()
    )
);

// Παίρνουμε τα στοιχεία του ραντεβού.
$appointment = $statement->fetch();

// Ελέγχουμε αν βρέθηκε ραντεβού που ανήκει στον τρέχοντα χρήστη.
if (!$appointment) {
    http_response_code(404);
    exit(t("appointment_edit_not_found"));
}

// Αποθηκεύουμε τα υπάρχοντα στοιχεία της φόρμας.
$formData = [
    "person_name" => $appointment["person_name"],
    "subject" => $appointment["subject"],
    "appointment_date" => $appointment["appointment_date"],
    "start_time" => substr($appointment["start_time"], 0, 5),
    "end_time" => substr($appointment["end_time"], 0, 5),
    "notes" => $appointment["notes"] ?? ""
];

// Δημιουργούμε μεταβλητές για μηνύματα και προτεινόμενη ώρα.
$errorMessage = "";
$suggestion = null;

// Ελέγχουμε αν υποβλήθηκε η φόρμα.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Ελέγχουμε αν το CSRF token είναι έγκυρο.
    if (!verifyCsrfToken($_POST["csrf_token"] ?? null)) {
        http_response_code(403);
        exit(t("auth_csrf_error"));
    }

    // Παίρνουμε την ενέργεια που ζητά ο χρήστης.
    $action = $_POST["action"] ?? "update";

    // Επιτρέπουμε μόνο τις συγκεκριμένες ενέργειες.
    if (!in_array($action, ["update", "confirm_suggestion"], true)) {
        http_response_code(400);
        exit(t("appointment_error_invalid"));
    }

    // Παίρνουμε τα νέα στοιχεία της φόρμας με έλεγχο τύπου.
    foreach ($formData as $field => $value) {
        $submittedValue = $_POST[$field] ?? "";

        $formData[$field] = is_string($submittedValue)
            ? trim($submittedValue)
            : "";
    }

    // Αποθηκεύουμε τα στοιχεία σε μεταβλητές.
    $personName = $formData["person_name"];
    $subject = $formData["subject"];
    $appointmentDate = $formData["appointment_date"];
    $startTime = $formData["start_time"];
    $endTime = $formData["end_time"];
    $notes = $formData["notes"];

    // Ελέγχουμε αν έχουν συμπληρωθεί τα υποχρεωτικά πεδία.
    if (
        $personName === "" ||
        $subject === "" ||
        $appointmentDate === "" ||
        $startTime === "" ||
        $endTime === ""
    ) {

        $errorMessage = t("appointment_error_required");

    } else {

        // Ελέγχουμε αν η ημερομηνία είναι έγκυρη.
        $dateObject = DateTime::createFromFormat(
            "!Y-m-d",
            $appointmentDate
        );

        // Επιβεβαιώνουμε τη σωστή μορφή ημερομηνίας.
        $validDate = $dateObject &&
            $dateObject->format("Y-m-d") === $appointmentDate;

        // Ελέγχουμε αν η ώρα έναρξης είναι έγκυρη.
        $validStartTime = preg_match(
            "/^(?:[01]\d|2[0-3]):[0-5]\d$/",
            $startTime
        );

        // Ελέγχουμε αν η ώρα λήξης είναι έγκυρη.
        $validEndTime = preg_match(
            "/^(?:[01]\d|2[0-3]):[0-5]\d$/",
            $endTime
        );

        // Ελέγχουμε την εγκυρότητα της ημερομηνίας και των ωρών.
        if (!$validDate || !$validStartTime || !$validEndTime) {

            $errorMessage = t("appointment_error_invalid_date_time");

        } elseif ($endTime <= $startTime) {

            // Ελέγχουμε αν η λήξη είναι μετά την έναρξη.
            $errorMessage = t("appointment_error_end_before_start");

        } else {

            // Δημιουργούμε αντικείμενο για την ώρα έναρξης.
            $startDateTime = new DateTime(
                $appointmentDate . " " . $startTime
            );

            // Δημιουργούμε αντικείμενο για την ώρα λήξης.
            $endDateTime = new DateTime(
                $appointmentDate . " " . $endTime
            );

            // Υπολογίζουμε τη διάρκεια του ραντεβού σε λεπτά.
            $duration = (
                $endDateTime->getTimestamp() -
                $startDateTime->getTimestamp()
            ) / 60;

            // Αναζητούμε άλλα ραντεβού του ίδιου χρήστη που συγκρούονται.
            // Εξαιρούμε το ραντεβού που επεξεργαζόμαστε.
            $conflictCheck = $pdo->prepare(
                "SELECT * FROM appointments
                 WHERE appointment_date = :date
                 AND id <> :id
                 AND start_time < :end_time
                 AND end_time > :start_time
                 AND " . getOwnerCondition() . "
                 ORDER BY end_time DESC"
            );

            // Εκτελούμε τον έλεγχο συγκρούσεων.
            $conflictCheck->execute(
                array_merge(
                    [
                        ":date" => $appointmentDate,
                        ":id" => $id,
                        ":start_time" => $startTime,
                        ":end_time" => $endTime
                    ],
                    getOwnerParameters()
                )
            );

            // Παίρνουμε όλα τα ραντεβού που συγκρούονται.
            $conflicts = $conflictCheck->fetchAll();

            // Ελέγχουμε αν υπάρχει σύγκρουση.
            if (!empty($conflicts)) {

                // Αν ο χρήστης αποδέχεται ήδη μια πρόταση, ελέγχουμε αν παραμένει διαθέσιμη.
                if ($action === "confirm_suggestion") {

                    $errorMessage = t("appointment_error_suggestion_unavailable");

                } else {

                    // Αναζητούμε όλα τα ραντεβού της ίδιας ημέρας του συγκεκριμένου χρήστη.
                    $dayAppointments = $pdo->prepare(
                        "SELECT start_time, end_time
                         FROM appointments
                         WHERE appointment_date = :date
                         AND id <> :id
                         AND " . getOwnerCondition()
                    );

                    // Εκτελούμε την αναζήτηση.
                    $dayAppointments->execute(
                        array_merge(
                            [
                                ":date" => $appointmentDate,
                                ":id" => $id
                            ],
                            getOwnerParameters()
                        )
                    );

                    // Αποθηκεύουμε τα ραντεβού της ημέρας.
                    $existingAppointments = $dayAppointments->fetchAll();

                    // Δημιουργούμε μεταβλητή για την επιτυχημένη πρόταση.
                    $foundSuggestion = false;

                    // Ξεκινάμε την αναζήτηση ένα λεπτό πριν από την αρχική ώρα.
                    $candidateStart = clone $startDateTime;
                    $candidateStart->modify("-1 minute");

                    // Αναζητούμε πρώτα διαθέσιμη ώρα νωρίτερα μέσα στην ίδια ημέρα.
                    while ($candidateStart->format("Y-m-d") === $appointmentDate) {

                        // Υπολογίζουμε την ώρα λήξης διατηρώντας την αρχική διάρκεια.
                        $candidateEnd = clone $candidateStart;
                        $candidateEnd->modify("+" . (int)$duration . " minutes");

                        // Σταματάμε αν η λήξη περάσει στην επόμενη ημέρα.
                        if ($candidateEnd->format("Y-m-d") !== $appointmentDate) {
                            break;
                        }

                        // Θεωρούμε αρχικά ότι η υποψήφια ώρα είναι διαθέσιμη.
                        $isAvailable = true;

                        // Ελέγχουμε την υποψήφια ώρα απέναντι σε κάθε υπάρχον ραντεβού.
                        foreach ($existingAppointments as $existing) {

                            // Μετατρέπουμε την ώρα έναρξης του υπάρχοντος ραντεβού.
                            $existingStart = new DateTime(
                                $appointmentDate . " " . $existing["start_time"]
                            );

                            // Μετατρέπουμε την ώρα λήξης του υπάρχοντος ραντεβού.
                            $existingEnd = new DateTime(
                                $appointmentDate . " " . $existing["end_time"]
                            );

                            // Υπολογίζουμε 15 λεπτά μετά τη λήξη της νέας πρότασης.
                            $candidateEndWithBuffer = clone $candidateEnd;
                            $candidateEndWithBuffer->modify("+15 minutes");

                            // Υπολογίζουμε 15 λεπτά μετά τη λήξη του υπάρχοντος ραντεβού.
                            $existingEndWithBuffer = clone $existingEnd;
                            $existingEndWithBuffer->modify("+15 minutes");

                            // Ελέγχουμε αν υπάρχει επαρκές κενό πριν από το υπάρχον ραντεβού.
                            $fitsBefore = $candidateEndWithBuffer <= $existingStart;

                            // Ελέγχουμε αν υπάρχει επαρκές κενό μετά το υπάρχον ραντεβού.
                            $fitsAfter = $candidateStart >= $existingEndWithBuffer;

                            // Αν δεν υπάρχει επαρκές κενό, η ώρα δεν είναι διαθέσιμη.
                            if (!$fitsBefore && !$fitsAfter) {
                                $isAvailable = false;
                                break;
                            }
                        }

                        // Αν βρέθηκε διαθέσιμη ώρα, αποθηκεύουμε την πρόταση.
                        if ($isAvailable) {

                            $suggestion = [
                                "person_name" => $personName,
                                "subject" => $subject,
                                "appointment_date" => $appointmentDate,
                                "start_time" => $candidateStart->format("H:i"),
                                "end_time" => $candidateEnd->format("H:i"),
                                "notes" => $notes
                            ];

                            $foundSuggestion = true;
                            break;
                        }

                        // Αν δεν βρέθηκε διαθέσιμη ώρα, δοκιμάζουμε ένα λεπτό νωρίτερα.
                        $candidateStart->modify("-1 minute");
                    }

                    // Αν δεν βρέθηκε νωρίτερη ώρα, αναζητούμε αργότερα.
                    if (!$foundSuggestion) {

                        // Ξεκινάμε ένα λεπτό μετά την αρχική ώρα έναρξης.
                        $candidateStart = clone $startDateTime;
                        $candidateStart->modify("+1 minute");

                        // Εξετάζουμε τις επόμενες ώρες της ίδιας ημέρας.
                        while ($candidateStart->format("Y-m-d") === $appointmentDate) {

                            // Υπολογίζουμε την ώρα λήξης διατηρώντας την αρχική διάρκεια.
                            $candidateEnd = clone $candidateStart;
                            $candidateEnd->modify("+" . (int)$duration . " minutes");

                            // Σταματάμε αν η λήξη περάσει στην επόμενη ημέρα.
                            if ($candidateEnd->format("Y-m-d") !== $appointmentDate) {
                                break;
                            }

                            // Θεωρούμε αρχικά ότι η υποψήφια ώρα είναι διαθέσιμη.
                            $isAvailable = true;

                            // Ελέγχουμε την υποψήφια ώρα απέναντι σε κάθε υπάρχον ραντεβού.
                            foreach ($existingAppointments as $existing) {

                                // Μετατρέπουμε την ώρα έναρξης του υπάρχοντος ραντεβού.
                                $existingStart = new DateTime(
                                    $appointmentDate . " " . $existing["start_time"]
                                );

                                // Μετατρέπουμε την ώρα λήξης του υπάρχοντος ραντεβού.
                                $existingEnd = new DateTime(
                                    $appointmentDate . " " . $existing["end_time"]
                                );

                                // Υπολογίζουμε 15 λεπτά μετά τη λήξη της νέας πρότασης.
                                $candidateEndWithBuffer = clone $candidateEnd;
                                $candidateEndWithBuffer->modify("+15 minutes");

                                // Υπολογίζουμε 15 λεπτά μετά τη λήξη του υπάρχοντος ραντεβού.
                                $existingEndWithBuffer = clone $existingEnd;
                                $existingEndWithBuffer->modify("+15 minutes");

                                // Ελέγχουμε αν υπάρχει επαρκές κενό πριν από το υπάρχον ραντεβού.
                                $fitsBefore = $candidateEndWithBuffer <= $existingStart;

                                // Ελέγχουμε αν υπάρχει επαρκές κενό μετά το υπάρχον ραντεβού.
                                $fitsAfter = $candidateStart >= $existingEndWithBuffer;

                                // Αν δεν υπάρχει επαρκές κενό, η ώρα δεν είναι διαθέσιμη.
                                if (!$fitsBefore && !$fitsAfter) {
                                    $isAvailable = false;
                                    break;
                                }
                            }

                            // Αν βρέθηκε διαθέσιμη ώρα, αποθηκεύουμε την πρόταση.
                            if ($isAvailable) {

                                $suggestion = [
                                    "person_name" => $personName,
                                    "subject" => $subject,
                                    "appointment_date" => $appointmentDate,
                                    "start_time" => $candidateStart->format("H:i"),
                                    "end_time" => $candidateEnd->format("H:i"),
                                    "notes" => $notes
                                ];

                                $foundSuggestion = true;
                                break;
                            }

                            // Αν δεν βρέθηκε διαθέσιμη ώρα, δοκιμάζουμε ένα λεπτό αργότερα.
                            $candidateStart->modify("+1 minute");
                        }
                    }

                    // Αν δεν βρέθηκε καμία διαθέσιμη ώρα, εμφανίζουμε το αντίστοιχο μήνυμα.
                    if (!$foundSuggestion) {
                        $errorMessage = t("appointment_error_no_suggestion");
                    }
                }

            } else {

                // Δημιουργούμε SQL εντολή για ενημέρωση του ραντεβού.
                // Η ενημέρωση επιτρέπεται μόνο αν το ραντεβού ανήκει στον χρήστη.
                $update = $pdo->prepare(
                    "UPDATE appointments
                     SET person_name = :person_name,
                         subject = :subject,
                         appointment_date = :appointment_date,
                         start_time = :start_time,
                         end_time = :end_time,
                         notes = :notes
                     WHERE id = :id
                     AND " . getOwnerCondition()
                );

                // Αποθηκεύουμε τα νέα στοιχεία στη βάση δεδομένων.
                $update->execute(
                    array_merge(
                        [
                            ":person_name" => $personName,
                            ":subject" => $subject,
                            ":appointment_date" => $appointmentDate,
                            ":start_time" => $startTime,
                            ":end_time" => $endTime,
                            ":notes" => $notes !== "" ? $notes : null,
                            ":id" => $id
                        ],
                        getOwnerParameters()
                    )
                );

                // Ανανεώνουμε το CSRF token μετά την αποθήκευση.
                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                // Επιστρέφουμε στη λίστα με μήνυμα επιτυχίας.
                header("Location: index.php?updated=1");

                // Σταματάμε την εκτέλεση.
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

    <title>TaskDate - <?= htmlspecialchars(t("appointment_edit_page_title"), ENT_QUOTES, "UTF-8") ?></title>
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
            <!-- Κεφαλίδα σελίδας. -->

            <p class="page-eyebrow">
                <?= htmlspecialchars(t("tasks_eyebrow"), ENT_QUOTES, "UTF-8") ?>
            </p>

            <h1>
                <?= htmlspecialchars(t("appointment_edit_page_title"), ENT_QUOTES, "UTF-8") ?>
            </h1>

            <p class="page-intro">
                <?= htmlspecialchars(t("appointment_edit_page_intro"), ENT_QUOTES, "UTF-8") ?>
            </p>

        </header>

        <?php if ($errorMessage !== "") { ?>

            <!-- Εμφανίζουμε μήνυμα λάθους. -->
            <div class="alert alert-error">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8") ?>
            </div>

        <?php } ?>

        <section class="edit-panel appointment-edit-panel">
            <!-- Κεντρική κάρτα επεξεργασίας. -->

            <div class="section-heading">

                <div>
                    <h2>
                        <?= htmlspecialchars(t("appointment_edit_section_title"), ENT_QUOTES, "UTF-8") ?>
                    </h2>

                    <p>
                        <?= htmlspecialchars(t("appointment_edit_section_intro"), ENT_QUOTES, "UTF-8") ?>
                    </p>
                </div>

            </div>

            <form
                method="POST"
                action="edit.php?id=<?= (int)$id ?>"
                class="edit-form appointment-edit-form"
            >
                <!-- Φόρμα επεξεργασίας ραντεβού. -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, "UTF-8") ?>"
                >
                <!-- Μεταφέρουμε το CSRF token για προστασία της υποβολής. -->

                <input type="hidden" name="action" value="update">
                <!-- Δηλώνουμε ότι πρόκειται για επεξεργασία. -->

                <div class="appointment-form-grid">

                    <div class="form-field">

                        <label for="person_name">
                            <?= htmlspecialchars(t("appointment_edit_person_label"), ENT_QUOTES, "UTF-8") ?>
                        </label>

                        <input
                            type="text"
                            id="person_name"
                            name="person_name"
                            value="<?= htmlspecialchars($formData["person_name"], ENT_QUOTES, "UTF-8") ?>"
                            required
                        >
                        <!-- Εμφανίζουμε το υπάρχον όνομα. -->

                    </div>

                    <div class="form-field">

                        <label for="subject">
                            <?= htmlspecialchars(t("appointment_edit_subject_label"), ENT_QUOTES, "UTF-8") ?>
                        </label>

                        <input
                            type="text"
                            id="subject"
                            name="subject"
                            value="<?= htmlspecialchars($formData["subject"], ENT_QUOTES, "UTF-8") ?>"
                            required
                        >
                        <!-- Εμφανίζουμε το υπάρχον θέμα. -->

                    </div>

                    <div class="form-field">

                        <label for="appointment_date">
                            <?= htmlspecialchars(t("appointment_date_label"), ENT_QUOTES, "UTF-8") ?>
                        </label>

                        <input
                            type="date"
                            id="appointment_date"
                            name="appointment_date"
                            value="<?= htmlspecialchars($formData["appointment_date"], ENT_QUOTES, "UTF-8") ?>"
                            required
                        >
                        <!-- Εμφανίζουμε την υπάρχουσα ημερομηνία. -->

                    </div>

                    <div class="form-field">

                        <label for="start_time">
                            <?= htmlspecialchars(t("appointment_start_label"), ENT_QUOTES, "UTF-8") ?>
                        </label>

                        <input
                            type="time"
                            id="start_time"
                            name="start_time"
                            value="<?= htmlspecialchars($formData["start_time"], ENT_QUOTES, "UTF-8") ?>"
                            required
                        >
                        <!-- Εμφανίζουμε την υπάρχουσα ώρα έναρξης. -->

                    </div>

                    <div class="form-field">

                        <label for="end_time">
                            <?= htmlspecialchars(t("appointment_end_label"), ENT_QUOTES, "UTF-8") ?>
                        </label>

                        <input
                            type="time"
                            id="end_time"
                            name="end_time"
                            value="<?= htmlspecialchars($formData["end_time"], ENT_QUOTES, "UTF-8") ?>"
                            required
                        >
                        <!-- Εμφανίζουμε την υπάρχουσα ώρα λήξης. -->

                    </div>

                    <div class="form-field appointment-notes-field">

                        <label for="notes">
                            <?= htmlspecialchars(t("appointment_notes_label"), ENT_QUOTES, "UTF-8") ?>
                        </label>

                        <textarea
                            id="notes"
                            name="notes"
                            placeholder="<?= htmlspecialchars(t("appointment_notes_placeholder"), ENT_QUOTES, "UTF-8") ?>"
                        ><?= htmlspecialchars($formData["notes"], ENT_QUOTES, "UTF-8") ?></textarea>
                        <!-- Εμφανίζουμε τις υπάρχουσες σημειώσεις. -->

                    </div>

                </div>

                <div class="edit-actions">

                    <button type="submit" class="button-primary">
                        <?= htmlspecialchars(t("appointment_edit_save_button"), ENT_QUOTES, "UTF-8") ?>
                    </button>
                    <!-- Κουμπί αποθήκευσης. -->

                    <a href="index.php" class="button-secondary">
                        <?= htmlspecialchars(t("appointment_edit_back_button"), ENT_QUOTES, "UTF-8") ?>
                    </a>
                    <!-- Επιστρέφουμε στη λίστα ραντεβού. -->

                </div>

            </form>

        </section>

        <?php if ($suggestion !== null) { ?>

            <!-- Εμφανίζουμε την προτεινόμενη εναλλακτική ώρα. -->
            <section class="suggestion-panel">

                <h2>
                    <?= htmlspecialchars(t("appointment_conflict_title"), ENT_QUOTES, "UTF-8") ?>
                </h2>

                <p>
                    <?= htmlspecialchars(t("appointment_suggested_date"), ENT_QUOTES, "UTF-8") ?>

                    <?= htmlspecialchars(
                        DateTime::createFromFormat(
                            "!Y-m-d",
                            $suggestion["appointment_date"]
                        )->format("d/m/Y"),
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </p>

                <p>
                    <?= htmlspecialchars(t("appointment_suggested_time"), ENT_QUOTES, "UTF-8") ?>

                    <?= htmlspecialchars($suggestion["start_time"], ENT_QUOTES, "UTF-8") ?>

                    <?= htmlspecialchars(t("appointment_until"), ENT_QUOTES, "UTF-8") ?>

                    <?= htmlspecialchars($suggestion["end_time"], ENT_QUOTES, "UTF-8") ?>
                </p>

                <form
                    method="POST"
                    action="edit.php?id=<?= (int)$id ?>"
                    class="suggestion-form"
                >
                    <!-- Φόρμα αποδοχής της προτεινόμενης ώρας. -->

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, "UTF-8") ?>"
                    >
                    <!-- Προστατεύουμε και την αποδοχή της πρότασης με CSRF token. -->

                    <input
                        type="hidden"
                        name="action"
                        value="confirm_suggestion"
                    >
                    <!-- Δηλώνουμε ότι ο χρήστης αποδέχεται την πρόταση. -->

                    <?php foreach ($suggestion as $field => $value) { ?>

                        <!-- Μεταφέρουμε τα στοιχεία της πρότασης. -->

                        <input
                            type="hidden"
                            name="<?= htmlspecialchars($field, ENT_QUOTES, "UTF-8") ?>"
                            value="<?= htmlspecialchars($value, ENT_QUOTES, "UTF-8") ?>"
                        >

                    <?php } ?>

                    <button type="submit">
                        <?= htmlspecialchars(t("appointment_accept_suggestion"), ENT_QUOTES, "UTF-8") ?>
                    </button>
                    <!-- Κουμπί αποδοχής. -->

                </form>

            </section>

        <?php } ?>

    </main>

</body>
</html>
