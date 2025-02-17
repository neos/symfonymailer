Neos SymfonyMailer
================

The Mailer package facilitates email sending via the Symfony/Mailer package and simplifies its usage with
the standard method  of Neos.Flow configuration.

The package can also facilitate a smoother migration from the old Swiftmailer package to the new Symfony/Mailer package.

Getting Started
---------------

```
$ composer require neos/symfonymailer
```

Configuration
-------------

The package provides a default configuration for the Symfony/Mailer package. You can override the default configuration
by adding the following configuration to your `Settings.yaml`:

```yaml
Neos:
  SymfonyMailer:
    mailer:
      dsn: 'smtp://localhost'
```

The `dsn` parameter is the only required parameter. It should be a valid DSN string for the Symfony/Mailer package.
If you migrate from Swiftmailer, you can use the following command to generate the DSN:

```bash
./flow symfonymailer:migrate:generatedsnfromswiftmailer
```

Usage
-----

You can test the mailer configuration by sending a test email:

```bash
./flow symfonymailer:test:send --from="team@neos.io" --to="jon@doe.com" --subject="Test email" --body="This is a test email"
```

The package’s design principle is to minimize modifications to the original mailer package. Consequently, only the classes of Symfony/Mailer are utilized for emails, attachments, and so on. The package solely provides a service to initialize the mailer with the configuration.
Additionally, the package provides a command to send test emails and migrate the SwiftMailer configuration to an appropriate DSN for Symfony/Mailer.

If you use the `LoggingTransport` or `MboxTransport` from SwiftMailer, there is no replacement for the Symfony/Mailer package.

**Basic Example**
```php
#[Flow\Inject]
protected MailerService $mailerService;

public function sendEmail(string $from, string $to, string $subject, string $body): void
{
	$email = new Email();
	$email
		->from($from)
		->to($to)
		->subject($subject)
		->text($body);

	$mailer = $this->mailerService->getMailer();
	$mailer->send($email);
}
```

Migrate from Swiftmailer
------------------------

As previously mentioned, the package includes a command to generate a DSN from the Swiftmailer configuration.
To utilize this command, you must have the Swiftmailer configuration located in your `Settings.yaml` file.

You can use the following command to generate the DSN.
```bash
./flow symfonymailer:migrate:generatedsnfromswiftmailer
```

The PHP code within your package requires some modifications.
To facilitate the migration process, there are several rector rules available that facilitate the transition from Swiftmailer to Symfony/Mailer.

* [SwiftMessageToEmailRector](https://getrector.com/rule-detail/swift-message-to-email-rector)
* [SwiftCreateMessageToNewEmailRector](https://getrector.com/rule-detail/swift-create-message-to-new-email-rector)
* [SwiftSetBodyToHtmlPlainMethodCallRector](https://getrector.com/rule-detail/swift-set-body-to-html-plain-method-call-rector)

Utilizing these tools, it is feasible to migrate your codebase to Symfony/Mailer.
