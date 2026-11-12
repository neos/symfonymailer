<?php

declare(strict_types=1);

namespace Neos\SymfonyMailer\Tests\Unit\Service;

/*
 * This file is part of the Neos.SymfonyMailer package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\SymfonyMailer\Exception\InvalidMailerConfigurationException;
use Neos\SymfonyMailer\Service\MailerService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Event\MessageEvent;

class MailerServiceDkimTest extends TestCase
{
    private const PASSPHRASE = 'correct horse';

    /** @var array<string, string> generating a key is slow, so each kind is made once */
    private static array $keys = [];

    /**
     * @test
     */
    public function emptyConfigurationReturnsNull(): void
    {
        self::assertNull($this->createDispatcher([]));
        self::assertNull($this->createDispatcher(['domain' => null, 'selector' => '', 'privateKey' => null]));
    }

    /**
     * @test
     */
    public function nullConfigurationBlockReturnsNull(): void
    {
        self::assertNull($this->createDispatcher(null));
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function incompleteConfigurations(): array
    {
        return [
            'only domain' => [['domain' => 'example.com']],
            'missing selector' => [['domain' => 'example.com', 'privateKey' => 'irrelevant']],
            'missing private key' => [['domain' => 'example.com', 'selector' => 'default']],
        ];
    }

    /**
     * @test
     * @dataProvider incompleteConfigurations
     * @param array<string, string> $configuration
     */
    public function incompleteConfigurationThrows(array $configuration): void
    {
        $this->expectException(InvalidMailerConfigurationException::class);
        $this->expectExceptionCode(1762900001);

        $this->createDispatcher($configuration);
    }

    /**
     * @test
     */
    public function nonStringPassphraseThrows(): void
    {
        $this->expectException(InvalidMailerConfigurationException::class);
        $this->expectExceptionCode(1794473054);

        $this->createDispatcher($this->configuration(self::key(2048), null) + ['passphrase' => ['not', 'a string']]);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function emptyPassphrases(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            // what an unset %env:NAME% setting resolves to
            'false' => [false],
        ];
    }

    /**
     * @test
     * @dataProvider emptyPassphrases
     */
    public function emptyPassphraseIsAcceptedForUnencryptedKey(mixed $passphrase): void
    {
        $dispatcher = $this->createDispatcher($this->configuration(self::key(2048)) + ['passphrase' => $passphrase]);

        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
    }

    /**
     * @test
     */
    public function emptyPassphraseIsRejectedForEncryptedKey(): void
    {
        $this->expectException(InvalidMailerConfigurationException::class);
        $this->expectExceptionCode(1794473025);

        $this->createDispatcher($this->configuration(self::key(2048, self::PASSPHRASE)) + ['passphrase' => false]);
    }

    /**
     * @test
     */
    public function nonArraySignerOptionsThrow(): void
    {
        $this->expectException(InvalidMailerConfigurationException::class);
        $this->expectExceptionCode(1794473322);

        $this->createDispatcher($this->configuration(self::key(2048)) + ['signerOptions' => 'ed25519-sha256']);
    }

    /**
     * @test
     */
    public function invalidPemThrows(): void
    {
        $this->expectException(InvalidMailerConfigurationException::class);
        $this->expectExceptionCode(1794473025);

        $this->createDispatcher($this->configuration('-----BEGIN PRIVATE KEY-----not a key-----END PRIVATE KEY-----'));
    }

    /**
     * @test
     */
    public function incorrectPassphraseThrows(): void
    {
        $this->expectException(InvalidMailerConfigurationException::class);
        $this->expectExceptionCode(1794473025);

        $this->createDispatcher($this->configuration(self::key(2048, self::PASSPHRASE), 'wrong passphrase'));
    }

    /**
     * @test
     */
    public function rsaKeyOf2048BitsReturnsSigningDispatcher(): void
    {
        $dispatcher = $this->createDispatcher($this->configuration(self::key(2048)));

        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        self::assertNotEmpty($dispatcher->getListeners(MessageEvent::class));
    }

    /**
     * @test
     */
    public function encryptedKeyWithCorrectPassphraseReturnsSigningDispatcher(): void
    {
        $dispatcher = $this->createDispatcher(
            $this->configuration(self::key(2048, self::PASSPHRASE), self::PASSPHRASE)
        );

        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        self::assertNotEmpty($dispatcher->getListeners(MessageEvent::class));
    }

    /**
     * @param array<string, mixed>|null $dkimConfiguration
     */
    private function createDispatcher(?array $dkimConfiguration): ?EventDispatcherInterface
    {
        // The constructor is overridden on purpose: Flow's proxy constructor would try to inject the
        // real settings, and the test supplies its own.
        $service = new class ($dkimConfiguration) extends MailerService {
            public function __construct(?array $dkimConfiguration)
            {
                $this->dkimConfiguration = $dkimConfiguration;
            }

            public function dispatcher(): ?EventDispatcherInterface
            {
                return $this->createDkimSigningEventDispatcher();
            }
        };

        return $service->dispatcher();
    }

    /**
     * @return array<string, string>
     */
    private function configuration(string $privateKey, ?string $passphrase = null): array
    {
        return array_filter(
            [
                'domain' => 'example.com',
                'selector' => 'default',
                'privateKey' => $privateKey,
                'passphrase' => $passphrase,
            ],
            static fn ($value) => $value !== null
        );
    }

    private static function key(int $bits, ?string $passphrase = null): string
    {
        return self::$keys[$bits . ':' . $passphrase] ??= self::generateKey($bits, $passphrase);
    }

    private static function generateKey(int $bits, ?string $passphrase): string
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => $bits]);
        self::assertNotFalse($key, 'The test environment cannot generate RSA keys');
        self::assertTrue(openssl_pkey_export($key, $pem, $passphrase));

        return $pem;
    }
}
