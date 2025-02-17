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
use Neos\Flow\Cli\CommandController;
use Symfony\Component\Yaml\Yaml;
use Neos\SymfonyMailer\Service\MailerService;

/**
 * A command controller for migrating from SwiftMailer to Symfony Mailer
 */
class MigrateCommandController extends CommandController
{

    #[Flow\Inject]
    protected MailerService $mailerService;


    #[Flow\InjectConfiguration(path: "transport", package: "Neos.SwiftMailer")]
    protected array $swiftMailerConfiguration;

    /**
     * Command to migrate the SwiftMailer configuration to Symfony Mailer DSN.
     * Therefore, we check if we have a SwiftMailer configuration and if so, we create a DSN from it.
     */
    public function generateDSNFromSwiftMailerCommand(): void
    {
        if (!isset($this->swiftMailerConfiguration['type'])) {
            $this->outputLine('<error>No SwiftMailer configuration found. Nothing to migrate.</error>');
            $this->quit(1);
        }

        // Output the SwiftMailer configuration
        $yaml = Yaml::dump($this->swiftMailerConfiguration, 99);
        $this->outputLine('<b>Found Configuration for "Neos.SwiftMailer":</b>');
        $this->outputLine();
        $this->outputLine($yaml . chr(10));
        $this->outputLine();

        $transportType = $this->swiftMailerConfiguration['type'];
        $transportOptions = $this->swiftMailerConfiguration['options'] ?? [];

        if ($transportType === 'Swift_SmtpTransport') {
            $dsn = $this->mailerService->createDsnFromSMTPSwiftMailerConfiguration($transportOptions);
        } else {
            $this->outputLine('<error>Unsupported SymfonyMailer transport type. Nothing to migrate.</error>');
            $this->quit(1);
        }

        $this->outputLine('<success>DSN created: ' . $dsn . '</success>');
    }
}
