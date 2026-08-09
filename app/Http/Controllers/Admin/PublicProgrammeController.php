<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\PublicationState;
use App\Enums\ScheduleStatus;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionCoverImage;
use App\Models\Division;
use App\Models\Event;
use App\Models\Schedule;
use App\Models\SchedulePublication;
use App\Models\Venue;
use App\Services\AuditLogger;
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
    public function index(Request $request, Event $event): Response
    {
        $this->assertAdmin($request, $event);
        $event->load([
            'competitions' => fn ($query) => $query->orderBy('name'),
            'competitions.divisions' => fn ($query) => $query->orderBy('name'),
            'competitions.draftCoverImage',
            'competitions.publishedCoverImage',
            'venues' => fn ($query) => $query->orderBy('name'),
            'schedules' => fn ($query) => $query->orderBy('starts_at'),
            'schedules.division.competition',
            'schedules.venue',
            'schedules.currentPublication',
        ]);

        return Inertia::render('Admin/Events/PublicProgramme', [
            'event' => [
                'id' => (string) $event->getKey(),
                'name' => $event->name,
                'state' => $event->eventState()->value,
                'archived' => $event->isArchived(),
            ],
            'competitions' => $event->competitions->map(fn (Competition $competition) => [
                'id' => (string) $competition->getKey(),
                'name' => $competition->name,
                'divisions' => $competition->divisions->map(fn (Division $division) => [
                    'id' => (string) $division->getKey(),
                    'name' => $division->name,
                ])->values()->all(),
                'draft_cover' => $this->adminCover($event, $competition->draftCoverImage),
                'published_cover' => $this->adminCover($event, $competition->publishedCoverImage),
            ])->values()->all(),
            'venues' => $event->venues->map(fn (Venue $venue) => [
                'id' => (string) $venue->getKey(),
                'name' => $venue->name,
                'code' => $venue->code,
                'location' => $venue->location,
                'description' => $venue->description,
                'is_active' => (bool) $venue->is_active,
            ])->values()->all(),
            'schedules' => $event->schedules->map(fn (Schedule $schedule) => [
                'id' => (string) $schedule->getKey(),
                'competition_division_id' => (string) $schedule->competition_division_id,
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
            'schedule_statuses' => array_map(
                static fn (ScheduleStatus $status): array => [
                    'value' => $status->value,
                    'label' => Str::headline($status->value),
                ],
                ScheduleStatus::cases(),
            ),
        ]);
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
        $before = $this->scheduleAuditData($schedule);
        $schedule->update($this->validateSchedule($request, $event));
        $audit->record($request->user(), AuditAction::ScheduleUpdated, $schedule, $event, before: $before, after: $this->scheduleAuditData($schedule));

        return back()->with('status', 'Schedule draft updated. Republish to change the public programme.');
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
    private function validateSchedule(Request $request, Event $event): array
    {
        $data = $request->validate([
            'competition_division_id' => ['required', 'integer', 'exists:competition_divisions,id'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['required', Rule::enum(ScheduleStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
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
        $publication = $schedule->currentPublication;

        if ($publication === null) {
            return true;
        }

        return $publication->competition_name !== $schedule->division?->competition?->name
            || $publication->division_name !== $schedule->division?->name
            || $publication->title !== $schedule->title
            || $publication->starts_at?->toIso8601String() !== $schedule->starts_at?->toIso8601String()
            || $publication->ends_at?->toIso8601String() !== $schedule->ends_at?->toIso8601String()
            || ($publication->status instanceof ScheduleStatus ? $publication->status->value : (string) $publication->status)
                !== ($schedule->status instanceof ScheduleStatus ? $schedule->status->value : (string) $schedule->status)
            || $publication->venue_name !== $schedule->venue?->name
            || $publication->venue_location !== $schedule->venue?->location;
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
