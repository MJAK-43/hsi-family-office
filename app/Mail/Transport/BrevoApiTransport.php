<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Envoie les emails via l'API transactionnelle de Brevo (https://api.brevo.com/v3/smtp/email).
 *
 * Le compte Brevo n'accepte que les adresses IP autorisées : on force l'IPv4,
 * car seule l'adresse IPv4 du serveur est déclarée.
 */
class BrevoApiTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(private readonly string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('BREVO_API_KEY est vide : impossible d\'envoyer l\'email.');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $from = $email->getFrom()[0] ?? $message->getEnvelope()->getSender();

        $payload = array_filter([
            'sender' => $this->address($from),
            'to' => $this->addresses($email->getTo()),
            'cc' => $this->addresses($email->getCc()),
            'bcc' => $this->addresses($email->getBcc()),
            'replyTo' => isset($email->getReplyTo()[0]) ? $this->address($email->getReplyTo()[0]) : null,
            'subject' => (string) $email->getSubject(),
            'htmlContent' => $email->getHtmlBody() !== null ? (string) $email->getHtmlBody() : null,
            'textContent' => $email->getTextBody() !== null ? (string) $email->getTextBody() : null,
            'attachment' => array_map(fn ($part) => [
                'name' => $part->getFilename() ?? 'piece-jointe',
                'content' => base64_encode($part->getBody()),
            ], $email->getAttachments()),
        ], fn ($value) => $value !== null && $value !== []);

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'accept' => 'application/json',
        ])
            ->withOptions(['force_ip_resolve' => 'v4'])
            ->timeout(20)
            ->post(self::ENDPOINT, $payload);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Brevo a refusé l\'email (HTTP %d) : %s',
                $response->status(),
                $response->json('message') ?? $response->body()
            ));
        }

        if ($messageId = $response->json('messageId')) {
            $message->setMessageId(trim($messageId, '<>'));
        }
    }

    /** @param  Address[]  $addresses */
    private function addresses(array $addresses): array
    {
        return array_values(array_map(fn (Address $address) => $this->address($address), $addresses));
    }

    private function address(Address $address): array
    {
        return array_filter([
            'email' => $address->getAddress(),
            'name' => $address->getName() ?: null,
        ]);
    }

    public function __toString(): string
    {
        return 'brevo+api://default';
    }
}
