<?php

declare(strict_types=1);

namespace Tests\Unit\Payment;

use App\Services\Payment\WebhookSignature;
use App\Services\Payment\WebhookSignatureVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Uses the Laravel TestCase because the verifier reads the clock and logs.
 */
class WebhookSignatureVerifierTest extends TestCase
{
    private const SECRET = 'unit-test-secret';

    private const BODY = '{"id":"evt_1"}';

    private function verifier(string $secret = self::SECRET): WebhookSignatureVerifier
    {
        return new WebhookSignatureVerifier($secret, toleranceSeconds: 300);
    }

    private function signatureAt(int $timestamp, string $body = self::BODY): string
    {
        return WebhookSignature::sign((string) $timestamp, $body, self::SECRET);
    }

    public function test_the_signature_is_hmac_sha256_of_timestamp_dot_body(): void
    {
        $this->assertSame(
            hash_hmac('sha256', '1700000000.'.self::BODY, self::SECRET),
            WebhookSignature::sign('1700000000', self::BODY, self::SECRET),
        );
    }

    public function test_a_valid_signature_is_accepted(): void
    {
        $now = now()->getTimestamp();

        $this->assertTrue($this->verifier()->verify($this->signatureAt($now), (string) $now, self::BODY));
    }

    /**
     * @return array<string, array{0: int, 1: bool}>
     */
    public static function ages(): array
    {
        return [
            '300 s old' => [300, true],
            '301 s old' => [301, false],
            '300 s ahead' => [-300, true],
            '301 s ahead' => [-301, false],
        ];
    }

    #[DataProvider('ages')]
    public function test_the_timestamp_window_is_five_minutes_both_ways(int $ageSeconds, bool $accepted): void
    {
        $timestamp = now()->getTimestamp() - $ageSeconds;

        $this->assertSame(
            $accepted,
            $this->verifier()->verify($this->signatureAt($timestamp), (string) $timestamp, self::BODY),
        );
    }

    public function test_a_different_body_is_rejected(): void
    {
        $now = now()->getTimestamp();

        $this->assertFalse($this->verifier()->verify($this->signatureAt($now), (string) $now, '{"id":"evt_2"}'));
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function badHeaders(): array
    {
        return [
            'no signature' => [null, 'now'],
            'empty signature' => ['', 'now'],
            'no timestamp' => ['sig', null],
            'non-numeric timestamp' => ['sig', 'yesterday'],
            'negative timestamp' => ['sig', '-1'],
        ];
    }

    #[DataProvider('badHeaders')]
    public function test_missing_or_malformed_headers_are_rejected(?string $signature, ?string $timestamp): void
    {
        $now = now()->getTimestamp();
        $timestamp = $timestamp === 'now' ? (string) $now : $timestamp;
        $signature = $signature === 'sig' ? $this->signatureAt($now) : $signature;

        $this->assertFalse($this->verifier()->verify($signature, $timestamp, self::BODY));
    }

    public function test_an_empty_secret_rejects_everything(): void
    {
        $now = now()->getTimestamp();
        $signedWithEmptySecret = WebhookSignature::sign((string) $now, self::BODY, '');

        $this->assertFalse($this->verifier('')->verify($signedWithEmptySecret, (string) $now, self::BODY));
    }
}
