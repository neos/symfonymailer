<?php
declare(strict_types=1);

namespace Neos\SymfonyMailer\Command;

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
use Symfony\Component\Yaml\Yaml;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Cli\Exception\StopCommandException;
use Neos\SymfonyMailer\Exception\InvalidMailerConfigurationException;
use Neos\SymfonyMailer\Service\MailerService;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * A command controller for sending test emails
 */
class TestCommandController extends CommandController
{

    #[Flow\Inject]
    protected MailerService $mailerService;

    #[Flow\InjectConfiguration(package: "Neos.SymfonyMailer")]
    protected array $mailerConfiguration;

    /**
     * A command for creating and sending simple emails.
     *
     * @param string $from The from address of the message
     * @param string $to The to address of the message
     * @param string $subject The subject of the message
     * @param string $body The body of the message
     * @param string $contentType The body content type of the message (Default: test/plain)
     * @throws TransportExceptionInterface
     * @throws StopCommandException
     */
    public function sendCommand(string $from, string $to, string $subject, string $body = '', string $contentType = 'text/plain'): void
    {
        $email = new Email();
        $email
            ->from($from)
            ->to($to)
            ->subject($subject);

        if ($contentType === MailerService::FORMAT_HTML) {
            $email->html($body);
        } else {
            $email->text($body);
        }

        try {
            // Output the SwiftMailer configuration
            $yaml = Yaml::dump($this->mailerConfiguration, 99);
            $this->outputLine('<b>Send mail with following configuration "Neos.SymfonyMailer":</b>');
            $this->outputLine();
            $this->outputLine($yaml . chr(10));

            $mailer = $this->mailerService->getMailer();
            $mailer->send($email);
        } catch (InvalidMailerConfigurationException|\Exception $e) {
            $this->outputLine('<error>' . $e->getMessage() . '</error>');
            $this->quit(1);
        }

        $this->outputLine('<success>E-Mail has successfully been sent.</success>');
    }
}
