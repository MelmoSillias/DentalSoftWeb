<?php

namespace App\Reporting\Domain\Model;

use App\Reporting\Domain\Exception\ReportingDomainException;
use App\Reporting\Domain\ValueObject\ReportRequestId;

/**
 * Read-model domain object describing a report request (no persistence entity).
 */
final class ReportRequest
{
    private const ALLOWED_PERIODS = ['today', 'week', 'month', 'year', 'custom'];

    private function __construct(
        private ReportRequestId $id,
        private string $period,
        private ?string $start,
        private ?string $end,
        private ?int $employeeId,
    ) {
        if (!in_array($this->period, self::ALLOWED_PERIODS, true)) {
            throw new ReportingDomainException(sprintf('Unsupported report period "%s".', $this->period));
        }
    }

    public static function create(
        string $period,
        ?string $start = null,
        ?string $end = null,
        ?int $employeeId = null,
    ): self {
        $id = ReportRequestId::fromString(sprintf(
            '%s:%s:%s:%s',
            $period,
            $start ?? '-',
            $end ?? '-',
            $employeeId ?? 'all'
        ));

        return new self($id, $period, $start, $end, $employeeId);
    }

    public function getId(): ReportRequestId
    {
        return $this->id;
    }

    public function getPeriod(): string
    {
        return $this->period;
    }

    public function getStart(): ?string
    {
        return $this->start;
    }

    public function getEnd(): ?string
    {
        return $this->end;
    }

    public function getEmployeeId(): ?int
    {
        return $this->employeeId;
    }
}
