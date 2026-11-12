<?php

return [
    'brand' => 'Femida · setup',
    'title' => 'Setup · Femida',
    'steps' => [
        'checks' => 'Checks',
        'admin' => 'Admin account',
        'done' => 'Done',
    ],
    'checks' => [
        'php' => 'PHP 8.3+',
        'db' => 'Database is reachable',
        'storage' => 'Storage directory is writable',
        'key' => 'Application key generated',
    ],
    'admin_title' => 'Administrator account',
    'admin_hint' => 'This account can do everything: manage projects, users and API keys. The data stays on your server only.',
    'password_hint' => 'at least 8 characters',
    'locale_ru' => 'Русский',
    'locale_en' => 'English',
    'submit' => 'Create administrator and sign in',
    'note' => 'Step 2 of 3 · you will get into the system right away',
    'wizard_hint' => 'The wizard is shown until the first user exists',
    'done' => 'Welcome, :name! The administrator account has been created.',
    'attributes' => [
        'name' => 'Name',
        'email' => 'Email',
        'password' => 'Password',
        'password_confirmation' => 'Password confirmation',
        'locale' => 'Interface locale',
    ],
];
