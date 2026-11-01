<?php

use App\Providers\AppServiceProvider;
use Illuminate\Validation\Rules\Password;

Password::defaults(
    fn () => Password::min(8)->letters()->numbers()
);

return [
    AppServiceProvider::class,
];
