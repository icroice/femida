// Named imports only: a namespace import (import * as lucide) would pull
// the whole ~1500-icon library into the bundle of every page. When a view
// needs a new icon, add it here — a missing one logs a console warning
// (see refreshIcons below).
import {
    createIcons,
    ArrowDown,
    ArrowRight,
    BadgeCheck,
    Bell,
    CalendarDays,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    CircleCheck,
    Code,
    Ellipsis,
    Eye,
    FlaskConical,
    GitPullRequest,
    Layers,
    LayoutDashboard,
    LayoutTemplate,
    ListTodo,
    Lock,
    LogOut,
    Mail,
    Package,
    Palette,
    PanelsTopLeft,
    Pencil,
    Plus,
    RectangleHorizontal,
    RotateCcw,
    Scale,
    Search,
    Settings,
    Square,
    Table,
    Tag,
    TextCursorInput,
    Trash2,
    Type,
    Upload,
    User,
    X,
} from 'lucide';

const icons = {
    ArrowDown,
    ArrowRight,
    BadgeCheck,
    Bell,
    CalendarDays,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    CircleCheck,
    Code,
    Ellipsis,
    Eye,
    FlaskConical,
    GitPullRequest,
    Layers,
    LayoutDashboard,
    LayoutTemplate,
    ListTodo,
    Lock,
    LogOut,
    Mail,
    Package,
    Palette,
    PanelsTopLeft,
    Pencil,
    Plus,
    RectangleHorizontal,
    RotateCcw,
    Scale,
    Search,
    Settings,
    Square,
    Table,
    Tag,
    TextCursorInput,
    Trash2,
    Type,
    Upload,
    User,
    X,
};

// Icons: <i data-lucide="..."> elements are replaced with inline svg.
const refreshIcons = () => {
    createIcons({ nameAttr: 'data-lucide', icons });

    // an icon missing from the imports above would silently stay <i>
    document.querySelectorAll('i[data-lucide]').forEach((el) => {
        console.warn(`[icons] "${el.getAttribute('data-lucide')}" is not imported in app.js`);
    });
};

document.addEventListener('DOMContentLoaded', refreshIcons);

// Livewire morphs the component DOM after every update: a fresh render brings
// <i data-lucide> that createIcons has not seen yet (icons "disappear").
// Re-run after every commit (idempotent: only <i> elements are replaced).
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ succeed }) => {
        succeed(() => queueMicrotask(refreshIcons));
    });
});
