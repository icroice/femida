<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesApiKeyContext;
use App\Support\Like;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list_cases')]
#[Description('The project test case library. Returns ids and titles — a task session is assembled from them.')]
class ListCasesTool extends Tool
{
    use ResolvesApiKeyContext;

    public function handle(Request $request): Response
    {
        // agent flag: include steps right away, saving N extra get_case calls
        $withSteps = (bool) $request->get('with_steps');

        // the library defaults to active cases only (inactive ones are unused)
        $request->validate(['status' => ['nullable', 'in:active,inactive,all']]);
        $status = $request->get('status') ?: 'active';

        $cases = $this->project()
            ->testCases()
            ->when($request->get('query'), function ($query, $term) {
                // SQLite LIKE is case-sensitive for non-ASCII: search both cases
                // of the first letter; LIKE wildcards are escaped (Like + ESCAPE)
                $capitalized = mb_strtoupper(mb_substr($term, 0, 1)).mb_substr($term, 1);
                $query->where(fn ($w) => $w
                    ->whereRaw("title LIKE ? ESCAPE '\\'", [Like::contains($term)])
                    ->orWhereRaw("title LIKE ? ESCAPE '\\'", [Like::contains($capitalized)]));
            })
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->get('folder'), function ($query, $folder) {
                // exact folder, or a parent folder with all its subfolders (prefix)
                $query->where(fn ($w) => $w
                    ->where('folder', $folder)
                    ->orWhereRaw("folder LIKE ? ESCAPE '\\'", [Like::prefix($folder)]));
            })
            ->orderBy('title')
            ->get();

        return Response::json([
            'total' => $cases->count(),
            'cases' => $cases->map(fn ($case) => [
                'id' => $case->id,
                'title' => $case->title,
                'folder' => $case->folder,
                'priority' => $case->priority->value,
                'status' => $case->status->value,
                'steps_count' => count($case->steps ?? []),
                ...($withSteps ? ['steps' => $case->steps ?? []] : []),
            ]),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => Type::string()->description('Substring of the case title'),
            'status' => Type::string()->enum(['active', 'inactive', 'all'])->description('Status filter; defaults to active — the library of active cases'),
            'folder' => Type::string()->description('Folder filter: exact folder or a parent level (pulls in subfolders)'),
            'with_steps' => Type::boolean()->description('Return case steps themselves instead of only steps_count — saves N get_case calls'),
        ];
    }
}
