<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStatusTransitionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    /**
     * @return array<string, array{0: OrderStatus, 1: OrderStatus}>
     */
    public static function allowedTransitions(): array
    {
        return [
            'pending → paid' => [OrderStatus::PendingPayment, OrderStatus::Paid],
            'pending → payment_failed' => [OrderStatus::PendingPayment, OrderStatus::PaymentFailed],
            'pending → cancelled' => [OrderStatus::PendingPayment, OrderStatus::Cancelled],
            'payment_failed → pending (retry)' => [OrderStatus::PaymentFailed, OrderStatus::PendingPayment],
            'payment_failed → cancelled' => [OrderStatus::PaymentFailed, OrderStatus::Cancelled],
        ];
    }

    /**
     * Every pair that is not in allowedTransitions().
     *
     * @return array<string, array{0: OrderStatus, 1: OrderStatus}>
     */
    public static function forbiddenTransitions(): array
    {
        $allowed = array_map(
            fn (array $pair): string => $pair[0]->value.'→'.$pair[1]->value,
            self::allowedTransitions(),
        );

        $forbidden = [];
        foreach (OrderStatus::cases() as $from) {
            foreach (OrderStatus::cases() as $to) {
                $name = $from->value.'→'.$to->value;
                if (! in_array($name, $allowed, true)) {
                    $forbidden[$name] = [$from, $to];
                }
            }
        }

        return $forbidden;
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transitions_are_accepted(OrderStatus $from, OrderStatus $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
        $this->assertSame($to, $from->transitionTo($to));
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_forbidden_transitions_are_rejected(OrderStatus $from, OrderStatus $to): void
    {
        $this->assertFalse($from->canTransitionTo($to));
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_a_forbidden_transition_throws_a_domain_exception(OrderStatus $from, OrderStatus $to): void
    {
        try {
            $from->transitionTo($to);
            $this->fail('Expected InvalidOrderStatusTransitionException.');
        } catch (InvalidOrderStatusTransitionException $e) {
            $this->assertSame($from, $e->from);
            $this->assertSame($to, $e->to);
        }
    }

    public function test_paid_and_cancelled_are_terminal(): void
    {
        $this->assertSame([], OrderStatus::Paid->allowedTransitions());
        $this->assertSame([], OrderStatus::Cancelled->allowedTransitions());
    }

    public function test_paid_cannot_be_cancelled(): void
    {
        $this->expectException(InvalidOrderStatusTransitionException::class);

        OrderStatus::Paid->transitionTo(OrderStatus::Cancelled);
    }
}
