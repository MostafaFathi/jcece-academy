<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\PermissionName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketAssigneeLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can(PermissionName::SupportTicketsManage->value), 403);

        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'id' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $assignees = User::query()
            ->permission(PermissionName::SupportTicketsManage->value)
            ->when(isset($filters['id']), fn ($query) => $query->whereKey($filters['id']))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->orderBy('id')
            ->select(['id', 'name'])
            ->paginate(25);

        return response()->json([
            'data' => $assignees->getCollection()->map(fn (User $assignee): array => ['id' => $assignee->id, 'name' => $assignee->name])->values(),
            'meta' => ['current_page' => $assignees->currentPage(), 'last_page' => $assignees->lastPage()],
        ]);
    }
}
