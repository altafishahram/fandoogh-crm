<?php

declare(strict_types=1);

return ['fixed_code' => env('PUBLIC_AUTH_FIXED_CODE', '123456'), 'auth_mode' => env('PUBLIC_AUTH_MODE', 'fixed_code')];
