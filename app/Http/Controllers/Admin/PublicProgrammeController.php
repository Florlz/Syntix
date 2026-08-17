<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\PublicationState;
use App\Enums\ScheduleStatus;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionCoverImage;
use App\Models\Contest;
use App\Models\Division;
use App\Models\Event;
use App\Models\BracketNode;
use App\Models\Schedule;
use App\Models\SchedulePublication;
use App\Models\Venue;
use App\Services\AuditLogger;
use App\Services\SportWorkspaceReadModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PublicProgrammeController extends Controller
{
    public function index(Request $request, Event $event, SportWorkspaceReadModel $workspaceReadModel): Response
    {
        $this->assertAdmin($request, $event);
        $filters = $request->validate([
            'competition' => ['nullable', 'integer'],
            'division' => ['nullable', 'integer'],
        ]);
        $event->load([
            'competitions' => fn ($query) => $query->orderBy('name'),
            'competitions.divisions' => fn ($query) => $query->withCount('entries')->orderBy('name'),
            'competitions.draftCoverImage',
            'competitions.publishedCoverImage',
            'venues' => fn ($query) => $query->orderBy('name'),
            // Keep the event id and the division graph in agreement. The
            // latter guard prevents malformed/cross-event rows from leaking
            // into the event-wide admin projection.
            'schedules' => fn ($query) => $query
                ->whereHas('division.competition', fn ($competitionQuery) => $competitionQuery->where('event_id', $event->getKey()))
                ->orderBy('starts_at'),
            'schedules.division.competition',
            'schedules.contest',
            'schedules.venue',
            'schedules.currentPublication',
        ]);

        $competition = ($filters['competition'] ?? null) === null
            ? null
            : $event->competitions->firstWhere('id', (int) $filters['competition']);
        $division = ($filters['division'] ?? null) === null
            ? null
            : $event->competitions->flatMap->divisions->firstWhere('id', (int) $filters['division']);
        if (($filters['competition'] ?? null) !== null && $competition === null) {
            abort(404);
        }
        if (($filters['division'] ?? null) !== null && ($division === null || ($competition !== null && (int) $division->competition_id !== (int) $competition->getKey()))) {
            abort(404);
        }
        $schedules = $event->schedules
            ->when($division !== null, fn ($items) => $items->where('competition_division_id', $division->getKey()))
            ->when($division === null && $competition !== null, fn ($items) => $items->filter(fn (Schedule $schedule): bool => (int) $schedule->division?->competition_id === (int) $competition->getKey()))
            ->values();
        $matches = $this->activeBracketMatches($event, $competition, $division);

        return Inertia::render('Admin/Events/PublicProgramme', [
            'event' => [
                'id' => (string) $event->getKey(),
                'name' => $event->name,
                'state' => $event->eventState()->value,
                'archived' => $event->isArchived(),
            ],
            'competitions' => $event->competitions->map(function (Competition $competition) use ($event, $workspaceReadModel): array {
                $workspace = $workspaceReadModel->forSport($competition);

                return array_merge($workspace['sport'], [
                    'divisions' => $workspace['divisions'],
                    'draft_cover' => $this->adminCover($event, $competition->draftCoverImage),
                    'published_cover' => $this->adminCover($event, $competition->publishedCoverImage),
                ]);
            })->values()->all(),
            'venues' => $event->venues->map(fn (Venue $venue) => [
                'id' => (string) $venue->getKey(),
                'name' => $venue->name,
                'code' => $venue->code,
                'location' => $venue->location,
                'description' => $venue->description,
                'is_active' => (bool) $venue->is_active,
            ])->values()->all(),
            'schedules' => $schedules->map(fn (Schedule $schedule) => [
                'id' => (string) $schedule->getKey(),
                'contest_id' => $schedule->contest_id === null ? null : (string) $schedule->contest_id,
                'competition_division_id' => (string) $schedule->competition_division_id,
                'competition_id' => $schedule->division?->competition?->getKey() === null ? null : (string) $schedule->division->competition->getKey(),
                'competition' => $schedule->division?->competition?->name,
                'division' => $schedule->division?->name,
                'venue_id' => $schedule->venue_id === null ? null : (string) $schedule->venue_id,
                'title' => $schedule->title,
                'starts_at' => $schedule->starts_at?->toIso8601String(),
                'ends_at' => $schedule->ends_at?->toIso8601String(),
                'status' => $schedule->status instanceof ScheduleStatus
                    ? $schedule->status->value
                    : (string) $schedule->status,
                'notes' => $schedule->notes,
                'publication' => $this->adminSchedulePublication($schedule->currentPublication),
                'has_unpublished_changes' => $this->scheduleHasUnpublishedChanges($schedule),
            ])->values()->all(),
            'matches' => $matches->values()->all(),
            'schedule_statuses' => array_map(
                static fn (ScheduleStatus $status): array => [
                    'value' => $status->value,
                    'label' => Str::headline($status->value),
                ],
                ScheduleStatus::cases(),
            ),
            'scope' => [
                'competition' => $competition?->name ?? $division?->competition?->name,
                'division' => $division?->name,
                'competition_id' => $competition === null ? ($division?->competition?->getKey() === null ? null : (string) $division->competition->getKey()) : (string) $competition->getKey(),
                'division_id' => $division === null ? null : (string) $division->getKey(),
            ],
        ]);
    }

    /**
     * Build the operational agenda from the latest active draw. A schedule is
     * deliberately attached to a contest, rather than creating a second game
     * record which can drift away from the bracket.
     */
    private function activeBracketMatches(Event $event, ?Competition $competition, ?Division $division)
    {
        $divisions = $event->competitions
            ->when($competition !== null, fn ($items) => $items->where('id', $competition->getKey()))
            ->flatMap->divisions
            ->when($division !== null, fn ($items) => $items->where('id', $division->getKey()))
            ->values();
        $matches = collect();

        foreach ($divisions as $currentDivision) {
            $tournament = $currentDivision->tournaments()
                ->whereIn('state', ['preview', 'published', 'uncontested'])
                ->latest('id')
                ->first();
            $bracket = $tournament?->bracketVersions()
                ->whereIn('state', ['preview', 'published'])
                ->latest('version')
                ->first();
            if ($bracket === null) {
                continue;
            }
            $bracket->load([
                'nodes' => fn ($query) => $query->whereIn('node_type', ['contest', 'third_place', 'reset_final'])->orderBy('round_number')->orderBy('sequence'),
                'nodes.contest',
                'nodes.slots.entry',
            ]);
            foreach ($bracket->nodes as $node) {
                if ($node->contest === null) {
                    continue;
                }
                $contest = $node->contest;
                // The contest is reached through the event's division graph,
                // but keep this guard explicit for cross-event safety.
                if ((int) $contest->division?->competition?->event_id !== (int) $event->getKey()
                    || (int) $contest->competition_division_id !== (int) $currentDivision->getKey()) {
                    continue;
                }
                $schedule = $event->schedules->firstWhere('contest_id', $contest->getKey());
                $slots = $node->slots->sortBy('slot_number')->values();
                $teams = $slots->map(function ($slot): string {
                    if ($slot->entry !== null) {
                        return $slot->entry->name;
                    }
                    return $slot->label ?: 'TBD';
                })->pad(2, 'TBD')->take(2)->values();
                $matches->push([
                    'id' => (string) $contest->getKey(),
                    'contest_id' => (string) $contest->getKey(),
                    'node_key' => $node->node_key,
                    'round' => $node->round_number === null ? 'Bracket' : 'Round '.$node->round_number,
                    'sequence' => (int) ($node->sequence ?? 0),
                    'competition' => $currentDivision->competition?->name,
                    'competition_id' => (string) $currentDivision->competition_id,
                    'division' => $currentDivision->name,
                    'division_id' => (string) $currentDivision->getKey(),
                    'teams' => $teams->all(),
                    'contest_state' => $contest->state instanceof \BackedEnum ? $contest->state->value : (string) $contest->state,
                    'schedule' => $schedule === null ? null : [
                        'id' => (string) $schedule->getKey(),
                        'venue_id' => $schedule->venue_id === null ? null : (string) $schedule->venue_id,
                        'title' => $schedule->title,
                        'starts_at' => $schedule->starts_at?->toIso8601String(),
                        'ends_at' => $schedule->ends_at?->toIso8601String(),
                        'status' => $schedule->status instanceof ScheduleStatus ? $schedule->status->value : (string) $schedule->status,
                        'notes' => $schedule->notes,
                        'venue' => $schedule->venue?->name,
                        'publication' => $this->adminSchedulePublication($schedule->currentPublication),
                        'has_unpublished_changes' => $this->scheduleHasUnpublishedChanges($schedule),
                    ],
                ]);
            }
        }

        return $matches->sortBy(fn ($match) => [$match['schedule']['starts_at'] ?? '9999-12-31T23:59:59Z', $match['division'], $match['sequence']])->values();
    }

    public function storeVenue(Request $request, Event $event, AuditLogger $audit): RedirectResponse
    {
        $this->assertAdmin($request, $event);
        $this->assertMutableEvent($event);
        $data = $this->validateVenue($request, $event);
        $venue = $event->venues()->create($data);
        $audit->record($request->user(), AuditAction::VenueCreated, $venue, $event, after: $this->venueAuditData($venue));

        return back()->with('status', 'Venue created.');
    }

    public function updateVenue(Request $request, Event $event, Venue $venue, AuditLogger $audit): RedirectResponse
    {
        $this->assertAdmin($request, $event);
        $this->assertMutableEvent($event);
        $this->assertBelongsToEvent($venue->event_id, $event);
        $before = $this->venueAuditData($venue);
        $venue->update($this->validateVenue($request, $event, $venue));
        $audit->record($request->user(), AuditAction::VenueUpdated, $venue, $event, before: $before, after: $this->venueAuditData($venue));

        return back()->with('status', 'Venue updated. Published schedule snapshots were not changed.');
    }

    public function storeSchedule(Request $request, Event $event, AuditLogger $audit): RedirectResponse
    {
        $this->assertAdmin($request, $event);
        $this->assertMutableEvent($event);
        $data = $this->validateSchedule($request, $event);
        $schedule = $event->schedules()->create($data);
        $audit->record($request->user(), AuditAction::ScheduleCreated, $schedule, $event, after: $this->scheduleAuditData($schedule));

        return back()->with('status', 'Schedule created as an unpublished draft.');
    }

    public function updateSchedule(Request $request, Event $event, Schedule $schedule, AuditLogger $audit): RedirectResponse
    {
        $this->assertAdmin($request, $event);
        $this->assertMutableEvent($event);
        $this->assertBelongsToEvent($schedule->event_id, $event);
        $this->assertScheduleGraph($schedule, $event);
        $before = $this->scheduleAuditData($schedule);
        $schedule->update($this->validateSchedule($request, $event, $schedule));
        $audit->record($request->user(), AuditAction::ScheduleUpdated, $schedule, $event, before: $before, after: $this->scheduleAuditData($schedule));

        return back()->with('status', 'Schedule draft updated. Republish to change the public programme.');
    }

    public function publishCompetitionSchedules(Request $request, Event $event, Competition $competition, AuditLogger $audit): RedirectResponse
    {
        $this->assertAdmin($request, $event);
        $this->assertMutableEvent($event);
        $this->assertBelongsToEvent($competition->event_id, $event);

        $count = 0;
        // A sport can retain schedules for superseded draws. Only contests
        // in the latest active bracket are publishable from this bulk action.
        $activeContestIds = $this->activeBracketMatches($event, $competition, null)
            ->pluck('contest_id')
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($request, $event, $competition, $audit, $activeContestIds, &$count): void {
            $schedules = Schedule::query()
                ->with(['division.competition', 'venue'])
                ->where('event_id', $event->getKey())
                ->whereHas('division', fn ($query) => $query->where('competition_id', $competition->getKey()))
                ->whereHas('contest', fn ($query) => $query->whereColumn('contests.competition_division_id', 'schedules.competition_division_id'))
                ->whereNotNull('contest_id')
                ->whereIn('contest_id', $activeContestIds->all())
                ->lockForUpdate()
                ->get();
            foreach ($schedules as $schedule) {
                if ($schedule->starts_at === null || ! $schedule->hasUnpublishedChanges()) {
                    continue;
                }
                $publications = $schedule->publications()->lockForUpdate();
                $revision = ((int) (clone $publications)->max('revision')) + 1;
                (clone $publications)->where('state', PublicationState::Published->value)->update(['state' => PublicationState::Superseded->value]);
                $publication = $schedule->publications()->create([
                    'revision' => $revision,
                    'competition_name' => $schedule->division?->competition?->name,
                    'division_name' => $schedule->division?->name,
                    'title' => $schedule->title,
                    'starts_at' => $schedule->starts_at,
                    'ends_at' => $schedule->ends_at,
                    'status' => $schedule->status,
                    'venue_name' => $schedule->venue?->name,
                    'venue_location' => $schedule->venue?->location,
                    'state' => PublicationState::Published,
                    'published_by' => $request->user()->getKey(),
                    'published_at' => now(),
                ]);
                $audit->record($request->user(), AuditAction::SchedulePublished, $publication, $event, after: ['schedule_id' => (string) $schedule->getKey(), 'revision' => $revision, 'bulk' => true]);
                $count++;
            }
        });

        return back()->with('status', $count === 0 ? 'No changed bracket schedules were ready to publish.' : $count.' schedule'.($count === 1 ? '' : 's').' published for '.$competition->name.'.');
    }

    public function uploadCover(Request $request, Event $event, Competition $competition, AuditLogger $audit): RedirectResponse
    {
        $this->assertAdmin($request, $event);
        $this->assertMutableEvent($event);
        $this->assertBelongsToEvent($competition->event_id, $event);
        $data = $request->validate([
            'cover' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=800,min_height=450'],
            'alt_text' => ['required', 'string', 'min:10', 'max:180'],
        ]);
        $upload = $request->file('cover');
        $dimensions = getimagesize($upload->getPathname());

        if ($dimensions === false) {
            throw ValidationException::withMessages(['cover' => 'The uploaded file is not a readable image.']);
        }

        $extension = strtolower($upload->extension() ?: $upload->getClientOriginalExtension());
        $privatePath = $upload->storeAs(
            "events/{$event->getKey()}/competition-covers/{$competition->getKey()}",
            Str::uuid().'.'.$extension,
            'local',
        );

        if ($privatePath === false) {
            throw ValidationException::withMessages(['cover' => 'The image could not be stored. Try again.']);
        }

        try {
            $cover = DB::transaction(function () use ($competition, $request, $data, $upload, $dimensions, $privatePath): CompetitionCoverImage {
                $covers = $competition->coverImages()->lockForUpdate();
                $revision = ((int) (clone $covers)->max('revision')) + 1;
                (clone $covers)->where('state', PublicationState::Draft->value)->update([
                    'state' => PublicationState::Withdrawn->value,
                    'withdrawn_by' => $request->user()->getKey(),
                    'withdrawn_at' => now(),
                    'withdrawal_reason' => 'Replaced by a newer draft upload.',
                ]);

                return $competition->coverImages()->create([
                    'revision' => $revision,
                    'private_path' => $privatePath,
                    'mime_type' => $upload->getMimeType() ?: 'application/octet-stream',
                    'file_size' => $upload->getSize(),
                    'width' => $dimensions[0],
                    'height' => $dimensions[1],
                    'alt_text' => trim($data['alt_text']),
                    'state' => PublicationState::Draft,
                    'created_by' => $request->user()->getKey(),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($privatePath);
            throw $exception;
        }

        $audit->record($request->user(), AuditAction::CompetitionCoverUploaded, $cover, $event, after: [
            'competition_id' => (string) $competition->getKey(),
            'revision' => $cover->revision,
            'mime_type' => $cover->mime_type,
            'width' => $cover->width,
            'height' => $cover->height,
        ]);

        return back()->with('status', 'Cover uploaded as a private draft. Publish it when ready.');
    }

    public function previewCover(Request $request, Event $event, CompetitionCoverImage $cover): StreamedResponse
    {
        $this->assertAdmin($request, $event);
        $cover->loadMissing('competition');
        $this->assertBelongsToEvent($cover->competition?->event_id, $event);

        abort_unless(Storage::disk('local')->exists($cover->private_path), 404);

        return Storage::disk('local')->response($cover->private_path, headers: [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => $cover->mime_type,
        ]);
    }

    public function publishCover(Request $request, Event $event, CompetitionCoverImage $cover, AuditLogger $audit): RedirectResponse
    {
        $this->assertAdmin($request, $event);
        $this->assertMutableEvent($event);
        $cover->loadMissing('competition');
        $this->assertBelongsToEvent($cover->competition?->event_id, $event);

        if ($cover->state !== PublicationState::Draft) {
            throw ValidationException::withMessages(['cover' => 'Only the current draft cover can be published.']);
        }

        $extension = pathinfo($cover->private_path, PATHINFO_EXTENSION);
        $publicPath = "events/{$event->getKey()}/competitions/{$cover->competition_id}/cover-r{$cover->revision}.{$extension}";
        $source = Storage::disk('local')->readStream($cover->private_path);

        if ($source === false || ! Storage::disk('public')->put($publicPath, $source)) {
            if (is_resource($source)) {
                fclose($source);
            }
            throw ValidationException::withMessages(['cover' => 'The cover could not be published. Try again.']);
        }
        if (is_resource($source)) {
            fclose($source);
        }

        $supersededPath = null;

        try {
            DB::transaction(function () use ($request, $event, $cover, $publicPath, $audit, &$supersededPath): void {
                $lockedCover = CompetitionCoverImage::query()->lockForUpdate()->findOrFail($cover->getKey());

                if ($lockedCover->state !== PublicationState::Draft) {
                    throw ValidationException::withMessages(['cover' => 'This draft has already been processed.']);
                }

                $published = CompetitionCoverImage::query()
                    ->where('competition_id', $lockedCover->competition_id)
                    ->where('state', PublicationState::Published->value)
                    ->lockForUpdate()
                    ->first();

                if ($published !== null) {
                    $supersededPath = $published->public_path;
                    $published->update([
                        'state' => PublicationState::Superseded,
                        'public_path' => null,
                    ]);
                }

                $lockedCover->update([
                    'state' => PublicationState::Published,
                    'public_path' => $publicPath,
                    'published_by' => $request->user()->getKey(),
                    'published_at' => now(),
                ]);
                $audit->record($request->user(), AuditAction::CompetitionCoverPublished, $lockedCover, $event, after: [
                    'competition_id' => (string) $lockedCover->competition_id,
                    'revision' => $lockedCover->revision,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($publicPath);
            throw $exception;
        }

        if ($supersededPath !== null) {
            Storage::disk('public')->delete($supersededPath);
        }

        return back()->with('status', 'Competition cover published.');
    }

    public function withdrawCover(Request $request, Event $event, CompetitionCoverImage $cover, AuditLogger $audit): RedirectResponse
    {
        $this->assertAdmin($request, $event);
        $this->assertMutableEvent($event);
        $cover->loadMissing('competition');
        $this->assertBelongsToEvent($cover->competition?->event_id, $event);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $publicPath = null;

        DB::transaction(function () use ($request, $event, $cover, $data, $audit, &$publicPath): void {
            $lockedCover = CompetitionCoverImage::query()->lockForUpdate()->findOrFail($cover->getKey());

            if ($lockedCover->state !== PublicationState::Published) {
                throw ValidationException::withMessages(['cover' => 'Only the current published cover can be withdrawn.']);
            }

            $publicPath = $lockedCover->public_path;
            $lockedCover->update([
                'state' => PublicationState::Withdrawn,
                'public_path' => null,
                'withdrawn_by' => $request->user()->getKey(),
                'withdrawn_at' => now(),
                'withdrawal_reason' => trim($data['reason']),
            ]);
            $audit->record($request->user(), AuditAction::CompetitionCoverWithdrawn, $lockedCover, $event, reason: trim($data['reason']), after: [
                'competition_id' => (string) $lockedCover->competition_id,
                'revision' => $lockedCover->revision,
            ]);
        });

        if ($publicPath !== null) {
            Storage::disk('public')->delete($publicPath);
        }

        return back()->with('status', 'Competition cover withdrawn. The public page now uses its fallback.');
    }

    public function publishSchedule(Request $request, Event $event, Schedule $schedule, AuditLogger $audit): RedirectResponse
    {
        $this->assertAdmin($request, $event);
        $this->assertMutableEvent($event);
        $this->assertBelongsToEvent($schedule->event_id, $event);
        $this->assertScheduleGraph($schedule, $event);

        DB::transaction(function () use ($request, $event, $schedule, $audit): void {
            $lockedSchedule = Schedule::query()
                ->with(['division.competition', 'venue'])
                ->lockForUpdate()
                ->findOrFail($schedule->getKey());
            $publications = $lockedSchedule->publications()->lockForUpdate();
            $revision = ((int) (clone $publications)->max('revision')) + 1;
            (clone $publications)->where('state', PublicationState::Published->value)->update([
                'state' => PublicationState::Superseded->value,
            ]);
            $publication = $lockedSchedule->publications()->create([
                'revision' => $revision,
                'competition_name' => $lockedSchedule->division?->competition?->name,
                'division_name' => $lockedSchedule->division?->name,
                'title' => $lockedSchedule->title,
                'starts_at' => $lockedSchedule->starts_at,
                'ends_at' => $lockedSchedule->ends_at,
                'status' => $lockedSchedule->status,
                'venue_name' => $lockedSchedule->venue?->name,
                'venue_location' => $lockedSchedule->venue?->location,
                'state' => PublicationState::Published,
                'published_by' => $request->user()->getKey(),
                'published_at' => now(),
            ]);
            $audit->record($request->user(), AuditAction::SchedulePublished, $publication, $event, after: [
                'schedule_id' => (string) $lockedSchedule->getKey(),
                'revision' => $publication->revision,
            ]);
        });

        return back()->with('status', 'Schedule snapshot published to the public programme.');
    }

    public function withdrawSchedule(Request $request, Event $event, SchedulePublication $publication, AuditLogger $audit): RedirectResponse
    {
        $this->assertAdmin($request, $event);
        $this->assertMutableEvent($event);
        $publication->loadMissing('schedule');
        $this->assertBelongsToEvent($publication->schedule?->event_id, $event);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);

        DB::transaction(function () use ($request, $event, $publication, $data, $audit): void {
            $lockedPublication = SchedulePublication::query()->lockForUpdate()->findOrFail($publication->getKey());

            if ($lockedPublication->state !== PublicationState::Published) {
                throw ValidationException::withMessages(['schedule' => 'Only the current published schedule can be withdrawn.']);
            }

            $lockedPublication->update([
                'state' => PublicationState::Withdrawn,
                'withdrawn_by' => $request->user()->getKey(),
                'withdrawn_at' => now(),
                'withdrawal_reason' => trim($data['reason']),
            ]);
            $audit->record($request->user(), AuditAction::SchedulePublicationWithdrawn, $lockedPublication, $event, reason: trim($data['reason']), after: [
                'schedule_id' => (string) $lockedPublication->schedule_id,
                'revision' => $lockedPublication->revision,
            ]);
        });

        return back()->with('status', 'Published schedule withdrawn. The operational draft is unchanged.');
    }

    /** @return array<string, mixed> */
    private function validateVenue(Request $request, Event $event, ?Venue $venue = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('venues', 'name')->where('event_id', $event->getKey())->ignore($venue?->getKey()),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validateSchedule(Request $request, Event $event, ?Schedule $schedule = null): array
    {
        $data = $request->validate([
            'competition_division_id' => ['nullable', 'integer', 'exists:competition_divisions,id'],
            'contest_id' => ['nullable', 'integer', 'exists:contests,id', Rule::unique('schedules', 'contest_id')->ignore($schedule?->getKey())],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['required', Rule::enum(ScheduleStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Once a schedule is attached to a bracket contest, editing its
        // timing must not silently move it to another match (or detach it).
        // A previously manual schedule may be linked once, which preserves
        // the legacy event-wide programme route.
        if ($schedule !== null && $schedule->contest_id !== null) {
            if (! array_key_exists('contest_id', $data) || $data['contest_id'] === null) {
                $data['contest_id'] = $schedule->contest_id;
            } elseif ((int) $data['contest_id'] !== (int) $schedule->contest_id) {
                throw ValidationException::withMessages([
                    'contest_id' => 'A schedule cannot be reassigned to a different bracket match.',
                ]);
            }
        }

        if (empty($data['contest_id']) && empty($data['competition_division_id'])) {
            if ($schedule !== null) {
                $data['competition_division_id'] = $schedule->competition_division_id;
            } else {
                throw ValidationException::withMessages([
                    'competition_division_id' => 'Select a division or link this schedule to a contest.',
                ]);
            }
        }
        if (! empty($data['contest_id'])) {
            $contest = Contest::query()->with('division.competition')->findOrFail($data['contest_id']);
            $this->assertBelongsToEvent($contest->division?->competition?->event_id, $event);
            if (! empty($data['competition_division_id']) && (int) $data['competition_division_id'] !== (int) $contest->competition_division_id) {
                throw ValidationException::withMessages(['contest_id' => 'The selected match belongs to a different division.']);
            }
            $data['competition_division_id'] = $contest->competition_division_id;
        }
        $division = Division::query()->with('competition')->findOrFail($data['competition_division_id']);
        $this->assertBelongsToEvent($division->competition?->event_id, $event);

        if (! empty($data['venue_id'])) {
            $venue = Venue::query()->findOrFail($data['venue_id']);
            $this->assertBelongsToEvent($venue->event_id, $event);
        }

        return $data;
    }

    private function assertAdmin(Request $request, Event $event): void
    {
        if (! $request->user()->hasAdminAccess($event)) {
            throw new AuthorizationException('The active Global Admin is required.');
        }
    }

    private function assertMutableEvent(Event $event): void
    {
        if ($event->isArchived()) {
            throw new AuthorizationException('Archived Events are read-only.');
        }
    }

    private function assertBelongsToEvent(mixed $eventId, Event $event): void
    {
        if ((string) $eventId !== (string) $event->getKey()) {
            throw new AuthorizationException('The selected record does not belong to this Event.');
        }
    }

    private function assertScheduleGraph(Schedule $schedule, Event $event): void
    {
        $schedule->loadMissing('division.competition', 'contest.division.competition');
        $this->assertBelongsToEvent($schedule->division?->competition?->event_id, $event);

        if ($schedule->contest !== null) {
            $this->assertBelongsToEvent($schedule->contest->division?->competition?->event_id, $event);
            if ((int) $schedule->contest->competition_division_id !== (int) $schedule->competition_division_id) {
                throw new AuthorizationException('The schedule and bracket match belong to different divisions.');
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function adminCover(Event $event, ?CompetitionCoverImage $cover): ?array
    {
        if ($cover === null) {
            return null;
        }

        return [
            'id' => (string) $cover->getKey(),
            'revision' => $cover->revision,
            'alt_text' => $cover->alt_text,
            'width' => $cover->width,
            'height' => $cover->height,
            'state' => $cover->state instanceof PublicationState ? $cover->state->value : (string) $cover->state,
            'preview_url' => route('admin.cover-images.preview', [$event, $cover]),
            'public_url' => $cover->public_path === null ? null : Storage::disk('public')->url($cover->public_path),
            'published_at' => $cover->published_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function adminSchedulePublication(?SchedulePublication $publication): ?array
    {
        if ($publication === null) {
            return null;
        }

        return [
            'id' => (string) $publication->getKey(),
            'revision' => $publication->revision,
            'title' => $publication->title,
            'starts_at' => $publication->starts_at?->toIso8601String(),
            'ends_at' => $publication->ends_at?->toIso8601String(),
            'status' => $publication->status instanceof ScheduleStatus
                ? $publication->status->value
                : (string) $publication->status,
            'venue_name' => $publication->venue_name,
            'venue_location' => $publication->venue_location,
            'published_at' => $publication->published_at?->toIso8601String(),
        ];
    }

    private function scheduleHasUnpublishedChanges(Schedule $schedule): bool
    {
        return $schedule->hasUnpublishedChanges();
    }

    /** @return array<string, mixed> */
    private function venueAuditData(Venue $venue): array
    {
        return [
            'name' => $venue->name,
            'code' => $venue->code,
            'location' => $venue->location,
            'is_active' => (bool) $venue->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function scheduleAuditData(Schedule $schedule): array
    {
        return [
            'competition_division_id' => (string) $schedule->competition_division_id,
            'venue_id' => $schedule->venue_id === null ? null : (string) $schedule->venue_id,
            'title' => $schedule->title,
            'starts_at' => $schedule->starts_at?->toIso8601String(),
            'ends_at' => $schedule->ends_at?->toIso8601String(),
            'status' => $schedule->status instanceof ScheduleStatus
                ? $schedule->status->value
                : (string) $schedule->status,
        ];
    }
}
