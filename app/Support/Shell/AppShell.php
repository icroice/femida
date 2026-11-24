<?php

namespace App\Support\Shell;

use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestSession;
use App\Models\User;
use App\Support\Access\AccessService;
use App\Support\Access\Module;
use App\Support\Access\ModuleRegistry;
use App\Support\Access\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * The app shell model (006, v2-19): everything the layout renders — the
 * project context, module tabs, the section rail, the switcher — is
 * assembled here, not in Blade ("Blade is display only", styleguide).
 *
 * Section presentation (route, icon, label, counter) lives in one map;
 * a section whose route is not registered is skipped, so a module added
 * to the registry before its pages exist cannot 500 the shell.
 */
final class AppShell
{
    /** Module tab icons; a module without an entry falls back to 'layers'. */
    private const MODULE_ICONS = [
        'qa' => 'flask-conical',
    ];

    /**
     * Section presentation by id: route name, icon, lang key. A section
     * absent here has no page to link and no counter — it stays off the
     * rail even though the module declares it.
     */
    private const SECTIONS = [
        'cases' => ['route' => 'projects.cases.index', 'icon' => 'list-todo', 'title' => 'app.cases'],
        'sessions' => ['route' => 'projects.sessions.index', 'icon' => 'play', 'title' => 'app.sessions'],
    ];

    public readonly ?User $user;

    /** The route-bound project (v2-19): the shell exists only inside one. */
    public readonly ?Project $project;

    /** @var list<Module> viewable modules with at least one linkable section */
    public readonly array $modules;

    public readonly ?Module $activeModule;

    /** Section id highlighted inside the active module's rail. */
    public readonly ?string $activeSectionId;

    /** @var array<string, list<SectionLink>> per-module memo of the request */
    private array $linkCache = [];

    /** @var Collection<int, Project>|null */
    private ?Collection $switcherProjects = null;

    public function __construct(
        private readonly Request $request,
        private readonly AccessService $access,
    ) {
        $this->user = $request->user();

        $bound = $request->route()?->parameter('project');
        $this->project = $bound instanceof Project ? $bound : null;

        $this->modules = $this->resolveModules();

        [$this->activeModule, $this->activeSectionId] = $this->resolveActive();
    }

    /** Initials for the profile avatar. */
    public function initials(): string
    {
        return mb_strtoupper(collect(explode(' ', (string) $this->user?->name))
            ->filter()
            ->map(fn ($part) => mb_substr($part, 0, 1))
            ->take(2)
            ->implode(''));
    }

    public function managesUsers(): bool
    {
        return $this->user?->managesUsers() ?? false;
    }

    /**
     * Projects for the switcher: the instance admin switches between all,
     * a member — between their own.
     *
     * @return Collection<int, Project>
     */
    public function switcherProjects(): Collection
    {
        if ($this->switcherProjects !== null) {
            return $this->switcherProjects;
        }

        return $this->switcherProjects = $this->user === null
            ? collect()
            : ($this->user->isAdmin()
                ? Project::query()->orderBy('name')->get()
                : $this->user->projects()->orderBy('name')->get());
    }

    public function moduleIcon(string $moduleId): string
    {
        return self::MODULE_ICONS[$moduleId] ?? 'layers';
    }

    /** The module tab target: its first linkable section page. */
    public function moduleUrl(Module $module): ?string
    {
        foreach ($this->sectionLinks($module) as $link) {
            return $link->url;
        }

        return null;
    }

    /**
     * Rail items of a module (cached per request).
     *
     * @return list<SectionLink>
     */
    public function sectionLinks(Module $module): array
    {
        return $this->linkCache[$module->id] ??= self::sectionLinksFor($module, $this->project, withCounts: true);
    }

    /**
     * The switcher target for any project: the first linkable section of
     * the first registry module (no counters — the switcher may hold a
     * long project list).
     */
    public function landingUrl(Project $project): ?string
    {
        foreach (ModuleRegistry::all() as $module) {
            $link = self::sectionLinksFor($module, $project, withCounts: false)[0] ?? null;

            if ($link !== null) {
                return $link->url;
            }
        }

        return null;
    }

    /**
     * Linkable sections of a module: declared in SECTIONS and backed by a
     * registered route. Pure presentation resolution, safe to call for any
     * registry module — an undeclared or routeless section is skipped.
     *
     * @return list<SectionLink>
     */
    public static function sectionLinksFor(Module $module, ?Project $project, bool $withCounts = true): array
    {
        if ($project === null) {
            return [];
        }

        $links = [];

        foreach ($module->sections as $id) {
            $meta = self::SECTIONS[$id] ?? null;

            if ($meta === null || ! RouteFacade::has($meta['route'])) {
                continue;
            }

            $links[] = new SectionLink(
                id: $id,
                title: __($meta['title']),
                icon: $meta['icon'],
                url: route($meta['route'], $project),
                count: $withCounts ? self::countFor($id, $project) : null,
            );
        }

        return $links;
    }

    /** Entity counters in the rail; sections without an entity show none. */
    private static function countFor(string $id, Project $project): ?int
    {
        return match ($id) {
            'cases' => TestCase::query()->where('project_id', $project->id)->count(),
            'sessions' => TestSession::query()->where('project_id', $project->id)->count(),
            default => null,
        };
    }

    /**
     * Registry modules the user may view (spec §5 — membership ∧ right;
     * today every member holds every verb, v2-18) that have a linkable
     * section — a module without a landing page shows no tab.
     *
     * @return list<Module>
     */
    private function resolveModules(): array
    {
        if ($this->project === null || $this->user === null) {
            return [];
        }

        $modules = [];

        foreach (ModuleRegistry::all() as $module) {
            if ($module->sections === []) {
                continue;
            }

            if (! $this->access->check($this->user, $this->project, $module->ability($module->sections[0], Permission::View))) {
                continue;
            }

            if ($this->sectionLinks($module) === []) {
                continue;
            }

            $modules[] = $module;
        }

        return $modules;
    }

    /**
     * The open module/section from the route name ('projects.{section}.…'
     * or a bare '{section}.…' page route): URL shapes come and go (023),
     * names carry the semantics. A page outside module sections (settings,
     * screenshots) keeps the first module rail without a highlighted item.
     *
     * @return array{0: ?Module, 1: ?string}
     */
    private function resolveActive(): array
    {
        if ($this->modules === []) {
            return [null, null];
        }

        $parts = explode('.', (string) $this->request->route()?->getName());
        $sectionId = $parts[0] === 'projects' ? ($parts[1] ?? '') : $parts[0];

        foreach ($this->modules as $module) {
            if ($module->hasSection($sectionId)) {
                return [$module, $sectionId];
            }
        }

        return [$this->modules[0], null];
    }
}
