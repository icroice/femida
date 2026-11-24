import * as lucide from 'lucide';

const { createIcons, ...iconSet } = lucide;

// Icons: <i data-lucide="..."> elements are replaced with inline svg.
const refreshIcons = () => createIcons({ nameAttr: 'data-lucide', icons: iconSet });

document.addEventListener('DOMContentLoaded', refreshIcons);

// Livewire morphs the component DOM after every update: a fresh render brings
// <i data-lucide> that createIcons has not seen yet (icons "disappear").
// Re-run after every commit (idempotent: only <i> elements are replaced).
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ succeed }) => {
        succeed(() => queueMicrotask(refreshIcons));
    });
});
