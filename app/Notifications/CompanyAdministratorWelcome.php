<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyAdministratorWelcome extends Notification
{
    use Queueable;

    public function __construct(private readonly Company $company, private readonly string $token) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Acesso administrativo ao RadiusHub')
            ->greeting('Olá, '.$notifiable->name)
            ->line('Seu acesso administrativo à empresa '.$this->company->legal_name.' foi criado.')
            ->action('Definir ou alterar senha', url('/reset-password/'.$this->token.'?email='.urlencode($notifiable->email)))
            ->line('Por segurança, defina uma senha exclusiva antes de utilizar a plataforma.');
    }
}
