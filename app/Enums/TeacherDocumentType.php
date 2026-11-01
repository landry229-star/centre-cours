<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TeacherDocumentType: string
{
    use HasOptions;

    case Contract = 'contract';
    case Diploma = 'diploma';
    case Identity = 'identity';
    case Certificate = 'certificate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Contrat',
            self::Diploma => 'Diplôme',
            self::Identity => 'Pièce d’identité',
            self::Certificate => 'Attestation / certificat',
            self::Other => 'Autre',
        };
    }
}
