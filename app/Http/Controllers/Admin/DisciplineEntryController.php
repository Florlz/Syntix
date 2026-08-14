<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Registrations\SaveDisciplineEntry;
use App\Http\Controllers\Controller;
use App\Models\Discipline;
use App\Models\DisciplineEntry;
use App\Models\Entry;
use App\Models\Event;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DisciplineEntryController extends Controller
{
    public function update(
        Request $request,
        Event $event,
        Discipline $discipline,
        Entry $entry,
        SaveDisciplineEntry $save,
    ): RedirectResponse {
        $this->assertWritable($request, $event);
        $data = $request->validate([
            'members' => ['array'],
            'members.*.participant_id' => ['required', 'integer'],
            'members.*.is_starter' => ['sometimes', 'boolean'],
            'members.*.is_active' => ['sometimes', 'boolean'],
            'members.*.notes' => ['nullable', 'string', 'max:2000'],
            'state' => ['sometimes', 'in:draft,locked'],
        ]);

        $save->handle(
            $request->user(),
            $event,
            $discipline,
            $entry,
            array_values($data['members'] ?? []),
            $data['state'] ?? 'draft',
        );

        return back()->with('status', 'Discipline Entry assignments saved.');
    }

    public function state(
        Request $request,
        Event $event,
        Discipline $discipline,
        Entry $entry,
        SaveDisciplineEntry $save,
    ): RedirectResponse {
        $this->assertWritable($request, $event);
        $disciplineEntry = DisciplineEntry::query()
            ->where('discipline_id', $discipline->getKey())
            ->where('entry_id', $entry->getKey())
            ->with('members')
            ->firstOrFail();

        $members = $disciplineEntry->members->map(fn ($member): array => [
            'participant_id' => (int) $member->participant_id,
            'is_starter' => (bool) $member->is_starter,
            'is_active' => (bool) $member->is_active,
            'notes' => $member->notes,
        ])->all();

        $data = $request->validate(['state' => ['required', 'in:draft,locked']]);
        $save->handle($request->user(), $event, $discipline, $entry, $members, $data['state']);

        return back()->with('status', $data['state'] === 'locked'
            ? 'Discipline Entry locked for drawing.'
            : 'Discipline Entry returned to draft.');
    }

    private function assertWritable(Request $request, Event $event): void
    {
        if (! $request->user()->hasAdminAccess($event)) {
            throw new AuthorizationException('Only the Global Admin can manage discipline Entries.');
        }
        if ($event->isArchived()) {
            throw new AuthorizationException('Archived events are read-only.');
        }
    }
}
