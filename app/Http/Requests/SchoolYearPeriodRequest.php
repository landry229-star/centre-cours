<?php

namespace App\Http\Requests;

use App\Models\SchoolYear;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SchoolYearPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'periods' => ['required', 'array:trimester,semester,annual'],
            'periods.trimester' => ['required', 'array', 'size:3'],
            'periods.semester' => ['required', 'array', 'size:2'],
            'periods.annual' => ['required', 'array', 'size:1'],
            'periods.trimester.*.start_date' => ['required', 'date_format:Y-m-d'],
            'periods.trimester.*.end_date' => ['required', 'date_format:Y-m-d'],
            'periods.semester.*.start_date' => ['required', 'date_format:Y-m-d'],
            'periods.semester.*.end_date' => ['required', 'date_format:Y-m-d'],
            'periods.annual.*.start_date' => ['required', 'date_format:Y-m-d'],
            'periods.annual.*.end_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $schoolYear = $this->route('schoolYear');

                if (! $schoolYear instanceof SchoolYear) {
                    return;
                }

                $periods = $this->validated('periods');
                $yearStart = $schoolYear->start_date->toDateString();
                $yearEnd = $schoolYear->end_date->toDateString();

                foreach (['trimester' => 3, 'semester' => 2, 'annual' => 1] as $type => $count) {
                    $rows = $periods[$type];
                    ksort($rows);

                    if (array_keys($rows) !== range(1, $count)
                        || $rows[1]['start_date'] !== $yearStart
                        || $rows[$count]['end_date'] !== $yearEnd) {
                        $validator->errors()->add('periods', 'Chaque rythme doit couvrir toute l’année scolaire, du début à la fin.');

                        return;
                    }

                    foreach ($rows as $number => $row) {
                        if (CarbonImmutable::parse($row['end_date'])->lessThan(CarbonImmutable::parse($row['start_date']))) {
                            $validator->errors()->add("periods.{$type}.{$number}.end_date", 'La date de fin doit être postérieure ou égale à la date de début.');

                            return;
                        }
                    }

                    for ($number = 1; $number < $count; $number++) {
                        if (CarbonImmutable::parse($rows[$number]['end_date'])->addDay()->toDateString()
                            !== $rows[$number + 1]['start_date']) {
                            $validator->errors()->add('periods', 'Les périodes doivent se suivre sans chevauchement ni intervalle vide.');

                            return;
                        }
                    }
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'periods.*.*.start_date' => 'date de début',
            'periods.*.*.end_date' => 'date de fin',
        ];
    }
}
