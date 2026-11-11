<?php

declare(strict_types=1);

namespace App\Services\OrderNotes;

use App\Contracts\OrderNoteExtractor;
use App\Data\ExtractedOrderNote;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * No network calls. Used in tests, and when no ANTHROPIC_API_KEY is configured.
 *
 * By default it returns nothing with confidence 0.0, so an app running on the fake
 * by mistake sends every note to review instead of applying made-up data.
 */
class FakeOrderNoteExtractor implements OrderNoteExtractor
{
    private ?ExtractedOrderNote $result = null;

    private ?Throwable $exception = null;

    /** @var list<array{note: string, today: CarbonImmutable}> */
    private array $calls = [];

    public function extract(string $note, CarbonImmutable $today): ExtractedOrderNote
    {
        $this->calls[] = ['note' => $note, 'today' => $today];

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->result ?? new ExtractedOrderNote(
            deliveryDate: null,
            poNumber: null,
            invoiceAddress: null,
            confidence: 0.0,
        );
    }

    public function willReturn(ExtractedOrderNote $result): self
    {
        $this->result = $result;
        $this->exception = null;

        return $this;
    }

    public function willThrow(Throwable $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    /**
     * @return list<array{note: string, today: CarbonImmutable}>
     */
    public function calls(): array
    {
        return $this->calls;
    }
}
