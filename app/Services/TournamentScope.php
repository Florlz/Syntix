<?php

namespace App\Services;

use App\Enums\EntryStatus;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\Entry;
use Illuminate\Support\Collection;

final class TournamentScope
{
    public function __construct(
        public readonly Division $division,
        public readonly ?Discipline $discipline = null,
    ) {
        if ($discipline !== null && (int) $discipline->competition_division_id !== (int) $division->getKey()) {
            throw new \InvalidArgumentException('The discipline does not belong to the selected Division.');
        }
    }

    public function label(): string
    {
        return $this->discipline === null
            ? $this->division->name
            : $this->division->name.' · '.$this->discipline->name;
    }

    public function eligibleEntryIds(): Collection
    {
        if ($this->discipline === null) {
            return $this->division->entries()
                ->where('status', EntryStatus::Locked->value)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id);
        }

        return $this->discipline->disciplineEntries()
            ->where('state', 'locked')
            ->whereHas('entry', fn ($query) => $query->where('status', EntryStatus::Locked->value))
            ->orderBy('id')
            ->pluck('entry_id')
            ->map(fn ($id): int => (int) $id);
    }

    public function participatingEntryIds(): Collection
    {
        if ($this->discipline === null) {
            return $this->division->entries()
                ->whereNotIn('status', [
                    EntryStatus::Withdrawn->value,
                    EntryStatus::Disqualified->value,
                ])
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id);
        }

        return $this->discipline->disciplineEntries()
            ->whereHas('entry', fn ($query) => $query->whereNotIn('status', [
                EntryStatus::Withdrawn->value,
                EntryStatus::Disqualified->value,
            ]))
            ->orderBy('entry_id')
            ->pluck('entry_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    public function unreadyEntryIds(): Collection
    {
        return $this->participatingEntryIds()
            ->diff($this->eligibleEntryIds())
            ->values();
    }

    /** @return list<string> */
    public function generationErrors(): array
    {
        $errors = $this->readinessErrors();
        $required = $this->participatingEntryIds();
        $eligible = $this->eligibleEntryIds();

        if ($required->isEmpty()) {
            $errors[] = $this->discipline === null
                ? 'No teams are currently participating in this division.'
                : 'No teams have been added to this event yet.';
        } elseif ($required->diff($eligible)->isNotEmpty()) {
            $errors[] = $this->discipline === null
                ? 'Approve every participating team sheet before making the draw.'
                : "Approve every team's team sheet and lineup for this event before making the draw.";
        }

        return array_values(array_unique($errors));
    }

    public function assertReadyForGeneration(): void
    {
        $this->lockScopeForGeneration();
        $errors = $this->generationErrors();

        if ($errors !== []) {
            throw new \DomainException($errors[0]);
        }
    }

    private function lockScopeForGeneration(): void
    {
        if ($this->discipline === null) {
            $this->division->entries()->lockForUpdate()->get();

            return;
        }

        $entryIds = $this->discipline->disciplineEntries()
            ->lockForUpdate()
            ->pluck('entry_id');

        Entry::query()
            ->whereKey($entryIds)
            ->lockForUpdate()
            ->get();
    }

    /** @param list<int|string> $drawOrder */
    public function assertDrawOrder(array $drawOrder): void
    {
        $this->assertReadyForGeneration();

        $actual = collect($drawOrder)
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($actual->count() !== $actual->unique()->count()) {
            throw new \DomainException('The draw order cannot contain duplicate teams.');
        }

        $expected = $this->eligibleEntryIds()
            ->sort()
            ->values();

        if ($actual->sort()->values()->all() !== $expected->all()) {
            throw new \DomainException('The draw must include every eligible team exactly once.');
        }
    }

    public function tournamentQuery()
    {
        return $this->division->tournaments()->when(
            $this->discipline === null,
            fn ($query) => $query->whereNull('discipline_id'),
            fn ($query) => $query->where('discipline_id', $this->discipline->getKey()),
        );
    }

    /** @return list<string> */
    public function readinessErrors(): array
    {
        $errors = [];
        $rule = $this->division->governingRuleVersion;

        if ($rule === null) {
            $errors[] = 'A governing rule version is required.';
        } else {
            $errors = array_merge($errors, $rule->readinessErrors());
        }

        if ($this->discipline !== null) {
            $missingParent = $this->discipline->disciplineEntries()
                ->where('state', 'locked')
                ->whereHas('entry', fn ($query) => $query
                    ->whereNotIn('status', [
                        EntryStatus::Withdrawn->value,
                        EntryStatus::Disqualified->value,
                    ])
                    ->where('status', '!=', EntryStatus::Locked->value))
                ->exists();

            if ($missingParent) {
                $errors[] = "Approve this team's team sheet before approving its lineup for this event.";
            }
        }

        return array_values(array_unique($errors));
    }
}
