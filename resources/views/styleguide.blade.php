<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="femida">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Стильгайд — Femida</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200 text-base-content text-sm">

{{-- Шапка стильгайда --}}
<header class="sticky top-0 z-40 h-14 border-b border-(--fm-line) bg-base-100/90 backdrop-blur">
    <div class="flex h-full items-center gap-3 px-5">
        <span class="grid size-8 place-items-center rounded-lg bg-primary text-primary-content shadow-(--fm-shadow-sm)">
            <svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 4v17M5 21h14M12 6l-6 3 6-3 6 3M3.5 12a3.2 3.2 0 0 0 6.4 0L6.7 9zM14.1 12a3.2 3.2 0 0 0 6.4 0L17.3 9z"/></svg>
        </span>
        <div class="flex items-baseline gap-2">
            <span class="text-[15px] font-semibold tracking-tight">Femida</span>
            <span class="text-(--fm-muted)">стильгайд</span>
        </div>
        <span class="ml-auto badge badge-soft badge-neutral badge-sm font-medium">только dev-окружение</span>
    </div>
</header>

<div class="mx-auto max-w-6xl flex gap-10 px-6 py-8 lg:px-10">
    {{-- Оглавление --}}
    <nav class="sticky top-21 hidden h-fit w-52 shrink-0 flex-col gap-0.5 py-2 lg:flex">
        <p class="px-2.5 pb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-(--fm-subtle)">Разделы</p>
        <a href="#tokens" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-(--fm-ink-2) hover:bg-base-100 hover:text-base-content transition-colors"><i data-lucide="palette" class="size-4 text-(--fm-subtle)"></i><span>Токены</span></a>
        <a href="#typography" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-(--fm-ink-2) hover:bg-base-100 hover:text-base-content transition-colors"><i data-lucide="type" class="size-4 text-(--fm-subtle)"></i><span>Типографика</span></a>
        <a href="#buttons" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-(--fm-ink-2) hover:bg-base-100 hover:text-base-content transition-colors"><i data-lucide="rectangle-horizontal" class="size-4 text-(--fm-subtle)"></i><span>Кнопки</span></a>
        <a href="#forms" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-(--fm-ink-2) hover:bg-base-100 hover:text-base-content transition-colors"><i data-lucide="text-cursor-input" class="size-4 text-(--fm-subtle)"></i><span>Формы</span></a>
        <a href="#layout" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-(--fm-ink-2) hover:bg-base-100 hover:text-base-content transition-colors"><i data-lucide="panels-top-left" class="size-4 text-(--fm-subtle)"></i><span>Каркас</span></a>
        <a href="#badges" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-(--fm-ink-2) hover:bg-base-100 hover:text-base-content transition-colors"><i data-lucide="badge-check" class="size-4 text-(--fm-subtle)"></i><span>Бейджи и статусы</span></a>
        <a href="#cards" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-(--fm-ink-2) hover:bg-base-100 hover:text-base-content transition-colors"><i data-lucide="square" class="size-4 text-(--fm-subtle)"></i><span>Карточки</span></a>
        <a href="#entity-list" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-(--fm-ink-2) hover:bg-base-100 hover:text-base-content transition-colors"><i data-lucide="table" class="size-4 text-(--fm-subtle)"></i><span>Список сущностей</span></a>
        <a href="#principles" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-(--fm-ink-2) hover:bg-base-100 hover:text-base-content transition-colors"><i data-lucide="scale" class="size-4 text-(--fm-subtle)"></i><span>Принципы</span></a>
    </nav>

    {{-- Контент --}}
    <main class="min-w-0 grow">

        <div>
            <h1 class="text-[26px] font-semibold leading-tight tracking-tight">Стильгайд</h1>
            <p class="mt-1 text-(--fm-muted)">Живая витрина темы «Femida». Источник токенов — <code class="rounded bg-base-100 px-1.5 py-0.5 text-xs shadow-(--fm-shadow-xs)">resources/css/app.css</code>; новый UI-паттерн сначала показываем здесь.</p>
        </div>

        {{-- ===== ТОКЕНЫ ===== --}}
        <section id="tokens" class="mt-12 scroll-mt-20">
            <div class="flex items-center gap-3">
                <h2 class="text-sm font-semibold text-(--fm-ink-2)">Токены</h2>
                <span class="h-px grow bg-(--fm-line)"></span>
            </div>
            <p class="mt-1.5 text-(--fm-muted)">Палитра темы daisyUI и продуктовые переменные <code class="text-xs">--fm-*</code>. Цвета — только токенами, не сырыми значениями.</p>

            <h3 class="mt-6 text-xs font-semibold uppercase tracking-[0.08em] text-(--fm-subtle)">Палитра daisyUI</h3>
            <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach (['base-100' => 'поверхность', 'base-200' => 'заливка', 'base-300' => 'границы, сильнее', 'canvas' => 'фон страницы', 'primary' => 'акцент: индиго', 'secondary' => 'второй тон', 'neutral' => 'тёмный графит', 'info' => 'справочное', 'success' => 'готово', 'warning' => 'внимание', 'error' => 'опасное действие'] as $color => $hint)
                    <div class="overflow-hidden rounded-lg border border-(--fm-line) bg-base-100 shadow-(--fm-shadow-xs)">
                        <div class="h-11 bg-{{ $color }}"></div>
                        <div class="px-2.5 py-2">
                            <p class="font-medium">{{ $color }}</p>
                            <p class="mt-0.5 text-xs text-(--fm-muted)">{{ $hint }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <h3 class="mt-8 text-xs font-semibold uppercase tracking-[0.08em] text-(--fm-subtle)">Продуктовые: тексты и линии</h3>
            <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach (['--fm-ink-2' => 'второй текст', '--fm-muted' => 'пояснение', '--fm-subtle' => 'подпись/каптион', '--fm-line' => 'линия', '--fm-line-strong' => 'линия при hover', '--fm-row-hover' => 'строка таблицы'] as $token => $hint)
                    <div class="rounded-lg border border-(--fm-line) bg-base-100 p-3 shadow-(--fm-shadow-xs)">
                        <div class="h-8 rounded-md border border-(--fm-line)" style="background: var({{ $token }})"></div>
                        <p class="mt-2 font-mono text-xs font-medium">{{ $token }}</p>
                        <p class="mt-0.5 text-xs text-(--fm-muted)">{{ $hint }}</p>
                    </div>
                @endforeach
            </div>

            <h3 class="mt-8 text-xs font-semibold uppercase tracking-[0.08em] text-(--fm-subtle)">Продуктовые: акценты и градиент</h3>
            <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach (['--fm-primary-soft' => 'фон активного пункта', '--fm-primary-soft-2' => 'фон активной кнопки', '--fm-primary-ring' => 'ring фокуса', 'gradient' => 'фирменный градиент'] as $token => $hint)
                    <div class="rounded-lg border border-(--fm-line) bg-base-100 p-3 shadow-(--fm-shadow-xs)">
                        <div class="h-8 rounded-md border border-(--fm-line)" @if ($token === 'gradient') style="background: linear-gradient(135deg, var(--fm-grad-a), var(--fm-grad-b))" @else style="background: var({{ $token }})" @endif></div>
                        <p class="mt-2 font-mono text-xs font-medium">{{ $token }}</p>
                        <p class="mt-0.5 text-xs text-(--fm-muted)">{{ $hint }}</p>
                    </div>
                @endforeach
            </div>

            <h3 class="mt-8 text-xs font-semibold uppercase tracking-[0.08em] text-(--fm-subtle)">Тени и геометрия</h3>
            <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach (['--fm-shadow-xs' => 'поля, мелкое', '--fm-shadow-sm' => 'кнопки', '--fm-shadow-md' => 'карточки-секции', '--fm-shadow-lg' => 'дропдауны'] as $token => $hint)
                    <div class="rounded-box border border-(--fm-line) bg-base-100 p-3" style="box-shadow: var({{ $token }})">
                        <p class="font-mono text-xs font-medium">{{ $token }}</p>
                        <p class="mt-0.5 text-xs text-(--fm-muted)">{{ $hint }}</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-(--fm-muted)">Радиусы: <code class="font-mono">--radius-field</code> 0.5rem (поля), <code class="font-mono">--radius-box</code> 0.875rem (карточки <code>rounded-box</code>), <code class="font-mono">--radius-selector</code> 0.375rem.</p>
        </section>

        {{-- ===== ТИПОГРАФИКА ===== --}}
        <section id="typography" class="mt-12 scroll-mt-20">
            <div class="flex items-center gap-3">
                <h2 class="text-sm font-semibold text-(--fm-ink-2)">Типографика</h2>
                <span class="h-px grow bg-(--fm-line)"></span>
            </div>
            <p class="mt-1.5 text-(--fm-muted)">Golos Text (self-hosted, переменный, с кириллицей). Базовый размер страницы — <code class="text-xs">text-sm</code> (14px).</p>
            <div class="mt-4 space-y-4 rounded-box border border-(--fm-line) bg-base-100 p-5 shadow-(--fm-shadow-xs)">
                <div>
                    <p class="text-[26px] font-semibold leading-tight tracking-tight">Заголовок страницы — 26px, semibold, tracking-tight</p>
                    <p class="mt-1 text-(--fm-muted)">Пояснение под заголовком — muted, одна строка о смысле страницы.</p>
                </div>
                <div>
                    <p class="text-base font-semibold tracking-tight">Заголовок карточки — 16px</p>
                    <p class="mt-1 text-xs leading-relaxed text-(--fm-muted)">Описание в карточке — 12px, leading-relaxed, muted.</p>
                </div>
                <div class="flex flex-wrap items-baseline gap-x-6 gap-y-2 border-t border-(--fm-line) pt-4">
                    <span class="text-[11px] font-semibold uppercase tracking-[0.08em] text-(--fm-subtle)">Каптион секции</span>
                    <span class="text-xs text-(--fm-muted)">подпись-уточнение</span>
                    <span class="font-medium tabular-nums">1 234 <span class="font-normal text-(--fm-muted)">задачи</span></span>
                    <span class="text-xs font-medium tabular-nums text-primary">87%</span>
                </div>
                <p class="text-xs text-(--fm-subtle)">Числа — всегда <code class="font-mono">tabular-nums</code>; нижний колонтитул — subtle.</p>
            </div>
        </section>

        {{-- ===== КНОПКИ ===== --}}
        <section id="buttons" class="mt-12 scroll-mt-20">
            <div class="flex items-center gap-3">
                <h2 class="text-sm font-semibold text-(--fm-ink-2)">Кнопки</h2>
                <span class="h-px grow bg-(--fm-line)"></span>
            </div>
            <p class="mt-1.5 text-(--fm-muted)">Стандарт страницы — <code class="text-xs">btn-sm h-9</code>. Главная — не более одной на экран.</p>
            <div class="mt-4 flex flex-wrap items-center gap-3 rounded-box border border-(--fm-line) bg-base-100 p-5 shadow-(--fm-shadow-xs)">
                <button class="btn btn-primary btn-sm h-9 gap-2 px-4 font-medium shadow-(--fm-shadow-sm)">
                    <i data-lucide="plus" class="size-4"></i>Создать задачу
                </button>
                <button class="btn btn-sm h-9 gap-2 border-(--fm-line) bg-base-100 font-medium shadow-(--fm-shadow-xs) hover:border-(--fm-line-strong) hover:bg-base-200">
                    <i data-lucide="upload" class="size-4 text-(--fm-muted)"></i>Импорт
                </button>
                <button class="btn btn-ghost btn-sm h-9 gap-1.5 font-normal text-(--fm-ink-2)">
                    <i data-lucide="rotate-ccw" class="size-3.5"></i>Сбросить
                </button>
                <button class="btn btn-ghost btn-sm btn-square h-8 w-8 text-(--fm-muted) hover:text-base-content" aria-label="Действия">
                    <i data-lucide="ellipsis" class="size-4"></i>
                </button>
                <button class="btn btn-ghost btn-sm h-8 gap-1.5 font-medium text-error hover:bg-error/10">
                    <i data-lucide="trash-2" class="size-4"></i>Удалить
                </button>
                <button class="btn btn-sm h-9 border-(--fm-line) bg-base-100 font-medium shadow-(--fm-shadow-xs)" disabled>Недоступно</button>
                <button class="btn btn-primary btn-sm h-9" disabled>Сохранить</button>
            </div>
        </section>

        {{-- ===== ФОРМЫ ===== --}}
        <section id="forms" class="mt-12 scroll-mt-20">
            <div class="flex items-center gap-3">
                <h2 class="text-sm font-semibold text-(--fm-ink-2)">Формы</h2>
                <span class="h-px grow bg-(--fm-line)"></span>
            </div>
            <p class="mt-1.5 text-(--fm-muted)">Поле — <code class="text-xs">input h-10</code> с иконкой слева; фокус — ring токеном <code class="text-xs">--fm-primary-ring</code>. Текст ошибки — <code class="text-xs">text-error</code> под полем.</p>
            <div class="mt-4 grid gap-6 rounded-box border border-(--fm-line) bg-base-100 p-5 shadow-(--fm-shadow-xs) md:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="sg-email" class="text-xs font-medium text-(--fm-ink-2)">E-mail</label>
                    <label class="input h-10 border-(--fm-line) bg-base-100 shadow-(--fm-shadow-xs) focus-within:border-primary focus-within:outline-0 focus-within:ring-3 focus-within:ring-(--fm-primary-ring)">
                        <i data-lucide="mail" class="size-4 text-(--fm-subtle)"></i>
                        <input id="sg-email" type="email" class="grow" placeholder="imya@company.ru" autocomplete="username">
                    </label>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="sg-wrong" class="text-xs font-medium text-(--fm-ink-2)">Поле с ошибкой</label>
                    <label class="input h-10 border-error bg-base-100 shadow-(--fm-shadow-xs)">
                        <i data-lucide="lock" class="size-4 text-(--fm-subtle)"></i>
                        <input id="sg-wrong" type="password" class="grow" value="short">
                    </label>
                    <p class="text-xs text-error">Пароль должен быть не короче 8 символов.</p>
                </div>
                <label class="input input-sm h-9 w-full max-w-80 border-(--fm-line) shadow-(--fm-shadow-xs) focus-within:border-primary focus-within:outline-0 focus-within:ring-3 focus-within:ring-(--fm-primary-ring)">
                    <i data-lucide="search" class="size-4 text-(--fm-subtle)"></i>
                    <input type="search" class="grow" placeholder="Название или номер">
                </label>
                <div class="flex flex-wrap items-center gap-6">
                    <label class="flex items-center gap-2 text-(--fm-muted)">
                        На странице
                        <select class="select select-sm h-8 w-20 border-(--fm-line) tabular-nums shadow-(--fm-shadow-xs)">
                            <option selected>25</option>
                        </select>
                    </label>
                    <label class="flex items-center gap-2.5">
                        <input type="checkbox" class="checkbox checkbox-sm checkbox-primary rounded-[5px]" checked>
                        <span class="text-(--fm-ink-2)">Чекбокс строки</span>
                    </label>
                    <label class="flex items-center gap-2.5">
                        <input type="checkbox" class="toggle toggle-primary toggle-sm" checked>
                        <span class="text-(--fm-ink-2)">Тумблер модуля</span>
                    </label>
                </div>
            </div>
        </section>

        {{-- ===== КАРКАС ===== --}}
        <section id="layout" class="mt-12 scroll-mt-20">
            <div class="flex items-center gap-3">
                <h2 class="text-sm font-semibold text-(--fm-ink-2)">Каркас</h2>
                <span class="h-px grow bg-(--fm-line)"></span>
            </div>
            <p class="mt-1.5 text-(--fm-muted)">Шапка h-14 sticky (навигация модулей + проект + пользователь), под ней: тёмная левая панель <code class="font-mono text-xs">.app-sidebar</code> w-62 и контент. Активный элемент навигации — и таб шапки, и пункт панели — фирменный градиент с белым текстом (вид задаёт CSS по <code class="font-mono">aria-current="page"</code>); в дропдаунах активный пункт — светлая плашка. Дропдауны — на focus/tabindex, без JS.</p>

            <h3 class="mt-6 text-xs font-semibold uppercase tracking-[0.08em] text-(--fm-subtle)">Строка шапки</h3>
            <div class="mt-2 overflow-x-auto rounded-box border border-(--fm-line) bg-base-100 shadow-(--fm-shadow-xs)">
                <header class="flex h-14 min-w-[760px] items-center gap-6 px-5">
                    <span class="flex items-center gap-2.5">
                        <span class="grid size-8 place-items-center rounded-lg bg-primary text-primary-content shadow-(--fm-shadow-sm)"><svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 4v17M5 21h14M12 6l-6 3 6-3 6 3M3.5 12a3.2 3.2 0 0 0 6.4 0L6.7 9zM14.1 12a3.2 3.2 0 0 0 6.4 0L17.3 9z"/></svg></span>
                        <span class="text-[15px] font-semibold tracking-tight">Femida</span>
                    </span>
                    <nav class="flex items-center gap-0.5">
                        <a class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-(--fm-ink-2) hover:bg-base-200 hover:text-base-content transition-colors"><i data-lucide="layout-dashboard" class="size-4 text-(--fm-subtle)"></i>Дашборд</a>
                        <a aria-current="page" class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 font-medium"><i data-lucide="list-todo" class="size-4"></i>Бэклог</a>
                        <a class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-(--fm-ink-2) hover:bg-base-200 hover:text-base-content transition-colors"><i data-lucide="code" class="size-4 text-(--fm-subtle)"></i>Разработка</a>
                        <a class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-(--fm-ink-2) hover:bg-base-200 hover:text-base-content transition-colors"><i data-lucide="flask-conical" class="size-4 text-(--fm-subtle)"></i>QA</a>
                    </nav>
                    <div class="ml-auto flex items-center gap-2">
                        <div class="dropdown dropdown-end">
                            <div tabindex="0" role="button" class="flex items-center gap-2 rounded-lg border border-(--fm-line) bg-base-100 py-1 pl-1 pr-2.5 shadow-(--fm-shadow-xs) hover:border-(--fm-line-strong) cursor-pointer transition-colors">
                                <span class="grid size-6 place-items-center rounded-md bg-(--fm-primary-soft-2) text-[11px] font-semibold text-primary">F</span>
                                <span class="max-w-48 truncate font-medium">Проект «Фемида»</span>
                                <i data-lucide="chevrons-up-down" class="size-3.5 text-(--fm-subtle)"></i>
                            </div>
                            <ul tabindex="0" class="dropdown-content menu z-30 mt-2 w-72 rounded-box border border-(--fm-line) bg-base-100 p-1.5 shadow-(--fm-shadow-lg)">
                                <li class="menu-title text-xs font-medium text-(--fm-muted)">Проекты</li>
                                <li><a class="justify-between"><span class="flex items-center gap-2"><span class="grid size-5 place-items-center rounded bg-(--fm-primary-soft-2) text-[10px] font-semibold text-primary">F</span>Проект «Фемида»</span><i data-lucide="check" class="size-4 text-primary"></i></a></li>
                                <li><a><span class="grid size-5 place-items-center rounded bg-base-300 text-[10px] font-semibold">H</span>Проект «Харон»</a></li>
                                <li aria-hidden="true" class="pointer-events-none my-1 border-t border-(--fm-line)"></li>
                                <li><a class="text-(--fm-ink-2)"><i data-lucide="settings" class="size-4"></i>Управление проектами</a></li>
                            </ul>
                        </div>
                        <button class="btn btn-ghost btn-sm btn-square relative text-(--fm-ink-2)" aria-label="Уведомления">
                            <i data-lucide="bell" class="size-4.5"></i>
                            <span class="absolute right-1.5 top-1.5 size-2 rounded-full bg-error ring-2 ring-base-100"></span>
                        </button>
                        <div class="dropdown dropdown-end">
                            <div tabindex="0" role="button" class="flex items-center gap-2 rounded-lg py-1 pl-1 pr-2 hover:bg-base-200 cursor-pointer transition-colors">
                                <div class="avatar avatar-placeholder"><div class="w-7 rounded-full bg-neutral text-neutral-content"><span class="text-[11px] font-semibold">ЕМ</span></div></div>
                                <span class="font-medium">Елена Морозова</span>
                                <i data-lucide="chevron-down" class="size-3.5 text-(--fm-subtle)"></i>
                            </div>
                            <ul tabindex="0" class="dropdown-content menu z-30 mt-2 w-60 rounded-box border border-(--fm-line) bg-base-100 p-1.5 shadow-(--fm-shadow-lg)">
                                <li class="pointer-events-none px-3 py-2">
                                    <div class="flex flex-col items-start gap-0 p-0">
                                        <span class="font-medium">Елена Морозова</span>
                                        <span class="text-xs text-(--fm-muted)">Тестировщик</span>
                                    </div>
                                </li>
                                <li aria-hidden="true" class="pointer-events-none my-1 border-t border-(--fm-line)"></li>
                                <li><a><i data-lucide="user" class="size-4"></i>Профиль</a></li>
                                <li><a><i data-lucide="settings" class="size-4"></i>Настройки</a></li>
                                <li aria-hidden="true" class="pointer-events-none my-1 border-t border-(--fm-line)"></li>
                                <li><a class="text-error"><i data-lucide="log-out" class="size-4"></i>Выйти</a></li>
                            </ul>
                        </div>
                    </div>
                </header>
            </div>

            <h3 class="mt-8 text-xs font-semibold uppercase tracking-[0.08em] text-(--fm-subtle)">Левая панель (тёмная)</h3>
            <div class="mt-2 w-62 overflow-hidden rounded-box border border-(--fm-line) shadow-(--fm-shadow-xs)">
                <aside class="app-sidebar flex h-80 w-full flex-col">
                    <div class="px-4 pt-5 pb-2">
                        <p class="px-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-(--fm-subtle)">Раздел</p>
                        <p class="px-2 mt-1 text-[15px] font-semibold tracking-tight">Бэклог</p>
                    </div>
                    <nav class="flex flex-col gap-0.5 px-3 py-2">
                        <a class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-(--fm-ink-2) hover:bg-(--fm-row-hover) hover:text-base-content transition-colors"><i data-lucide="layers" class="size-4 text-(--fm-subtle)"></i><span>Все задачи</span></a>
                        <a aria-current="page" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 font-medium"><i data-lucide="package" class="size-4"></i><span>Спринт 12</span></a>
                        <a class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-(--fm-ink-2) hover:bg-(--fm-row-hover) hover:text-base-content transition-colors"><i data-lucide="calendar-days" class="size-4 text-(--fm-subtle)"></i><span>Дедлайны</span><span class="ml-auto text-xs tabular-nums text-(--fm-subtle)">4</span></a>
                    </nav>
                    <div class="mt-auto p-4">
                        <div class="rounded-box border border-(--fm-line) bg-base-200 p-4">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-medium text-(--fm-ink-2)">Закрыто в спринте</p>
                                <span class="text-xs font-semibold tabular-nums">67%</span>
                            </div>
                            <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-base-300">
                                <div class="h-full w-[67%] rounded-full bg-primary"></div>
                            </div>
                            <p class="mt-2 text-xs tabular-nums text-(--fm-muted)">12 из 18 задач</p>
                            <a class="mt-3 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline underline-offset-2">Открыть доску <i data-lucide="arrow-right" class="size-3.5"></i></a>
                        </div>
                    </div>
                </aside>
            </div>
            <p class="mt-3 text-xs text-(--fm-muted)">Активный пункт — <code class="font-mono">aria-current="page"</code> (ссылка или span): вид задаёт CSS — фирменный градиент, белый текст. Классы подсветки в разметке не нужны; шкала панели — своя тёмная (класс <code class="font-mono">.app-sidebar</code> в app.css).</p>
        </section>

        {{-- ===== БЕЙДЖИ ===== --}}
        <section id="badges" class="mt-12 scroll-mt-20">
            <div class="flex items-center gap-3">
                <h2 class="text-sm font-semibold text-(--fm-ink-2)">Бейджи и статусы</h2>
                <span class="h-px grow bg-(--fm-line)"></span>
            </div>
            <p class="mt-1.5 text-(--fm-muted)">Статусы задач — <code class="text-xs">badge-soft</code> с точкой-индикатором; недоступность — ghost-бейдж с иконкой замка.</p>
            <div class="mt-4 flex flex-wrap items-center gap-3 rounded-box border border-(--fm-line) bg-base-100 p-5 shadow-(--fm-shadow-xs)">
                <span class="badge badge-soft badge-info badge-sm gap-1.5 font-medium"><span class="size-1.5 rounded-full bg-info"></span>В работе</span>
                <span class="badge badge-soft badge-warning badge-sm gap-1.5 font-medium"><span class="size-1.5 rounded-full bg-warning"></span>На ревью</span>
                <span class="badge badge-soft badge-success badge-sm gap-1.5 font-medium"><span class="size-1.5 rounded-full bg-success"></span>Готово</span>
                <span class="badge badge-soft badge-error badge-sm gap-1.5 font-medium"><span class="size-1.5 rounded-full bg-error"></span>Заблокирована</span>
                <span class="badge badge-ghost badge-sm gap-1.5 font-medium text-(--fm-muted)"><i data-lucide="lock" class="size-3"></i>Модуль не предоставлен</span>
            </div>
        </section>

        {{-- ===== КАРТОЧКИ ===== --}}
        <section id="cards" class="mt-12 scroll-mt-20">
            <div class="flex items-center gap-3">
                <h2 class="text-sm font-semibold text-(--fm-ink-2)">Карточки</h2>
                <span class="h-px grow bg-(--fm-line)"></span>
            </div>
            <p class="mt-1.5 text-(--fm-muted)">Карточка — <code class="text-xs">rounded-box</code> + <code class="text-xs">--fm-shadow-xs</code> на фоне base-100. Недоступное — приглушить <code class="text-xs">opacity-70</code>, не раскрашивать.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div class="flex flex-col rounded-box border border-(--fm-line) bg-base-100 p-5 shadow-(--fm-shadow-xs)">
                    <div class="flex items-start justify-between">
                        <span class="grid size-11 place-items-center rounded-lg border border-(--fm-line) bg-base-200 text-(--fm-ink-2)"><i data-lucide="list-todo" class="size-5"></i></span>
                        <span class="badge badge-soft badge-success badge-sm gap-1.5 font-medium"><span class="size-1.5 rounded-full bg-success"></span>Включён</span>
                    </div>
                    <p class="mt-4 font-semibold tracking-tight">Бэклог</p>
                    <p class="mt-1 grow text-xs leading-relaxed text-(--fm-muted)">Пул задач проекта, приоритеты и связи. Колонка «Бэклог» на доске.</p>
                    <div class="mt-4 flex items-center justify-between border-t border-(--fm-line) pt-4">
                        <span class="text-xs text-(--fm-subtle)">Включён</span>
                        <input type="checkbox" class="toggle toggle-primary toggle-sm" checked aria-label="Включить модуль «Бэклог»">
                    </div>
                </div>
                <div class="flex flex-col rounded-box border border-(--fm-line) bg-base-100 p-5 opacity-70 shadow-(--fm-shadow-xs)">
                    <div class="flex items-start justify-between">
                        <span class="grid size-11 place-items-center rounded-lg border border-(--fm-line) bg-base-200 text-(--fm-subtle)"><i data-lucide="git-pull-request" class="size-5"></i></span>
                        <span class="badge badge-ghost badge-sm gap-1.5 font-medium text-(--fm-muted)"><i data-lucide="lock" class="size-3"></i>Не предоставлен</span>
                    </div>
                    <p class="mt-4 font-semibold tracking-tight">Ревью</p>
                    <p class="mt-1 grow text-xs leading-relaxed text-(--fm-muted)">Ревью кода: замечания, статусы, связь с задачей.</p>
                    <div class="mt-4 flex items-center justify-between border-t border-(--fm-line) pt-4">
                        <span class="text-xs text-(--fm-subtle)">Запросите у администратора</span>
                        <input type="checkbox" class="toggle toggle-sm" disabled aria-label="Модуль «Ревью» не предоставлен">
                    </div>
                </div>
                <div class="flex flex-col items-center rounded-box border border-dashed border-(--fm-line-strong) bg-base-100 px-6 py-12 shadow-(--fm-shadow-md)">
                    <div class="grid size-13 place-items-center rounded-xl bg-(--fm-primary-soft)"><i data-lucide="layout-template" class="size-6 text-primary"></i></div>
                    <h3 class="mt-5 text-base font-semibold tracking-tight">Здесь пока пусто</h3>
                    <p class="mt-1.5 max-w-xs text-center text-(--fm-muted)">Пустое состояние — пунктирная рамка, иконка в soft-круге, одна кнопка-действие.</p>
                    <a class="mt-6 btn btn-primary btn-sm h-9 gap-2 px-4 font-medium shadow-(--fm-shadow-sm)">Начать <i data-lucide="arrow-right" class="size-4"></i></a>
                </div>
            </div>
            <p class="mt-4 flex items-center gap-1.5 text-xs text-(--fm-subtle)">
                <i data-lucide="circle-check" class="size-3.5"></i>
                Подсказка-приписка после блока карточек — subtle, с иконкой.
            </p>
        </section>

        {{-- ===== СПИСОК СУЩНОСТЕЙ ===== --}}
        <section id="entity-list" class="mt-12 scroll-mt-20">
            <div class="flex items-center gap-3">
                <h2 class="text-sm font-semibold text-(--fm-ink-2)">Список сущностей</h2>
                <span class="h-px grow bg-(--fm-line)"></span>
            </div>
            <p class="mt-1.5 text-(--fm-muted)">Паттерн страницы раздела: заголовок с действиями → карточка-таблица (тулбар → массовые действия → таблица → пагинация).</p>

            <div class="mt-4">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-1.5 text-xs text-(--fm-muted)">
                            <a class="hover:text-base-content" href="#entity-list">Бэклог</a>
                            <i data-lucide="chevron-right" class="size-3.5 text-(--fm-subtle)"></i>
                            <span class="text-(--fm-ink-2)">Все задачи</span>
                        </div>
                        <h3 class="mt-1.5 text-[26px] font-semibold leading-tight tracking-tight">Задачи</h3>
                        <p class="mt-1 text-(--fm-muted)">Пул проекта: <span class="tabular-nums">41</span> открытая, <span class="tabular-nums">6</span> заблокированы.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button class="btn btn-sm h-9 gap-2 border-(--fm-line) bg-base-100 font-medium shadow-(--fm-shadow-xs) hover:border-(--fm-line-strong) hover:bg-base-200"><i data-lucide="upload" class="size-4 text-(--fm-muted)"></i>Импорт</button>
                        <button class="btn btn-primary btn-sm h-9 gap-2 px-4 font-medium shadow-(--fm-shadow-sm)"><i data-lucide="plus" class="size-4"></i>Создать задачу</button>
                    </div>
                </div>

                <div class="mt-6 rounded-box border border-(--fm-line) bg-base-100 shadow-(--fm-shadow-md)">
                    {{-- тулбар --}}
                    <div class="flex flex-wrap items-center gap-3 border-b border-(--fm-line) px-4 py-3">
                        <label class="input input-sm h-9 w-64 border-(--fm-line) shadow-(--fm-shadow-xs) focus-within:border-primary focus-within:outline-0 focus-within:ring-3 focus-within:ring-(--fm-primary-ring)">
                            <i data-lucide="search" class="size-4 text-(--fm-subtle)"></i>
                            <input type="search" class="grow" placeholder="Название или номер">
                        </label>
                        <div class="dropdown">
                            <div tabindex="0" role="button" class="btn btn-sm h-9 gap-2 border-(--fm-line) bg-base-100 font-normal shadow-(--fm-shadow-xs) hover:border-(--fm-line-strong) hover:bg-base-200">
                                <i data-lucide="tag" class="size-4 text-(--fm-muted)"></i>
                                <span class="text-(--fm-ink-2)">Статус:</span>
                                <span class="font-medium">Все</span>
                                <i data-lucide="chevron-down" class="size-3.5 text-(--fm-subtle)"></i>
                            </div>
                            <ul tabindex="0" class="dropdown-content menu z-30 mt-2 w-64 rounded-box border border-(--fm-line) bg-base-100 p-1.5 shadow-(--fm-shadow-lg)">
                                <li><a class="justify-between">Все статусы <i data-lucide="check" class="size-4 text-primary"></i></a></li>
                                <li aria-hidden="true" class="pointer-events-none my-1 border-t border-(--fm-line)"></li>
                                <li><a>Открытые</a></li>
                                <li><a>В работе</a></li>
                                <li><a>На ревью</a></li>
                                <li><a>Готово</a></li>
                            </ul>
                        </div>
                        <div role="tablist" class="inline-flex items-center gap-0.5 rounded-lg border border-(--fm-line) bg-base-200 p-0.5">
                            <span class="inline-flex h-7.5 items-center gap-1.5 rounded-md bg-base-100 px-3 font-medium shadow-(--fm-shadow-sm)">Все <span class="tabular-nums text-xs text-(--fm-muted)">47</span></span>
                            <span class="inline-flex h-7.5 cursor-pointer items-center gap-1.5 rounded-md px-3 text-(--fm-ink-2) transition-colors hover:text-base-content">Открытые <span class="tabular-nums text-xs text-(--fm-subtle)">41</span></span>
                            <span class="inline-flex h-7.5 cursor-pointer items-center gap-1.5 rounded-md px-3 text-(--fm-ink-2) transition-colors hover:text-base-content">Закрытые <span class="tabular-nums text-xs text-(--fm-subtle)">6</span></span>
                        </div>
                        <div class="ml-auto flex items-center gap-3">
                            <span class="text-(--fm-muted)">Найдено: <span class="font-medium tabular-nums text-base-content">47</span></span>
                            <button class="btn btn-ghost btn-sm h-8 gap-1.5 font-normal text-(--fm-ink-2)"><i data-lucide="rotate-ccw" class="size-3.5"></i>Сбросить</button>
                        </div>
                    </div>

                    {{-- массовые действия (видны при выборе) --}}
                    <div class="flex flex-wrap items-center gap-2 border-b border-(--fm-line) bg-(--fm-primary-soft) px-4 py-2">
                        <span class="inline-flex items-center gap-2 pr-2 font-medium text-primary">
                            <span class="grid size-5 place-items-center rounded bg-primary text-primary-content"><i data-lucide="check" class="size-3.5"></i></span>
                            Выбрано <span class="tabular-nums">2</span>
                        </span>
                        <span class="h-4 w-px bg-(--fm-primary-soft-2)"></span>
                        <button class="btn btn-ghost btn-sm h-8 gap-1.5 font-medium text-(--fm-ink-2) hover:bg-(--fm-primary-soft-2)"><i data-lucide="tag" class="size-4"></i>Изменить статус</button>
                        <span class="text-(--fm-subtle)">·</span>
                        <button class="btn btn-ghost btn-sm h-8 gap-1.5 font-medium text-(--fm-ink-2) hover:bg-(--fm-primary-soft-2)"><i data-lucide="user" class="size-4"></i>Назначить</button>
                        <span class="text-(--fm-subtle)">·</span>
                        <button class="btn btn-ghost btn-sm h-8 gap-1.5 font-medium text-error hover:bg-error/10"><i data-lucide="trash-2" class="size-4"></i>Удалить</button>
                        <button class="btn btn-ghost btn-sm h-8 ml-auto gap-1.5 font-normal text-(--fm-muted) hover:bg-(--fm-primary-soft-2)">Снять выделение <i data-lucide="x" class="size-3.5"></i></button>
                    </div>

                    {{-- таблица --}}
                    <div class="overflow-x-auto">
                        <table class="table min-w-[880px]">
                            <thead>
                                <tr class="border-b border-(--fm-line) text-xs">
                                    <th class="w-12 pl-4"><input type="checkbox" class="checkbox checkbox-sm checkbox-primary rounded-[5px]" aria-label="Выбрать все"></th>
                                    <th class="font-medium text-(--fm-muted)"><span class="inline-flex items-center gap-1">Задача <i data-lucide="chevrons-up-down" class="size-3.5 opacity-50"></i></span></th>
                                    <th class="font-medium text-(--fm-muted)">Исполнитель</th>
                                    <th class="font-medium text-(--fm-ink-2)"><span class="inline-flex items-center gap-1">Обновлена <i data-lucide="arrow-down" class="size-3.5 text-primary"></i></span></th>
                                    <th class="font-medium text-(--fm-muted)">Статус</th>
                                    <th class="w-14"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b border-(--fm-line) transition-colors hover:bg-(--fm-row-hover)">
                                    <td class="pl-4"><input type="checkbox" class="checkbox checkbox-sm checkbox-primary rounded-[5px]" checked aria-label="Выбрать FEM-128"></td>
                                    <td class="py-3">
                                        <div class="min-w-0">
                                            <a class="block truncate font-medium hover:text-primary" href="#entity-list">Канбан-доска: перенос карт между колонками</a>
                                            <span class="text-xs tabular-nums text-(--fm-muted)">FEM-128 · спринт 12</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <div class="avatar avatar-placeholder"><div class="w-6 rounded-full bg-neutral text-neutral-content"><span class="text-[10px] font-semibold">ЕМ</span></div></div>
                                            <span class="text-(--fm-ink-2)">Елена М.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="tabular-nums">24.11.2026</div>
                                        <div class="text-xs text-(--fm-muted)">сегодня</div>
                                    </td>
                                    <td><span class="badge badge-soft badge-info badge-sm gap-1.5 font-medium"><span class="size-1.5 rounded-full bg-info"></span>В работе</span></td>
                                    <td class="pr-4 text-right">
                                        <div class="dropdown dropdown-end">
                                            <div tabindex="0" role="button" class="btn btn-ghost btn-sm btn-square h-8 w-8 text-(--fm-muted) hover:text-base-content" aria-label="Действия"><i data-lucide="ellipsis" class="size-4"></i></div>
                                            <ul tabindex="0" class="dropdown-content menu z-30 my-1 w-60 rounded-box border border-(--fm-line) bg-base-100 p-1.5 text-left shadow-(--fm-shadow-lg)">
                                                <li><a><i data-lucide="eye" class="size-4"></i>Открыть карточку</a></li>
                                                <li><a><i data-lucide="pencil" class="size-4"></i>Редактировать</a></li>
                                                <li aria-hidden="true" class="pointer-events-none my-1 border-t border-(--fm-line)"></li>
                                                <li><a class="text-error"><i data-lucide="trash-2" class="size-4"></i>Удалить</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="border-b border-(--fm-line) bg-(--fm-primary-soft) transition-colors hover:bg-(--fm-primary-soft-2)">
                                    <td class="pl-4"><input type="checkbox" class="checkbox checkbox-sm checkbox-primary rounded-[5px]" checked aria-label="Выбрать FEM-126"></td>
                                    <td class="py-3">
                                        <div class="min-w-0">
                                            <a class="block truncate font-medium hover:text-primary" href="#entity-list">Модульный каркас: реестр и дескрипторы модулей</a>
                                            <span class="text-xs tabular-nums text-(--fm-muted)">FEM-126 · спринт 12</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <div class="avatar avatar-placeholder"><div class="w-6 rounded-full bg-neutral text-neutral-content"><span class="text-[10px] font-semibold">ИС</span></div></div>
                                            <span class="text-(--fm-ink-2)">Игорь С.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="tabular-nums">24.11.2026</div>
                                        <div class="text-xs text-(--fm-muted)">сегодня</div>
                                    </td>
                                    <td><span class="badge badge-soft badge-warning badge-sm gap-1.5 font-medium"><span class="size-1.5 rounded-full bg-warning"></span>На ревью</span></td>
                                    <td class="pr-4 text-right">
                                        <div class="dropdown dropdown-end">
                                            <div tabindex="0" role="button" class="btn btn-ghost btn-sm btn-square h-8 w-8 text-(--fm-muted) hover:text-base-content" aria-label="Действия"><i data-lucide="ellipsis" class="size-4"></i></div>
                                            <ul tabindex="0" class="dropdown-content menu z-30 my-1 w-60 rounded-box border border-(--fm-line) bg-base-100 p-1.5 text-left shadow-(--fm-shadow-lg)">
                                                <li><a><i data-lucide="eye" class="size-4"></i>Открыть карточку</a></li>
                                                <li><a><i data-lucide="pencil" class="size-4"></i>Редактировать</a></li>
                                                <li aria-hidden="true" class="pointer-events-none my-1 border-t border-(--fm-line)"></li>
                                                <li><a class="text-error"><i data-lucide="trash-2" class="size-4"></i>Удалить</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="transition-colors hover:bg-(--fm-row-hover)">
                                    <td class="pl-4"><input type="checkbox" class="checkbox checkbox-sm checkbox-primary rounded-[5px]" aria-label="Выбрать FEM-119"></td>
                                    <td class="py-3">
                                        <div class="min-w-0">
                                            <a class="block truncate font-medium hover:text-primary" href="#entity-list">QA: повторный прогон пары «до/после»</a>
                                            <span class="text-xs tabular-nums text-(--fm-muted)">FEM-119 · спринт 11</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <div class="avatar avatar-placeholder"><div class="w-6 rounded-full bg-neutral text-neutral-content"><span class="text-[10px] font-semibold">АК</span></div></div>
                                            <span class="text-(--fm-ink-2)">Анна К.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="tabular-nums">22.11.2026</div>
                                        <div class="text-xs text-(--fm-muted)">2 дня назад</div>
                                    </td>
                                    <td><span class="badge badge-soft badge-error badge-sm gap-1.5 font-medium"><span class="size-1.5 rounded-full bg-error"></span>Заблокирована</span></td>
                                    <td class="pr-4 text-right">
                                        <div class="dropdown dropdown-end dropdown-top">
                                            <div tabindex="0" role="button" class="btn btn-ghost btn-sm btn-square h-8 w-8 text-(--fm-muted) hover:text-base-content" aria-label="Действия"><i data-lucide="ellipsis" class="size-4"></i></div>
                                            <ul tabindex="0" class="dropdown-content menu z-30 my-1 w-60 rounded-box border border-(--fm-line) bg-base-100 p-1.5 text-left shadow-(--fm-shadow-lg)">
                                                <li><a><i data-lucide="eye" class="size-4"></i>Открыть карточку</a></li>
                                                <li><a><i data-lucide="pencil" class="size-4"></i>Редактировать</a></li>
                                                <li aria-hidden="true" class="pointer-events-none my-1 border-t border-(--fm-line)"></li>
                                                <li><a class="text-error"><i data-lucide="trash-2" class="size-4"></i>Удалить</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- пагинация --}}
                    <div class="flex flex-wrap items-center gap-4 border-t border-(--fm-line) px-4 py-3">
                        <p class="text-(--fm-muted)">Показано <span class="font-medium tabular-nums text-base-content">1–3</span> из <span class="font-medium tabular-nums text-base-content">47</span></p>
                        <div class="ml-auto flex items-center gap-5">
                            <label class="flex items-center gap-2 text-(--fm-muted)">
                                На странице
                                <select class="select select-sm h-8 w-20 border-(--fm-line) tabular-nums shadow-(--fm-shadow-xs)"><option selected>10</option></select>
                            </label>
                            <nav class="flex items-center gap-1" aria-label="Страницы">
                                <button class="btn btn-ghost btn-sm btn-square h-8 w-8 text-(--fm-subtle)" disabled aria-label="Назад"><i data-lucide="chevron-left" class="size-4"></i></button>
                                <button class="btn btn-sm btn-square h-8 w-8 border-transparent bg-(--fm-primary-soft-2) font-semibold tabular-nums text-primary shadow-none" aria-current="page">1</button>
                                <button class="btn btn-ghost btn-sm btn-square h-8 w-8 font-normal tabular-nums">2</button>
                                <button class="btn btn-ghost btn-sm btn-square h-8 w-8 font-normal tabular-nums">3</button>
                                <button class="btn btn-ghost btn-sm btn-square h-8 w-8 text-(--fm-ink-2)" aria-label="Вперёд"><i data-lucide="chevron-right" class="size-4"></i></button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===== ПРИНЦИПЫ ===== --}}
        <section id="principles" class="mt-12 scroll-mt-20">
            <div class="flex items-center gap-3">
                <h2 class="text-sm font-semibold text-(--fm-ink-2)">Принципы</h2>
                <span class="h-px grow bg-(--fm-line)"></span>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="rounded-box border border-(--fm-line) bg-base-100 p-5 shadow-(--fm-shadow-xs)">
                    <p class="font-semibold tracking-tight">Разметка</p>
                    <ul class="mt-2 list-disc space-y-1.5 pl-5 text-(--fm-muted)">
                        <li>Blade — только отображение: php-вставки и бизнес-логика запрещены; данные готовит контроллер.</li>
                        <li>Цвета и тени — только токены темы (<code class="font-mono text-xs">--fm-*</code>, daisyUI-классы); сырые значения в разметке запрещены.</li>
                        <li>Иконки — lucide через <code class="font-mono text-xs">data-lucide</code>; createIcons вызывается в app.js.</li>
                        <li>Числа — <code class="font-mono text-xs">tabular-nums</code>; длинные названия — <code class="font-mono text-xs">truncate</code> с <code class="font-mono text-xs">max-w</code>.</li>
                        <li>Тексты UI — через файлы переводов, не хардкодом; термины предметной области не переводим.</li>
                    </ul>
                </div>
                <div class="rounded-box border border-(--fm-line) bg-base-100 p-5 shadow-(--fm-shadow-xs)">
                    <p class="font-semibold tracking-tight">Поведение</p>
                    <ul class="mt-2 list-disc space-y-1.5 pl-5 text-(--fm-muted)">
                        <li>Дропдауны — focus/tabindex (daisyUI), без JS; строки-кнопки и меню шапки — кликабельны целиком.</li>
                        <li>Главная кнопка на экране одна (btn-primary); опасные действия — text-error, отдельно от обычных.</li>
                        <li>Недоступное — приглушить (opacity-70, lock-бейдж), а не прятать: пользователь должен понимать причину.</li>
                        <li>Пустые состояния — пунктирная карточка + одно действие; не оставлять пустые экраны.</li>
                        <li>Новый паттерн сначала — на эту страницу, затем в продукт.</li>
                    </ul>
                </div>
            </div>
            <p class="mt-8 pb-4 text-center text-xs text-(--fm-subtle)">Тема «Femida»: индиго + тёмный сайдбар · стильгайд доступен только в dev-окружении</p>
        </section>

    </main>
</div>

</body>
</html>
