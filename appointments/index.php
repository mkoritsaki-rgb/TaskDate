
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

// Αρχικοποιούμε τη μεταβλητή της προτεινόμενης ώρας.
$suggestion = null;

// Δημιουργούμε πίνακα για τα στοιχεία της φόρμας.
$formData = [
    "person_name" => "",
    "subject" => "",
    "appointment_date" => "",
    "start_time" => "",
    "end_time" => "",
    "notes" => ""
];

// Ελέγχουμε αν έχει υποβληθεί φόρμα με POST.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Ελέγχουμε πρώτα αν το CSRF token είναι έγκυρο.
    if (!verifyCsrfToken($_POST["csrf_token"] ?? null)) {

        // Εμφανίζουμε μήνυμα αν το token δεν είναι έγκυρο.
        $errorMessage = t("auth_csrf_error");

    } else {

        // Παίρνουμε την ενέργεια που ζητά ο χρήστης.
        $action = $_POST["action"] ?? "";

        // Ελέγχουμε αν ο χρήστης θέλει να διαγράψει ραντεβού.
        if ($action === "delete") {

            // Ελέγχουμε αν το ID είναι έγκυρος ακέραιος.
            $appointmentId = filter_var(
                $_POST["appointment_id"] ?? null,
                FILTER_VALIDATE_INT
            );

            // Ελέγχουμε αν το ID είναι θετικό.
            if (
                $appointmentId === false ||
                $appointmentId === null ||
                $appointmentId < 1
            ) {

                $errorMessage = t("appointment_error_invalid");

            } else {

                // Δημιουργούμε SQL εντολή διαγραφής μόνο για τον ιδιοκτήτη.
                $delete = $pdo->prepare(
                    "DELETE FROM appointments
                     WHERE id = :id
                     AND " . getOwnerCondition()
                );

                // Εκτελούμε τη διαγραφή με το ID και τα στοιχεία ιδιοκτησίας.
                $delete->execute(
                    array_merge(
                        [":id" => $appointmentId],
                        getOwnerParameters()
                    )
                );

                // Επιστρέφουμε στη λίστα με μήνυμα επιτυχίας.
                header("Location: index.php?deleted=1");

                // Σταματάμε την εκτέλεση.
                exit;
            }

        // Ελέγχουμε αν ο χρήστης προσθέτει ή αποδέχεται ραντεβού.
        } elseif ($action === "add" || $action === "confirm_suggestion") {

            // Παίρνουμε τα στοιχεία της φόρμας με ασφαλή έλεγχο τύπου.
            foreach ($formData as $field => $value) {

                $submittedValue = $_POST[$field] ?? "";

                // Δεχόμαστε μόνο απλές συμβολοσειρές.
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

                // Ελέγχουμε την εγκυρότητα των στοιχείων.
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
                    $duration = (int) (
                        (
                            $endDateTime->getTimestamp() -
                            $startDateTime->getTimestamp()
                        ) / 60
                    );

                    // Μετατρέπουμε την αρχική ώρα έναρξης σε λεπτά από τα μεσάνυχτα.
                    $requestedStartMinutes =
                        ((int) substr($startTime, 0, 2) * 60) +
                        (int) substr($startTime, 3, 2);

                    // Αναζητούμε συγκρούσεις μόνο στα ραντεβού του ίδιου ιδιοκτήτη.
                    $conflictCheck = $pdo->prepare(
                        "SELECT id FROM appointments
                         WHERE appointment_date = :date
                         AND start_time < :end_time
                         AND end_time > :start_time
                         AND " . getOwnerCondition()
                    );

                    // Εκτελούμε τον έλεγχο συγκρούσεων.
                    $conflictCheck->execute(
                        array_merge(
                            [
                                ":date" => $appointmentDate,
                                ":start_time" => $startTime,
                                ":end_time" => $endTime
                            ],
                            getOwnerParameters()
                        )
                    );

                    // Παίρνουμε το πρώτο ραντεβού που συγκρούεται, αν υπάρχει.
                    $hasConflict = $conflictCheck->fetch();

                    // Ελέγχουμε αν υπάρχει σύγκρουση.
                    if ($hasConflict) {

                        // Αν ο χρήστης αποδέχθηκε προηγούμενη πρόταση,
                        // δεν δημιουργούμε δεύτερη πρόταση.
                        if ($action === "confirm_suggestion") {

                            $errorMessage = t("appointment_error_suggestion_unavailable");

                        } else {

                            // Ανακτούμε όλα τα ραντεβού της ίδιας ημέρας
                            // που ανήκουν στον συγκεκριμένο χρήστη ή επισκέπτη.
                            $dayAppointmentsQuery = $pdo->prepare(
                                "SELECT start_time, end_time
                                 FROM appointments
                                 WHERE appointment_date = :date
                                 AND " . getOwnerCondition()
                            );

                            // Εκτελούμε την αναζήτηση των ραντεβού της ημέρας.
                            $dayAppointmentsQuery->execute(
                                array_merge(
                                    [":date" => $appointmentDate],
                                    getOwnerParameters()
                                )
                            );

                            // Αποθηκεύουμε όλα τα ραντεβού της ημέρας.
                            $dayAppointments = $dayAppointmentsQuery->fetchAll();

                            // Αρχικοποιούμε τη μεταβλητή της διαθέσιμης πρότασης.
                            $availableSuggestion = null;

                            // Ορίζουμε το απαραίτητο κενό μεταξύ δύο ραντεβού.
                            $bufferMinutes = 15;

                            // Δημιουργούμε συνάρτηση που ελέγχει αν μια ώρα είναι διαθέσιμη.
                            $isTimeAvailable = function ($candidateStart) use (
                                $duration,
                                $dayAppointments,
                                $bufferMinutes
                            ) {

                                // Υπολογίζουμε την ώρα λήξης της υποψήφιας πρότασης.
                                $candidateEnd = $candidateStart + $duration;

                                // Ελέγχουμε αν η πρόταση παραμένει μέσα στην ίδια ημέρα.
                                if ($candidateStart < 0 || $candidateEnd > 1440) {
                                    return false;
                                }

                                // Ελέγχουμε όλα τα υπάρχοντα ραντεβού της ημέρας.
                                foreach ($dayAppointments as $existingAppointment) {

                                    // Μετατρέπουμε την ώρα έναρξης του υπάρχοντος ραντεβού σε λεπτά.
                                    $existingStart =
                                        ((int) substr($existingAppointment["start_time"], 0, 2) * 60) +
                                        (int) substr($existingAppointment["start_time"], 3, 2);

                                    // Μετατρέπουμε την ώρα λήξης του υπάρχοντος ραντεβού σε λεπτά.
                                    $existingEnd =
                                        ((int) substr($existingAppointment["end_time"], 0, 2) * 60) +
                                        (int) substr($existingAppointment["end_time"], 3, 2);

                                    // Ελέγχουμε αν η νέα ώρα βρίσκεται πριν από το υπάρχον ραντεβού
                                    // και αφήνει τουλάχιστον 15 λεπτά κενό.
                                    $fitsBefore =
                                        ($candidateEnd + $bufferMinutes) <= $existingStart;

                                    // Ελέγχουμε αν η νέα ώρα βρίσκεται μετά το υπάρχον ραντεβού
                                    // και αφήνει τουλάχιστον 15 λεπτά κενό.
                                    $fitsAfter =
                                        $candidateStart >= ($existingEnd + $bufferMinutes);

                                    // Αν δεν ισχύει καμία από τις δύο περιπτώσεις,
                                    // υπάρχει σύγκρουση ή ανεπαρκές χρονικό κενό.
                                    if (!$fitsBefore && !$fitsAfter) {
                                        return false;
                                    }
                                }

                                // Αν περάσαμε όλους τους ελέγχους, η ώρα είναι διαθέσιμη.
                                return true;
                            };

                            // ΠΡΩΤΑ αναζητούμε διαθέσιμη ώρα πριν από την αρχική.
                            // Ξεκινάμε ένα λεπτό νωρίτερα και κινούμαστε προς τα πίσω.
                            for (
                                $candidateStart = $requestedStartMinutes - 1;
                                $candidateStart >= 0;
                                $candidateStart--
                            ) {

                                // Ελέγχουμε αν η υποψήφια ώρα είναι διαθέσιμη.
                                if ($isTimeAvailable($candidateStart)) {

                                    // Αποθηκεύουμε την πρώτη διαθέσιμη ώρα.
                                    $availableSuggestion = $candidateStart;

                                    // Σταματάμε την αναζήτηση.
                                    break;
                                }
                            }

                            // Αν δεν βρέθηκε προηγούμενη ώρα, αναζητούμε μετά την αρχική.
                            if ($availableSuggestion === null) {

                                // Δεν επιτρέπουμε η ώρα λήξης να ξεπεράσει τα μεσάνυχτα.
                                $latestPossibleStart = 1440 - $duration;

                                // Ξεκινάμε ένα λεπτό μετά την αρχική ώρα.
                                for (
                                    $candidateStart = $requestedStartMinutes + 1;
                                    $candidateStart <= $latestPossibleStart;
                                    $candidateStart++
                                ) {

                                    // Ελέγχουμε αν η υποψήφια ώρα είναι διαθέσιμη.
                                    if ($isTimeAvailable($candidateStart)) {

                                        // Αποθηκεύουμε την πρώτη διαθέσιμη ώρα.
                                        $availableSuggestion = $candidateStart;

                                        // Σταματάμε την αναζήτηση.
                                        break;
                                    }
                                }
                            }

                            // Ελέγχουμε αν βρέθηκε διαθέσιμη ώρα.
                            if ($availableSuggestion === null) {

                                // Εμφανίζουμε μήνυμα όταν δεν υπάρχει διαθέσιμο διάστημα.
                                $errorMessage = t("appointment_error_no_suggestion");

                            } else {

                                // Μετατρέπουμε τα λεπτά της πρότασης σε ώρα έναρξης.
                                $suggestedStart = sprintf(
                                    "%02d:%02d",
                                    intdiv($availableSuggestion, 60),
                                    $availableSuggestion % 60
                                );

                                // Υπολογίζουμε τα λεπτά της ώρας λήξης.
                                $suggestedEndMinutes =
                                    $availableSuggestion + $duration;

                                // Μετατρέπουμε τα λεπτά της λήξης σε ώρα.
                                $suggestedEnd = sprintf(
                                    "%02d:%02d",
                                    intdiv($suggestedEndMinutes, 60),
                                    $suggestedEndMinutes % 60
                                );

                                // Αποθηκεύουμε προσωρινά την πρόταση.
                                $suggestion = [
                                    "person_name" => $personName,
                                    "subject" => $subject,
                                    "appointment_date" => $appointmentDate,
                                    "start_time" => $suggestedStart,
                                    "end_time" => $suggestedEnd,
                                    "notes" => $notes
                                ];
                            }
                        }

                    } else {

                        // Παίρνουμε τα στοιχεία ιδιοκτησίας του χρήστη ή επισκέπτη.
                        $ownerValues = getOwnerInsertValues();

                        // Δημιουργούμε SQL εντολή αποθήκευσης του ραντεβού.
                        $statement = $pdo->prepare(
                            "INSERT INTO appointments
                            (
                                person_name,
                                subject,
                                appointment_date,
                                start_time,
                                end_time,
                                notes,
                                user_id,
                                guest_token
                            )
                            VALUES
                            (
                                :person_name,
                                :subject,
                                :appointment_date,
                                :start_time,
                                :end_time,
                                :notes,
                                :user_id,
                                :guest_token
                            )"
                        );

                        // Αποθηκεύουμε τα στοιχεία και τον ιδιοκτήτη στη βάση.
                        $statement->execute(
                            array_merge(
                                [
                                    ":person_name" => $personName,
                                    ":subject" => $subject,
                                    ":appointment_date" => $appointmentDate,
                                    ":start_time" => $startTime,
                                    ":end_time" => $endTime,
                                    ":notes" => $notes !== "" ? $notes : null
                                ],
                                $ownerValues
                            )
                        );

                        // Επιστρέφουμε στη λίστα με μήνυμα επιτυχίας.
                        header("Location: index.php?added=1");

                        // Σταματάμε την εκτέλεση.
                        exit;
                    }
                }
            }
        }
    }
}

// Ελέγχουμε αν προστέθηκε ραντεβού.
if (isset($_GET["added"])) {
    $successMessage = t("appointment_success_added");
}

// Ελέγχουμε αν διαγράφηκε ραντεβού.
if (isset($_GET["deleted"])) {
    $successMessage = t("appointment_success_deleted");
}

// Ελέγχουμε αν επεξεργάστηκε ραντεβού.
if (isset($_GET["updated"])) {
    $successMessage = t("appointment_success_updated");
}

// Ανακτούμε μόνο τα ραντεβού του συγκεκριμένου ιδιοκτήτη.
$statement = $pdo->prepare(
    "SELECT * FROM appointments
     WHERE " . getOwnerCondition() . "
     ORDER BY appointment_date ASC, start_time ASC"
);

// Εκτελούμε την αναζήτηση με τα στοιχεία ιδιοκτησίας.
$statement->execute(getOwnerParameters());

// Αποθηκεύουμε τα αποτελέσματα σε πίνακα.
$appointments = $statement->fetchAll();

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLanguage, ENT_QUOTES, "UTF-8") ?>">

<head>

    <meta charset="UTF-8">
    <!-- Ορίζουμε την κωδικοποίηση χαρακτήρων. -->

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Προσαρμόζουμε τη σελίδα σε κινητά και υπολογιστές. -->

    <title>TaskDate - <?= htmlspecialchars(t("nav_appointments"), ENT_QUOTES, "UTF-8") ?></title>
    <!-- Ορίζουμε τον τίτλο της σελίδας στη σωστή γλώσσα. -->

    <link rel="stylesheet" href="/TaskDate/assets/css/style.css?v=4">
    <!-- Φορτώνουμε το κεντρικό CSS. -->

</head>

<body>

    <?php
    // Φορτώνουμε το κοινό μενού πλοήγησης.
    require_once __DIR__ . "/../includes/navigation.php";
    ?>

    <main class="page-container appointments-page">
        <!-- Κεντρικό περιεχόμενο της σελίδας. -->

        <header class="page-heading">

            <p class="page-eyebrow">
                <?= htmlspecialchars(t("appointments_eyebrow"), ENT_QUOTES, "UTF-8") ?>
            </p>

            <h1>
                <?= htmlspecialchars(t("appointments_page_title"), ENT_QUOTES, "UTF-8") ?>
            </h1>

            <p class="page-intro">
                <?= htmlspecialchars(t("appointments_page_intro"), ENT_QUOTES, "UTF-8") ?>
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

        <section class="appointment-create-panel">
            <!-- Περιοχή προσθήκης νέου ραντεβού. -->

            <div class="section-heading">
                <div>
                    <h2>
                        <?= htmlspecialchars(t("appointment_new_title"), ENT_QUOTES, "UTF-8") ?>
                    </h2>

                    <p>
                        <?= htmlspecialchars(t("appointment_new_intro"), ENT_QUOTES, "UTF-8") ?>
                    </p>
                </div>
            </div>

            <form method="POST" action="" class="appointment-form">
                <!-- Φόρμα προσθήκης ραντεβού. -->

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, "UTF-8") ?>">
                <!-- Στέλνουμε το CSRF token για προστασία της φόρμας. -->

                <input type="hidden" name="action" value="add">
                <!-- Δηλώνουμε ότι πρόκειται για προσθήκη. -->

                <div class="appointment-form-grid">

                    <div class="form-field">
                        <label for="person_name">
                            <?= htmlspecialchars(t("appointment_person_label"), ENT_QUOTES, "UTF-8") ?>
                        </label>

                        <input
                            type="text"
                            id="person_name"
                            name="person_name"
                            value="<?= htmlspecialchars($formData["person_name"], ENT_QUOTES, "UTF-8") ?>"
                            placeholder="<?= htmlspecialchars(t("appointment_person_placeholder"), ENT_QUOTES, "UTF-8") ?>"
                            required
                        >
                        <!-- Πεδίο ονόματος. -->
                    </div>

                    <div class="form-field">
                        <label for="subject">
                            <?= htmlspecialchars(t("appointment_subject_label"), ENT_QUOTES, "UTF-8") ?>
                        </label>

                        <input
                            type="text"
                            id="subject"
                            name="subject"
                            value="<?= htmlspecialchars($formData["subject"], ENT_QUOTES, "UTF-8") ?>"
                            placeholder="<?= htmlspecialchars(t("appointment_subject_placeholder"), ENT_QUOTES, "UTF-8") ?>"
                            required
                        >
                        <!-- Πεδίο θέματος. -->
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
                        <!-- Πεδίο ημερομηνίας. -->
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
                        <!-- Πεδίο ώρας έναρξης. -->
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
                        <!-- Πεδίο ώρας λήξης. -->
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
                        <!-- Πεδίο σημειώσεων. -->
                    </div>

                </div>

                <button type="submit" class="button-primary">
                    + <?= htmlspecialchars(t("appointment_add_button"), ENT_QUOTES, "UTF-8") ?>
                </button>
                <!-- Κουμπί υποβολής. -->

            </form>

        </section>

        <?php if ($suggestion !== null) { ?>

            <!-- Περιοχή εμφάνισης εναλλακτικής πρότασης. -->
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

                <form method="POST" action="" class="suggestion-form">
                    <!-- Φόρμα αποδοχής προτεινόμενης ώρας. -->

                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, "UTF-8") ?>">
                    <!-- Στέλνουμε το CSRF token και στη φόρμα πρότασης. -->

                    <input type="hidden" name="action" value="confirm_suggestion">

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

                </form>

            </section>

        <?php } ?>

        <section class="appointments-section">
            <!-- Περιοχή καταχωρημένων ραντεβού. -->

            <div class="section-heading">
                <div>
                    <h2>
                        <?= htmlspecialchars(t("appointment_list_title"), ENT_QUOTES, "UTF-8") ?>
                    </h2>

                    <p>
                        <?= htmlspecialchars(t("appointment_list_intro"), ENT_QUOTES, "UTF-8") ?>
                    </p>
                </div>

                <span class="task-counter">
                    <?= count($appointments) ?>

                    <?= htmlspecialchars(t("appointment_count_label"), ENT_QUOTES, "UTF-8") ?>
                </span>
            </div>

            <?php if (empty($appointments)) { ?>

                <!-- Μήνυμα όταν δεν υπάρχουν ραντεβού. -->
                <div class="empty-state">

                    <h3>
                        <?= htmlspecialchars(t("appointment_empty_title"), ENT_QUOTES, "UTF-8") ?>
                    </h3>

                    <p>
                        <?= htmlspecialchars(t("appointment_empty_description"), ENT_QUOTES, "UTF-8") ?>
                    </p>

                </div>

            <?php } else { ?>

                <div class="appointments-grid">
                    <!-- Δημιουργούμε το πλέγμα καρτών ραντεβού. -->

                    <?php foreach ($appointments as $appointment) { ?>

                        <?php
                        // Μετατρέπουμε την ημερομηνία σε αντικείμενο DateTime.
                        $displayDate = DateTime::createFromFormat(
                            "!Y-m-d",
                            $appointment["appointment_date"]
                        );
                        ?>

                        <article class="appointment-card">
                            <!-- Κάρτα συγκεκριμένου ραντεβού. -->

                            <div class="appointment-card-header">

                                <div>
                                    <p class="appointment-person">
                                        <?= htmlspecialchars($appointment["person_name"], ENT_QUOTES, "UTF-8") ?>
                                    </p>
                                    <!-- Εμφανίζουμε το όνομα προσώπου. -->

                                    <h3>
                                        <?= htmlspecialchars($appointment["subject"], ENT_QUOTES, "UTF-8") ?>
                                    </h3>
                                    <!-- Εμφανίζουμε το θέμα. -->

                                </div>

                                <span class="appointment-date-badge">
                                    <?= htmlspecialchars(
                                        $displayDate->format("d/m/Y"),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </span>
                                <!-- Εμφανίζουμε την ημερομηνία. -->

                            </div>

                            <div class="appointment-time">

                                <span>
                                    <?= htmlspecialchars(t("appointment_time_label"), ENT_QUOTES, "UTF-8") ?>
                                </span>

                                <strong>
                                    <?= htmlspecialchars(substr($appointment["start_time"], 0, 5), ENT_QUOTES, "UTF-8") ?>
                                    –
                                    <?= htmlspecialchars(substr($appointment["end_time"], 0, 5), ENT_QUOTES, "UTF-8") ?>
                                </strong>
                                <!-- Εμφανίζουμε το χρονικό διάστημα. -->

                            </div>

                            <?php if (!empty($appointment["notes"])) { ?>

                                <p class="appointment-notes">
                                    <?= htmlspecialchars($appointment["notes"], ENT_QUOTES, "UTF-8") ?>
                                </p>
                                <!-- Εμφανίζουμε τις σημειώσεις αν υπάρχουν. -->

                            <?php } ?>

                            <div class="appointment-actions">
                                <!-- Περιοχή ενεργειών. -->

                                <a
                                    href="edit.php?id=<?= (int)$appointment["id"] ?>"
                                    class="button-secondary"
                                >
                                    <?= htmlspecialchars(t("appointment_edit_button"), ENT_QUOTES, "UTF-8") ?>
                                </a>
                                <!-- Σύνδεσμος επεξεργασίας. -->

                                <form
                                    method="POST"
                                    action=""
                                    class="inline-action-form"
                                    onsubmit="return confirm(<?= htmlspecialchars(json_encode(t("appointment_confirm_delete")), ENT_QUOTES, "UTF-8") ?>);"
                                >

                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, "UTF-8") ?>">
                                    <!-- Στέλνουμε το CSRF token στη φόρμα διαγραφής. -->

                                    <input type="hidden" name="action" value="delete">

                                    <input
                                        type="hidden"
                                        name="appointment_id"
                                        value="<?= (int)$appointment["id"] ?>"
                                    >
                                    <!-- Στέλνουμε το ID του ραντεβού. -->

                                    <button type="submit" class="button-delete">
                                        <?= htmlspecialchars(t("appointment_delete_button"), ENT_QUOTES, "UTF-8") ?>
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
