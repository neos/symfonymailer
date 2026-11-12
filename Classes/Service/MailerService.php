<?php

declare(strict_types=1);

namespace Neos\SymfonyMailer\Service;

/*
 * This file is part of the Neos.SymfonyMailer package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\SymfonyMailer\Exception\InvalidMailerConfigurationException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\EventListener\DkimSignedMessageListener;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Exception\InvalidArgumentException as MimeInvalidArgumentException;

#[Flow\Scope("singleton")]
class MailerService
{
    #[Flow\InjectConfiguration(path: "mailer", package: "Neos.SymfonyMailer")]
    protected array $mailerConfiguration;

    #[Flow\InjectConfiguration(path: "dkim", package: "Neos.SymfonyMailer")]
    protected array $dkimConfiguration = [];

    public const FORMAT_PLAINTEXT = 'text/plain';
    public const FORMAT_HTML = 'html';

    /**
     * Returns a mailer instance with the given transport or the configured default transport.
     *
     * @param TransportInterface|null $transport
     * @return Mailer
     * @throws InvalidMailerConfigurationException
     */
    public function getMailer(?TransportInterface $transport = null): Mailer
    {
        if ($transport !== null) {
            return new Mailer($transport);
        }

        // throw exception when dsn is not set
        if (!isset($this->mailerConfiguration['dsn'])) {
            throw new InvalidMailerConfigurationException('No DSN configured for Neos.SymfonyMailer', 1739540476);
        }

        return new Mailer(Transport::fromDsn($this->mailerConfiguration['dsn'], $this->createDkimSigningEventDispatcher()));
    }

    /**
     * Returns a dispatcher that DKIM-signs every message, or null when DKIM is not configured.
     *
     * @throws InvalidMailerConfigurationException when DKIM is only partially configured
     */
    protected function createDkimSigningEventDispatcher(): ?EventDispatcherInterface
    {
        $required = [
            'domain' => $this->dkimConfiguration['domain'] ?? null,
            'selector' => $this->dkimConfiguration['selector'] ?? null,
            'privateKey' => $this->dkimConfiguration['privateKey'] ?? null,
        ];

        if (count(array_filter($required, static fn ($value) => $value !== null && $value !== '')) === 0) {
            return null;
        }

        foreach ($required as $name => $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new InvalidMailerConfigurationException(
                    sprintf('Missing or invalid DKIM configuration "%s" for Neos.SymfonyMailer.', $name),
                    1762900001
                );
            }
        }

        $passphrase = $this->dkimConfiguration['passphrase'] ?? '';

        if (!is_string($passphrase)) {
            throw new InvalidMailerConfigurationException(
                'Invalid DKIM passphrase configuration for Neos.SymfonyMailer.',
                1794473054
            );
        }

        $signerOptions = $this->dkimConfiguration['signerOptions'] ?? [];
        if (!is_array($signerOptions)) {
            throw new InvalidMailerConfigurationException(
                'Invalid DKIM signerOptions configuration for Neos.SymfonyMailer.',
                1794473322
            );
        }

        try {
            $signer = new DkimSigner(
                $required['privateKey'],
                $required['domain'],
                $required['selector'],
                $signerOptions,
                $passphrase,
            );
        } catch (\LogicException | MimeInvalidArgumentException $e) {
            // DkimSigner itself reports a missing OpenSSL extension, an unreadable key and a wrong passphrase.
            throw new InvalidMailerConfigurationException(
                'Invalid DKIM configuration for Neos.SymfonyMailer: ' . $e->getMessage(),
                1794473025,
                $e
            );
        }

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new DkimSignedMessageListener($signer));

        return $dispatcher;
    }

    /**
     * Function that creates a DSN from the Swift-mailer configuration array to be able to migrate the
     * configuration from Swift-mailer to Symfony Mailer.
     *
     * Old configuration:
     * Neos:
     *  SwiftMailer:
     *      transport:
     *          type: 'Swift_SmtpTransport'
     *          options:
     *              host: 'smtp.example.com'
     *              port: '465'
     *              encryption: 'ssl'
     *              username: 'myaccount@example.com'
     *              password: 'shoobidoo'
     *
     * New configuration:
     *  Neos:
     *      SymfonyMailer:
     *          mailer:
     *              dsn: 'smtp://username:password@host:port'
     *
     * @param array $configuration
     * @return string
     */
    public function createDsnFromSMTPSwiftMailerConfiguration(array $configuration): string
    {
        $transport = new EsmtpTransport(
            host: urlencode($configuration['host'] ?? 'localhost'),
            port: (int)($configuration['port'] ?? 0)
        );

        if (isset($configuration['localDomain'])) {
            $transport->setLocalDomain($configuration['localDomain']);
        }

        if (isset($configuration['username'])) {
            $transport->setUsername(urlencode($configuration['username']));
        }

        if (isset($configuration['password'])) {
            $transport->setPassword(urlencode($configuration['password']));
        }

        $dsn = new Transport\Dsn(
            scheme: 'smtp',
            host: $configuration['host'] ?? 'localhost',
            user: $transport->getUsername(),
            password: $transport->getPassword(),
            port: $transport->getStream()->getPort()
        );

        return $this->getStringFromDSN($dsn);
    }

    protected function getStringFromDSN(Transport\Dsn $dsn): string
    {
        $dsnString = $dsn->getScheme() . '://';

        if ($dsn->getUser() !== null) {
            $dsnString .= $dsn->getUser();
            if ($dsn->getPassword() !== null) {
                $dsnString .= ':' . $dsn->getPassword();
            }
            $dsnString .= '@';
        }

        $dsnString .= $dsn->getHost();

        if ($dsn->getPort() !== null) {
            $dsnString .= ':' . $dsn->getPort();
        }

        return $dsnString;
    }
}
