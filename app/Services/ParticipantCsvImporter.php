<?php

namespace App\Services;

use App\Actions\Registrations\SaveParticipant;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ParticipantCsvImporter
{
    /** @var list<string> */
    private const TARGETS = ['department_code', 'student_number', 'display_name', 'given_name', 'family_name', 'email', 'phone', 'private_notes', 'active'];

    /** @var array<string, list<string>> */
    private const ALIASES = [
        'department_code' => ['department_code', 'department', 'delegation', 'delegation_code', 'team'],
        'student_number' => ['student_number', 'student id', 'student_id', 'id number', 'student no', 'student no.'],
        'display_name' => ['display_name', 'full name', 'full_name', 'name', 'player name', 'participant'],
        'given_name' => ['given_name', 'first name', 'first_name', 'given name'],
        'family_name' => ['family_name', 'last name', 'last_name', 'surname', 'family name'],
        'email' => ['email', 'email address'],
        'phone' => ['phone', 'phone number', 'mobile'],
        'private_notes' => ['private_notes', 'notes', 'private notes'],
        'active' => ['active', 'is_active', 'enabled'],
    ];

    public function inspect(UploadedFile $file, Event $event, ?EventDelegation $department = null, array $mapping = []): array
    {
        [$headers, $rows] = $this->read($file);
        $resolved = $this->resolveMapping($headers, $mapping, $department !== null);
        $errors = $resolved['errors'];
        $parsed = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            if ($this->blank($row)) {
                continue;
            }
            if (count($row) !== count($headers)) {
                $rowErrors = [['line' => $line, 'message' => 'The row has a different number of columns than the header.']];
                $errors = [...$errors, ...$rowErrors];
                $parsed[] = ['line' => $line, 'record' => [], 'state' => 'error', 'errors' => $rowErrors];

                continue;
            }
            $record = $this->record($row, $headers, $resolved['mapping'], $department);
            $rowErrors = $this->validateRecord($record, $line, $event, $department, $seen);
            if ($rowErrors !== []) {
                $errors = [...$errors, ...$rowErrors];
            }
            $normalized = $this->normalizeStudentNumber($record['student_number'] ?? null);
            if ($normalized !== null) {
                $seen[$normalized] = $line;
            }
            $parsed[] = ['line' => $line, 'record' => $record, 'state' => $rowErrors === [] ? 'new' : 'error', 'errors' => $rowErrors];
        }

        if (count($parsed) > 1000) {
            $errors[] = ['line' => null, 'message' => 'A CSV import can contain at most 1,000 nonblank rows.'];
        }

        $existing = [];
        foreach ($parsed as &$item) {
            if ($item['state'] === 'error') {
                continue;
            }
            $number = $this->normalizeStudentNumber($item['record']['student_number'] ?? null);
            if ($number === null) {
                continue;
            }
            $participant = Participant::query()->where('event_id', $event->getKey())->where('student_number_normalized', $number)->first();
            if ($participant === null) {
                continue;
            }
            $recordDepartmentId = $item['record']['event_delegation_id'] ?? null;
            $sameDepartment = $recordDepartmentId !== null
                && (int) $participant->event_delegation_id === (int) $recordDepartmentId;
            if (! $sameDepartment) {
                $item['state'] = 'error';
                $item['errors'][] = ['line' => $item['line'], 'message' => 'This student number already belongs to another department.'];
                $errors[] = ['line' => $item['line'], 'message' => 'This student number already belongs to another department.'];
            } else {
                $item['state'] = 'already_exists';
                $item['participant_id'] = (string) $participant->getKey();
                $existing[] = (string) $participant->getKey();
            }
        }
        unset($item);

        return [
            'headers' => $headers,
            'mapping' => $resolved['mapping'],
            'rows' => $parsed,
            'errors' => array_values($errors),
            'new_count' => collect($parsed)->where('state', 'new')->count(),
            'existing_count' => collect($parsed)->where('state', 'already_exists')->count(),
            'existing_ids' => array_values(array_unique($existing)),
        ];
    }

    /** @return array{created_ids: list<string>, existing_ids: list<string>, count: int} */
    public function import(User $actor, Event $event, UploadedFile $file, ?EventDelegation $department, array $mapping, SaveParticipant $save): array
    {
        $preview = $this->inspect($file, $event, $department, $mapping);
        if ($preview['errors'] !== []) {
            throw ValidationException::withMessages(['file' => 'Fix the CSV errors before importing.']);
        }

        return DB::transaction(function () use ($actor, $event, $department, $preview, $save): array {
            $created = [];
            $existing = $preview['existing_ids'];
            foreach ($preview['rows'] as $item) {
                if ($item['state'] === 'already_exists') {
                    continue;
                }
                $record = $item['record'];
                $participant = $save->handle($actor, $event, [
                    'event_delegation_id' => $department?->getKey() ?? $record['event_delegation_id'],
                    'display_name' => $record['display_name'],
                    'given_name' => $record['given_name'],
                    'family_name' => $record['family_name'],
                    'student_number' => $record['student_number'],
                    'email' => $record['email'],
                    'phone' => $record['phone'],
                    'private_notes' => $record['private_notes'],
                    'is_active' => $record['active'],
                ]);
                $created[] = (string) $participant->getKey();
            }

            return ['created_ids' => $created, 'existing_ids' => array_values(array_unique($existing)), 'count' => count($created)];
        });
    }

    /** @return list<string> */
    public function targets(): array
    {
        return self::TARGETS;
    }

    /** @return array{headers: list<string>, rows: list<array<int, string|int|null>>} */
    private function read(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw ValidationException::withMessages(['file' => 'The uploaded CSV could not be read.']);
        }
        $contents = file_get_contents($path);
        if ($contents === false || ! mb_check_encoding($contents, 'UTF-8')) {
            throw ValidationException::withMessages(['file' => 'The CSV must be valid UTF-8.']);
        }
        if (str_contains($contents, "\0")) {
            throw ValidationException::withMessages(['file' => 'The CSV contains an invalid control character.']);
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded CSV could not be opened.']);
        }
        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'The CSV must include a header row.']);
        }
        $headers = array_map(function ($value): string {
            return trim(ltrim((string) $value, "\xEF\xBB\xBF"));
        }, $header);
        if (in_array('', $headers, true)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'Every CSV column needs a header.']);
        }
        $normalizedHeaders = array_map(fn (string $value): string => $this->normalizeHeader($value), $headers);
        if (count($normalizedHeaders) !== count(array_unique($normalizedHeaders))) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'CSV headers must be unique.']);
        }
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(static fn ($value): string => trim((string) $value), $row);
        }
        fclose($handle);

        return [$headers, $rows];
    }

    /** @return array{mapping: array<string, string>, errors: list<array{line: null, message: string}>} */
    private function resolveMapping(array $headers, array $mapping, bool $departmentScoped): array
    {
        $normalized = array_map(fn (string $header): string => $this->normalizeHeader($header), $headers);
        $errors = [];
        $resolved = array_fill_keys(self::TARGETS, null);
        $used = [];
        foreach (self::TARGETS as $target) {
            if ($departmentScoped && $target === 'department_code') {
                continue;
            }
            $source = $mapping[$target] ?? null;
            if ($source === null || $source === '') {
                foreach (self::ALIASES[$target] as $alias) {
                    $index = array_search($this->normalizeHeader($alias), $normalized, true);
                    if ($index !== false) {
                        $source = $headers[$index];
                        break;
                    }
                }
            }
            if ($source !== null && $source !== '') {
                if (! in_array($source, $headers, true)) {
                    $errors[] = ['line' => null, 'message' => "The mapped column for {$target} is not in the file."];
                } elseif (in_array($source, $used, true)) {
                    $errors[] = ['line' => null, 'message' => "A CSV column can only be mapped once ({$source})."];
                } else {
                    $resolved[$target] = $source;
                    $used[] = $source;
                }
            }
        }
        foreach (['student_number', 'display_name'] as $required) {
            if (! isset($resolved[$required])) {
                $errors[] = ['line' => null, 'message' => "Map a {$required} column before continuing."];
            }
        }

        return ['mapping' => $resolved, 'errors' => $errors];
    }

    /** @return array<string, mixed> */
    private function record(array $row, array $headers, array $mapping, ?EventDelegation $department): array
    {
        $values = array_combine($headers, array_pad($row, count($headers), '')) ?: [];
        $value = static fn (string $target): ?string => isset($mapping[$target]) ? trim((string) ($values[$mapping[$target]] ?? '')) : null;

        return [
            'event_delegation_id' => $department?->getKey(),
            'department_code' => $value('department_code'),
            'student_number' => $value('student_number'),
            'display_name' => $value('display_name'),
            'given_name' => $value('given_name'),
            'family_name' => $value('family_name'),
            'email' => $value('email'),
            'phone' => $value('phone'),
            'private_notes' => $value('private_notes'),
            'active' => $value('active') === null || $value('active') === '' ? true : in_array(strtolower((string) $value('active')), ['1', 'true', 'yes', 'y'], true),
            'active_raw' => $value('active'),
        ];
    }

    /** @param array<string, int> $seen @return list<array{line: int, message: string}> */
    private function validateRecord(array &$record, int $line, Event $event, ?EventDelegation $department, array $seen): array
    {
        $errors = [];
        $number = $this->normalizeStudentNumber($record['student_number'] ?? null);
        if ($number === null) {
            $errors[] = ['line' => $line, 'message' => 'Student number is required.'];
        }
        if (($record['display_name'] ?? '') === '') {
            $errors[] = ['line' => $line, 'message' => 'Display name is required.'];
        }
        if ($number !== null && isset($seen[$number])) {
            $errors[] = ['line' => $line, 'message' => "Student number duplicates row {$seen[$number]}."];
        }
        if (($record['email'] ?? null) !== null && $record['email'] !== '' && filter_var($record['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = ['line' => $line, 'message' => 'Email address is invalid.'];
        }
        if (($record['active_raw'] ?? null) !== null && $record['active_raw'] !== '' && ! in_array(strtolower((string) $record['active_raw']), ['1', '0', 'true', 'false', 'yes', 'no', 'y', 'n'], true)) {
            $errors[] = ['line' => $line, 'message' => 'Active must be true or false.'];
        }
        foreach (['display_name' => 255, 'given_name' => 255, 'family_name' => 255, 'student_number' => 100, 'phone' => 80, 'private_notes' => 4000] as $field => $max) {
            if (mb_strlen((string) ($record[$field] ?? '')) > $max) {
                $errors[] = ['line' => $line, 'message' => "{$field} is longer than {$max} characters."];
            }
        }
        if ($department === null) {
            $code = strtoupper(trim((string) ($record['department_code'] ?? '')));
            $match = $event->delegations()->whereRaw('UPPER(abbreviation) = ?', [$code])->where('is_active', true)->first();
            if ($match === null) {
                $errors[] = ['line' => $line, 'message' => 'Department code is unknown or inactive.'];
            } else {
                $record['event_delegation_id'] = $match->getKey();
            }
        }

        return $errors;
    }

    private function normalizeHeader(string $value): string
    {
        return strtolower(trim(str_replace(['-', '_'], ' ', $value)));
    }

    private function normalizeStudentNumber(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_strtoupper($value);
    }

    private function blank(array $row): bool
    {
        return collect($row)->every(static fn ($value): bool => trim((string) $value) === '');
    }
}
