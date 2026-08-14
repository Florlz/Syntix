<?php

namespace App\Services;

use App\Enums\EntryStatus;
use App\Models\Discipline;
use App\Models\Division;
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
                ->whereHas('entry', fn ($query) => $query->where('status', '!=', EntryStatus::Locked->value))
                ->exists();

            if ($missingParent) {
                $errors[] = 'Every discipline Entry requires a locked parent Entry.';
            }
        }

        return array_values(array_unique($errors));
    }
}
