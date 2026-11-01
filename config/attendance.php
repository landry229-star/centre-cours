<?php

return [
    // Au-delà de ce nombre de minutes après l'heure de début, un scan QR enregistre « retard ».
    'late_after_minutes' => (int) env('ATTENDANCE_LATE_AFTER_MINUTES', 10),
];
