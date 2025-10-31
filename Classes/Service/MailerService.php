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
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

#[Flow\Scope("singleton")]
class MailerService
{
    #[Flow\InjectConfiguration(path: "mailer", package: "Neos.SymfonyMailer")]
    protected array $mailerConfiguration;

    public const FORMAT_PLAINTEXT = 'text/plain';
    public const FORMAT_HTML = 'html';

    /**
     * Returns a mailer instance with the given transport or the configured default transport.
     *
     * @param TransportInterface|null $transport
     * @param EventDispatcherInterface|null $dispatcher
     * @return Mailer
     * @throws InvalidMailerConfigurationException
     */
    public function getMailer(TransportInterface $transport = null, ?EventDispatcherInterface $dispatcher = null): Mailer
    {
        if ($transport !== null) {
            return new Mailer($transport, null, $dispatcher);
        }

        // throw exception when dsn is not set
        if (!isset($this->mailerConfiguration['dsn'])) {
            throw new InvalidMailerConfigurationException('No DSN configured for Neos.SymfonyMailer', 1739540476);
        }

        return new Mailer(Transport::fromDsn($this->mailerConfiguration['dsn'], $dispatcher), null, $dispatcher);
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
