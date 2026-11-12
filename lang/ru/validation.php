<?php

// Переводы текстов валидации: покрыты все правила, используемые
// формами приложения; полный набор laravel-lang не нужен.

return [
    'attributes' => [
        'name' => 'Имя',
        'email' => 'Email',
        'password' => 'Пароль',
        'password_confirmation' => 'Подтверждение пароля',
        'current_password' => 'Текущий пароль',
        'old_password' => 'Текущий пароль',
        'new_password' => 'Новый пароль',
        'locale' => 'Локаль интерфейса',
        'slug' => 'Адрес проекта',
        'title' => 'Название',
        'folder' => 'Папка',
        'preconditions' => 'Предусловия',
        'steps' => 'Шаги',
        'priority' => 'Приоритет',
        'status' => 'Статус',
        'per_page' => 'per_page',
        'external_ref' => 'external_ref',
        'repo_remote' => 'Репозиторий',
        'description' => 'Описание',
    ],

    'required' => 'Поле :attribute обязательно для заполнения.',
    'required_if' => 'Поле :attribute обязательно, когда :other имеет значение :value.',
    'string' => 'Поле :attribute должно быть строкой.',
    'email' => 'Поле :attribute должно быть корректным email-адресом.',
    'integer' => 'Поле :attribute должно быть целым числом.',
    'boolean' => 'Поле :attribute должно быть true или false.',
    'array' => 'Поле :attribute должно быть массивом.',
    'file' => 'Поле :attribute должно быть файлом.',
    'url' => 'Поле :attribute должно быть корректным URL.',
    'in' => 'Выбранное значение поля :attribute недопустимо.',
    'not_in' => 'Выбранное значение поля :attribute недопустимо.',
    'unique' => 'Такое значение поля :attribute уже существует.',
    'exists' => 'Выбранное значение поля :attribute некорректно.',
    'same' => 'Поле :attribute должно совпадать с :other.',
    'regex' => 'Формат поля :attribute некорректен.',
    'current_password' => 'Текущий пароль указан неверно.',
    'min' => [
        'numeric' => 'Поле :attribute должно быть не меньше :min.',
        'file' => 'Файл в поле :attribute должен быть не меньше :min КБ.',
        'string' => 'Поле :attribute должно быть не короче :min символов.',
        'array' => 'Поле :attribute должно содержать минимум :min элемента(ов).',
    ],
    'max' => [
        'numeric' => 'Поле :attribute не может быть больше :max.',
        'file' => 'Файл в поле :attribute не может быть больше :max КБ.',
        'string' => 'Поле :attribute не может быть длиннее :max символов.',
        'array' => 'Поле :attribute не может содержать больше :max элемента(ов).',
    ],
    'between' => [
        'numeric' => 'Поле :attribute должно быть между :min и :max.',
        'file' => 'Файл в поле :attribute должен быть от :min до :max КБ.',
        'string' => 'Поле :attribute должно быть от :min до :max символов.',
        'array' => 'Поле :attribute должно содержать от :min до :max элементов.',
    ],
];
