
<?php

require_once __DIR__ . "/includes/language.php";

// Συνδέουμε το ημερολόγιο με τη βάση δεδομένων.
require_once __DIR__ . "/config/database.php";

// Φορτώνουμε τις λειτουργίες σύνδεσης και διαχείρισης ιδιοκτησίας.
require_once __DIR__ . "/includes/auth.php";

// Ορίζουμε τη ζώνη ώρας της Ελλάδας.
$timezone = new DateTimeZone("Europe/Athens");

// Παίρνουμε τη σημερινή ημερομηνία.
$today = new DateTime("today", $timezone);

// Αποθηκεύουμε τη σημερινή ημερομηνία σε μορφή έτους-μήνα-ημέρας.
$todayKey = $today->format("Y-m-d");

// Παίρνουμε την επιλεγμένη εβδομάδα από τη διεύθυνση URL.
$weekInput = $_GET["week"] ?? "";

// Ελέγχουμε αν έχει δοθεί έγκυρη ημερομηνία.
if ($weekInput !== "") {

    // Μετατρέπουμε το κείμενο σε αντικείμενο ημερομηνίας.
    $weekDate = DateTime::createFromFormat(
        "!Y-m-d",
        $weekInput,
        $timezone
    );

    // Ελέγχουμε αν η ημερομηνία είναι πραγματικά έγκυρη.
    if (!$weekDate || $weekDate->format("Y-m-d") !== $weekInput) {

        // Αν είναι λανθασμένη, χρησιμοποιούμε τη σημερινή ημερομηνία.
        $weekDate = clone $today;
    }

} else {

    // Αν δεν έχει επιλεγεί εβδομάδα, χρησιμοποιούμε τη σημερινή.
    $weekDate = clone $today;
}

// Υπολογίζουμε την πρώτη ημέρα της εβδομάδας, δηλαδή τη Δευτέρα.
$weekStart = clone $weekDate;
$weekStart->modify("monday this week");

// Υπολογίζουμε την τελευταία ημέρα της εβδομάδας, δηλαδή την Κυριακή.
$weekEnd = clone $weekStart;
$weekEnd->modify("+6 days");

// Υπολογίζουμε την προηγούμενη εβδομάδα.
$previousWeek = clone $weekStart;
$previousWeek->modify("-7 days");

// Υπολογίζουμε την επόμενη εβδομάδα.
$nextWeek = clone $weekStart;
$nextWeek->modify("+7 days");

// Μετατρέπουμε τις ημερομηνίες σε μορφή για τη βάση δεδομένων.
$startDate = $weekStart->format("Y-m-d");
$endDate = $weekEnd->format("Y-m-d");

// Παίρνουμε τις παραμέτρους που προσδιορίζουν τον ιδιοκτήτη.
$ownerParameters = getOwnerParameters();

// Αναζητούμε τα ραντεβού της εβδομάδας μόνο για τον συγκεκριμένο ιδιοκτήτη.
$statement = $pdo->prepare(
    "SELECT * FROM appointments
     WHERE appointment_date BETWEEN :start_date AND :end_date
       AND " . getOwnerCondition() . "
     ORDER BY appointment_date ASC, start_time ASC"
);

// Συνδυάζουμε τις ημερομηνίες με τις παραμέτρους ιδιοκτησίας.
$statement->execute(array_merge([
    ":start_date" => $startDate,
    ":end_date" => $endDate
], $ownerParameters));

// Αποθηκεύουμε τα αποτελέσματα σε πίνακα.
$appointments = $statement->fetchAll();

// Αναζητούμε τις εργασίες που έχουν προγραμματιστεί για την επιλεγμένη εβδομάδα.
$statement = $pdo->prepare(
    "SELECT * FROM tasks
     WHERE scheduled_date BETWEEN :start_date AND :end_date
       AND start_time IS NOT NULL
       AND end_time IS NOT NULL
       AND " . getOwnerCondition() . "
     ORDER BY scheduled_date ASC, start_time ASC"
);

// Συνδυάζουμε τις ημερομηνίες με τις παραμέτρους ιδιοκτησίας.
$statement->execute(array_merge([
    ":start_date" => $startDate,
    ":end_date" => $endDate
], $ownerParameters));

// Αποθηκεύουμε τις προγραμματισμένες εργασίες.
$tasks = $statement->fetchAll();

// Δημιουργούμε έναν πίνακα για την οργάνωση των ραντεβού ανά ημερομηνία.
$appointmentsByDate = [];

// Διατρέχουμε όλα τα ραντεβού.
foreach ($appointments as $appointment) {

    // Τοποθετούμε κάθε ραντεβού κάτω από τη δική του ημερομηνία.
    $appointmentsByDate[$appointment["appointment_date"]][] = $appointment;
}

// Δημιουργούμε έναν πίνακα για την οργάνωση των εργασιών ανά ημερομηνία.
$tasksByDate = [];

// Διατρέχουμε όλες τις προγραμματισμένες εργασίες.
foreach ($tasks as $task) {

    // Τοποθετούμε κάθε εργασία κάτω από την ημερομηνία προγραμματισμού της.
    $tasksByDate[$task["scheduled_date"]][] = $task;
}

// Δημιουργούμε τους πίνακες με τα ονόματα των ημερών ανά γλώσσα.
$dayNamesByLanguage = [

    "el" => [
        1 => "Δευτέρα",
        2 => "Τρίτη",
        3 => "Τετάρτη",
        4 => "Πέμπτη",
        5 => "Παρασκευή",
        6 => "Σάββατο",
        7 => "Κυριακή"
    ],

    "en" => [
        1 => "Monday",
        2 => "Tuesday",
        3 => "Wednesday",
        4 => "Thursday",
        5 => "Friday",
        6 => "Saturday",
        7 => "Sunday"
    ],

    "es" => [
        1 => "Lunes",
        2 => "Martes",
        3 => "Miércoles",
        4 => "Jueves",
        5 => "Viernes",
        6 => "Sábado",
        7 => "Domingo"
    ]
];

// Δημιουργούμε τους πίνακες με τα ονόματα των μηνών ανά γλώσσα.
$monthNamesByLanguage = [

    "el" => [
        1 => "Ιανουαρίου",
        2 => "Φεβρουαρίου",
        3 => "Μαρτίου",
        4 => "Απριλίου",
        5 => "Μαΐου",
        6 => "Ιουνίου",
        7 => "Ιουλίου",
        8 => "Αυγούστου",
        9 => "Σεπτεμβρίου",
        10 => "Οκτωβρίου",
        11 => "Νοεμβρίου",
        12 => "Δεκεμβρίου"
    ],

    "en" => [
        1 => "January",
        2 => "February",
        3 => "March",
        4 => "April",
        5 => "May",
        6 => "June",
        7 => "July",
        8 => "August",
        9 => "September",
        10 => "October",
        11 => "November",
        12 => "December"
    ],

    "es" => [
        1 => "enero",
        2 => "febrero",
        3 => "marzo",
        4 => "abril",
        5 => "mayo",
        6 => "junio",
        7 => "julio",
        8 => "agosto",
        9 => "septiembre",
        10 => "octubre",
        11 => "noviembre",
        12 => "diciembre"
    ]
];

// Δημιουργούμε τα κείμενα του ημερολογίου στις τρεις γλώσσες.
$calendarTexts = [

    "el" => [
        "title" => "Εβδομαδιαίο ημερολόγιο",
        "previous" => "Προηγούμενη",
        "today" => "Σήμερα",
        "next" => "Επόμενη",
        "time" => "Ώρα",
        "empty" => "Δεν υπάρχουν ραντεβού ή προγραμματισμένες εργασίες για αυτή την εβδομάδα.",
        "tasks" => "Εργασίες",
        "appointments" => "Ραντεβού"
    ],

    "en" => [
        "title" => "Weekly Calendar",
        "previous" => "Previous",
        "today" => "Today",
        "next" => "Next",
        "time" => "Time",
        "empty" => "There are no appointments or scheduled tasks for this week.",
        "tasks" => "Tasks",
        "appointments" => "Appointments"
    ],

    "es" => [
        "title" => "Calendario semanal",
        "previous" => "Anterior",
        "today" => "Hoy",
        "next" => "Siguiente",
        "time" => "Hora",
        "empty" => "No hay citas ni tareas programadas para esta semana.",
        "tasks" => "Tareas",
        "appointments" => "Citas"
    ]
];

// Επιλέγουμε τους σωστούς πίνακες με βάση την υπάρχουσα γλώσσα.
$dayNames = $dayNamesByLanguage[$currentLanguage] ?? $dayNamesByLanguage["el"];
$monthNames = $monthNamesByLanguage[$currentLanguage] ?? $monthNamesByLanguage["el"];
$texts = $calendarTexts[$currentLanguage] ?? $calendarTexts["el"];

// Δημιουργούμε έναν πίνακα που θα περιέχει τις επτά ημέρες της εβδομάδας.
$days = [];

// Επαναλαμβάνουμε επτά φορές, μία για κάθε ημέρα.
for ($i = 0; $i < 7; $i++) {

    // Παίρνουμε την ημερομηνία της συγκεκριμένης ημέρας.
    $dayDate = clone $weekStart;
    $dayDate->modify("+".$i." days");

    // Μετατρέπουμε την ημερομηνία σε μορφή έτους-μήνα-ημέρας.
    $dateKey = $dayDate->format("Y-m-d");

    // Παίρνουμε τον αριθμό της ημέρας της εβδομάδας.
    $dayNumber = (int)$dayDate->format("N");

    // Αποθηκεύουμε τα στοιχεία της ημέρας.
    $days[] = [
        "date" => $dateKey,
        "name" => $dayNames[$dayNumber],
        "display_date" => $dayDate->format("d/m/Y"),
        "is_today" => $dateKey === $todayKey,
        "appointments" => $appointmentsByDate[$dateKey] ?? [],
        "tasks" => $tasksByDate[$dateKey] ?? []
    ];
}

// Δημιουργούμε μια συνάρτηση για τη μορφοποίηση ημερομηνιών ανά γλώσσα.
$formatLocalizedDate = function ($date) use ($monthNames, $currentLanguage) {

    // Παίρνουμε την ημέρα, τον μήνα και το έτος.
    $day = $date->format("j");
    $month = $monthNames[(int)$date->format("n")];
    $year = $date->format("Y");

    // Επιστρέφουμε την ημερομηνία στην κατάλληλη μορφή.
    if ($currentLanguage === "en") {
        return $month . " " . $day . ", " . $year;
    }

    if ($currentLanguage === "es") {
        return $day . " de " . $month . " de " . $year;
    }

    return $day . " " . $month . " " . $year;
};

// Δημιουργούμε την περιγραφή της επιλεγμένης εβδομάδας.
$weekLabel = $formatLocalizedDate($weekStart) .
             " – " .
             $formatLocalizedDate($weekEnd);

// Ορίζουμε την πρώτη ώρα του ημερολογίου σε λεπτά.
$visibleStartMinutes = 8 * 60;

// Ορίζουμε την τελευταία ώρα του ημερολογίου σε λεπτά.
$visibleEndMinutes = 20 * 60;

// Υπολογίζουμε το συνολικό ύψος του ημερολογίου.
$calendarHeight = $visibleEndMinutes - $visibleStartMinutes;

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLanguage, ENT_QUOTES, "UTF-8") ?>">

<head>

    <meta charset="UTF-8">
    <!-- Ορίζουμε την κωδικοποίηση χαρακτήρων. -->

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Προσαρμόζουμε το ημερολόγιο σε κινητά και υπολογιστές. -->

    <!-- Φορτώνουμε το κεντρικό αρχείο CSS. -->
    <link rel="stylesheet" href="assets/css/style.css">

    <title>TaskDate - <?= htmlspecialchars($texts["title"], ENT_QUOTES, "UTF-8") ?></title>
    <!-- Ορίζουμε τον τίτλο της σελίδας. -->

    <style>

        /* Βασικές ρυθμίσεις ολόκληρης της σελίδας. */
        * {
            box-sizing: border-box;
        }

        /* Ορίζουμε τη γενική εμφάνιση της σελίδας. */
        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f6f5fb;
            color: #29263a;
        }

        /* Κεντρικό περιεχόμενο. */
        .calendar-container {
            max-width: 1500px;
            margin: 0 auto;
        }

        /* Κεφαλίδα ημερολογίου. */
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }

        /* Τίτλος ημερολογίου. */
        .calendar-header h1 {
            margin: 0;
            color: #493c78;
        }

        /* Περιοχή κουμπιών πλοήγησης. */
        .calendar-navigation {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* Κοινή εμφάνιση συνδέσμων πλοήγησης. */
        .calendar-navigation a,
        .page-links a {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 8px;
            background: #6956a5;
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        /* Αλλάζουμε το χρώμα κατά το πέρασμα του ποντικιού. */
        .calendar-navigation a:hover,
        .page-links a:hover {
            background: #514080;
        }

        /* Εμφάνιση ημερομηνιών εβδομάδας. */
        .week-label {
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: bold;
            color: #514080;
        }

        /* Περιοχή οριζόντιας κύλισης. */
        .calendar-scroll {
            overflow-x: auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
        }

        /* Κύριο πλέγμα ημερολογίου. */
        .calendar-grid {
            display: grid;
            grid-template-columns: 75px repeat(7, minmax(145px, 1fr));
            min-width: 1100px;
        }

        /* Κοινή εμφάνιση κεφαλίδων. */
        .corner,
        .day-header {
            min-height: 65px;
            padding: 12px 5px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border-bottom: 1px solid #e6e3ef;
            background: #f9f8fd;
        }

        /* Κελί ένδειξης ώρας. */
        .corner {
            font-weight: bold;
            color: #756d89;
        }

        /* Όνομα ημέρας. */
        .day-name {
            font-weight: bold;
            margin-bottom: 6px;
        }

        /* Ημερομηνία ημέρας. */
        .day-date {
            font-size: 13px;
            color: #777187;
        }

        /* Ξεχωρίζουμε τη σημερινή ημέρα. */
        .today {
            background: #eee9fb;
            color: #59458e;
        }

        /* Περιοχή εμφάνισης ωρών. */
        .time-axis {
            position: relative;
            height: 720px;
            background: #faf9fd;
            border-right: 1px solid #e8e5ee;
        }

        /* Ετικέτες ωρών. */
        .time-label {
            position: absolute;
            left: 0;
            width: 100%;
            padding-right: 8px;
            text-align: right;
            font-size: 12px;
            color: #777187;
            transform: translateY(-50%);
        }

        /* Η τελευταία ετικέτα τοποθετείται στο κάτω άκρο. */
        .time-label.last {
            transform: translateY(-100%);
        }

        /* Στήλη ημέρας. */
        .day-column {
            position: relative;
            height: 720px;
            border-right: 1px solid #e8e5ee;
            background-color: #ffffff;

            /* Δημιουργούμε οριζόντιες γραμμές ανά ώρα. */
            background-image: repeating-linear-gradient(
                to bottom,
                transparent 0,
                transparent 59px,
                #e9e7f0 59px,
                #e9e7f0 60px
            );
        }

        /* Δίνουμε διαφορετικό φόντο στη σημερινή ημέρα. */
        .day-column.today {
            background-color: #fcfbff;
        }

        /* Κάρτα ραντεβού. */
        .appointment-card {
            position: absolute;
            left: 5px;
            right: 5px;
            display: block;
            padding: 5px;
            overflow: hidden;
            border-left: 4px solid #7861b7;
            border-radius: 5px;
            background: #eae4fb;
            color: #383052;
            text-decoration: none;
            font-size: 11px;
            line-height: 1.4;
        }

        /* Εμφάνιση κάρτας ραντεβού κατά το πέρασμα του ποντικιού. */
        .appointment-card:hover {
            background: #dcd1f7;
            z-index: 5;
        }

        /* Τίτλος ραντεβού. */
        .appointment-title {
            display: block;
            font-weight: bold;
            margin-bottom: 3px;
        }

        /* Κάρτα προγραμματισμένης εργασίας. */
        .task-calendar-card {
            position: absolute;
            left: 5px;
            right: 5px;
            display: block;
            padding: 5px;
            overflow: hidden;
            border-left: 4px solid #329b7a;
            border-radius: 5px;
            background: #e2f5ed;
            color: #244b40;
            text-decoration: none;
            font-size: 11px;
            line-height: 1.4;
        }

        /* Εμφάνιση κάρτας εργασίας κατά το πέρασμα του ποντικιού. */
        .task-calendar-card:hover {
            background: #ccebdd;
            z-index: 5;
        }

        /* Τίτλος εργασίας. */
        .task-calendar-title {
            display: block;
            font-weight: bold;
            margin-bottom: 3px;
        }

        /* Μήνυμα όταν δεν υπάρχουν ραντεβού ή εργασίες. */
        .empty-calendar {
            margin: 20px 0;
            color: #777187;
            text-align: center;
        }

        /* Σύνδεσμοι μετάβασης σε άλλες ενότητες. */
        .page-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        /* Προσαρμογές για μικρές οθόνες. */
        @media (max-width: 600px) {

            body {
                padding: 12px;
            }

            .calendar-header {
                align-items: flex-start;
            }

            .week-label {
                font-size: 15px;
            }
        }

    </style>

</head>

<body>

    <?php
    // Φορτώνουμε την κοινή μπάρα πλοήγησης.
    require_once __DIR__ . "/includes/navigation.php";
    ?>

    <!-- Κεντρικό πλαίσιο ημερολογίου. -->
    <main class="calendar-container">

        <div class="calendar-header">

            <!-- Τίτλος ημερολογίου. -->
            <h1>
                <?= htmlspecialchars($texts["title"], ENT_QUOTES, "UTF-8") ?>
            </h1>

            <!-- Κουμπιά πλοήγησης εβδομάδων. -->
            <div class="calendar-navigation">

                <!-- Μετάβαση στην προηγούμενη εβδομάδα. -->
                <a href="calendar.php?week=<?= htmlspecialchars($previousWeek->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
                    &laquo; <?= htmlspecialchars($texts["previous"], ENT_QUOTES, "UTF-8") ?>
                </a>

                <!-- Επιστροφή στην τρέχουσα εβδομάδα. -->
                <a href="calendar.php">
                    <?= htmlspecialchars($texts["today"], ENT_QUOTES, "UTF-8") ?>
                </a>

                <!-- Μετάβαση στην επόμενη εβδομάδα. -->
                <a href="calendar.php?week=<?= htmlspecialchars($nextWeek->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($texts["next"], ENT_QUOTES, "UTF-8") ?> &raquo;
                </a>

            </div>

        </div>

        <!-- Εμφανίζουμε την επιλεγμένη εβδομάδα. -->
        <p class="week-label">
            <?= htmlspecialchars($weekLabel, ENT_QUOTES, "UTF-8") ?>
        </p>

        <!-- Περιοχή οριζόντιας κύλισης. -->
        <div class="calendar-scroll">

            <!-- Δημιουργούμε το πλέγμα με τις επτά ημέρες. -->
            <div class="calendar-grid">

                <!-- Πρώτο κελί πάνω αριστερά. -->
                <div class="corner">
                    <?= htmlspecialchars($texts["time"], ENT_QUOTES, "UTF-8") ?>
                </div>

                <?php foreach ($days as $day) { ?>

                    <!-- Εμφανίζουμε την κεφαλίδα κάθε ημέρας. -->
                    <div class="day-header <?= $day["is_today"] ? "today" : "" ?>">

                        <span class="day-name">
                            <?= htmlspecialchars($day["name"], ENT_QUOTES, "UTF-8") ?>
                        </span>

                        <span class="day-date">
                            <?= htmlspecialchars($day["display_date"], ENT_QUOTES, "UTF-8") ?>
                        </span>

                    </div>

                <?php } ?>

                <!-- Αριστερή στήλη ωρών. -->
                <div class="time-axis">

                    <?php for ($hour = 8; $hour <= 20; $hour++) { ?>

                        <!-- Υπολογίζουμε τη θέση κάθε ώρας στο ημερολόγιο. -->
                        <div
                            class="time-label <?= $hour === 20 ? 'last' : '' ?>"
                            style="top: <?= ($hour - 8) * 60 ?>px;"
                        >
                            <?= sprintf("%02d:00", $hour) ?>
                        </div>

                    <?php } ?>

                </div>

                <?php foreach ($days as $day) { ?>

                    <!-- Δημιουργούμε τη στήλη της συγκεκριμένης ημέρας. -->
                    <div class="day-column <?= $day["is_today"] ? "today" : "" ?>">

                        <?php foreach ($day["appointments"] as $appointment) { ?>

                            <?php

                            // Παίρνουμε την ώρα έναρξης.
                            $startParts = explode(":", $appointment["start_time"]);

                            // Μετατρέπουμε την ώρα έναρξης σε λεπτά.
                            $appointmentStart = ((int)$startParts[0] * 60) + (int)$startParts[1];

                            // Παίρνουμε την ώρα λήξης.
                            $endParts = explode(":", $appointment["end_time"]);

                            // Μετατρέπουμε την ώρα λήξης σε λεπτά.
                            $appointmentEnd = ((int)$endParts[0] * 60) + (int)$endParts[1];

                            // Περιορίζουμε την εμφάνιση στο διάστημα 08:00–20:00.
                            $visibleStart = max($appointmentStart, $visibleStartMinutes);
                            $visibleEnd = min($appointmentEnd, $visibleEndMinutes);

                            // Ελέγχουμε αν το ραντεβού βρίσκεται μέσα στο ωράριο.
                            if ($visibleEnd > $visibleStart) {

                                // Υπολογίζουμε τη θέση της κάρτας από την αρχή της ημέρας.
                                $top = $visibleStart - $visibleStartMinutes;

                                // Υπολογίζουμε το ύψος της κάρτας σε λεπτά.
                                $height = max(20, $visibleEnd - $visibleStart);

                                // Κρατάμε το ID του ραντεβού.
                                $appointmentId = (int)$appointment["id"];

                            ?>

                                <!-- Δημιουργούμε κάρτα για το συγκεκριμένο ραντεβού. -->
                                <a
                                    class="appointment-card"
                                    href="appointments/edit.php?id=<?= $appointmentId ?>"
                                    style="top: <?= $top ?>px; height: <?= $height ?>px;"
                                    title="<?= htmlspecialchars($appointment["subject"], ENT_QUOTES, "UTF-8") ?>"
                                >

                                    <span class="appointment-title">
                                        <?= htmlspecialchars($appointment["subject"], ENT_QUOTES, "UTF-8") ?>
                                    </span>

                                    <span>
                                        <?= htmlspecialchars($appointment["person_name"], ENT_QUOTES, "UTF-8") ?>
                                    </span>

                                    <span>
                                        <?= htmlspecialchars(substr($appointment["start_time"], 0, 5), ENT_QUOTES, "UTF-8") ?>
                                        -
                                        <?= htmlspecialchars(substr($appointment["end_time"], 0, 5), ENT_QUOTES, "UTF-8") ?>
                                    </span>

                                </a>

                            <?php } ?>

                        <?php } ?>

                        <?php foreach ($day["tasks"] as $task) { ?>

                            <?php

                            // Παίρνουμε την ώρα έναρξης της εργασίας.
                            $taskStartParts = explode(":", $task["start_time"]);

                            // Μετατρέπουμε την ώρα έναρξης της εργασίας σε λεπτά.
                            $taskStart = ((int)$taskStartParts[0] * 60) + (int)$taskStartParts[1];

                            // Παίρνουμε την ώρα λήξης της εργασίας.
                            $taskEndParts = explode(":", $task["end_time"]);

                            // Μετατρέπουμε την ώρα λήξης της εργασίας σε λεπτά.
                            $taskEnd = ((int)$taskEndParts[0] * 60) + (int)$taskEndParts[1];

                            // Περιορίζουμε την εμφάνιση στο διάστημα 08:00–20:00.
                            $taskVisibleStart = max($taskStart, $visibleStartMinutes);
                            $taskVisibleEnd = min($taskEnd, $visibleEndMinutes);

                            // Ελέγχουμε αν η εργασία βρίσκεται μέσα στο ωράριο.
                            if ($taskVisibleEnd > $taskVisibleStart) {

                                // Υπολογίζουμε τη θέση της κάρτας εργασίας.
                                $taskTop = $taskVisibleStart - $visibleStartMinutes;

                                // Υπολογίζουμε το ύψος της κάρτας εργασίας.
                                $taskHeight = max(20, $taskVisibleEnd - $taskVisibleStart);

                                // Κρατάμε το ID της εργασίας.
                                $taskId = (int)$task["id"];

                            ?>

                                <!-- Δημιουργούμε κάρτα για τη συγκεκριμένη εργασία. -->
                                <a
                                    class="task-calendar-card"
                                    href="tasks/edit.php?id=<?= $taskId ?>"
                                    style="top: <?= $taskTop ?>px; height: <?= $taskHeight ?>px;"
                                    title="<?= htmlspecialchars($task["title"], ENT_QUOTES, "UTF-8") ?>"
                                >

                                    <!-- Εμφανίζουμε τον τίτλο της εργασίας. -->
                                    <span class="task-calendar-title">
                                        <?= htmlspecialchars($task["title"], ENT_QUOTES, "UTF-8") ?>
                                    </span>

                                    <!-- Εμφανίζουμε την ώρα έναρξης και λήξης. -->
                                    <span>
                                        <?= htmlspecialchars(substr($task["start_time"], 0, 5), ENT_QUOTES, "UTF-8") ?>
                                        -
                                        <?= htmlspecialchars(substr($task["end_time"], 0, 5), ENT_QUOTES, "UTF-8") ?>
                                    </span>

                                </a>

                            <?php } ?>

                        <?php } ?>

                    </div>

                <?php } ?>

            </div>

        </div>

        <?php if (empty($appointments) && empty($tasks)) { ?>

            <!-- Ενημερώνουμε τον χρήστη όταν δεν υπάρχουν ραντεβού ή εργασίες. -->
            <p class="empty-calendar">
                <?= htmlspecialchars($texts["empty"], ENT_QUOTES, "UTF-8") ?>
            </p>

        <?php } ?>

        <!-- Σύνδεσμοι προς τις υπόλοιπες ενότητες. -->
        <div class="page-links">

            <a href="tasks/index.php">
                <?= htmlspecialchars($texts["tasks"], ENT_QUOTES, "UTF-8") ?>
            </a>

            <a href="appointments/index.php">
                <?= htmlspecialchars($texts["appointments"], ENT_QUOTES, "UTF-8") ?>
            </a>

        </div>

    </main>

</body>
</html>
