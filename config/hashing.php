<?php

return [
    'driver' => 'bcrypt', // تغيير إلى 'argon' أو 'php' إذا كنت تستخدم خوارزمية أخرى
    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
    ],
    'argon' => [
        'memory' => 65536,
        'threads' => 1,
        'time' => 4,
    ],
];
