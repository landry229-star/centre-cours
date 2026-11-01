<?php

namespace App\Services\Academics;

use App\Models\Subject;
use DomainException;

class SubjectService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): Subject
    {
        return Subject::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Subject $subject, array $data): Subject
    {
        $subject->update($data);

        return $subject;
    }

    public function delete(Subject $subject): void
    {
        if ($subject->assignments()->exists()) {
            throw new DomainException('Impossible de supprimer cette matière : elle est enseignée dans au moins une classe.');
        }

        $subject->delete();
    }
}
