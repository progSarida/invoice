<?php

namespace App\Models;

use App\Enums\ConnectionSafetyType;
use App\Enums\MailProtocolType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Sender extends Model
{
    protected $fillable = [
        'company_id',
        'public_name',
        'address',
        'connection_safety_type',
        'out_mail_server',
        'out_mail_protocol_type',
        'out_mail_port',
        'out_authentication',
        'out_username',
        'out_password',
    ];

    protected $casts = [
        'in_mail_protocol_type' => MailProtocolType::class,
        'out_mail_protocol_type' => MailProtocolType::class,
        'out_authentication' => 'boolean',
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

    protected static function booted()
    {
        static::creating(function ($sender) {
            //
        });

        static::created(function ($sender) {
            //
        });

        static::updating(function ($sender) {
            //
        });

        static::saving(function ($sender) {
            //
        });

        static::saved(function ($sender) {
            //
        });

        static::deleting(function ($sender) {
            //
        });

        static::deleted(function ($sender) {
            //
        });
    }

    public function getSmtpTransportName(): string
    {
        return match ($this->out_mail_protocol_type) {
            MailProtocolType::SMTP => 'smtp',
            // se supporti altri in futuro (es. sendmail, mailgun) → aggiungi case
            default => throw new \InvalidArgumentException("Protocollo outbound non supportato: {$this->out_mail_protocol_type->value}"),
        };
    }

    public function getSmtpEncryption(): ?string
    {
        return match ($this->connection_safety_type) {
            ConnectionSafetyType::SSL   => 'ssl',
            ConnectionSafetyType::TLS   => 'tls',
            ConnectionSafetyType::START => 'tls', // spesso STARTTLS → tls
            default => null,
        };
    }

    public function getSmtpUsername(): ?string
    {
        return $this->out_authentication ? $this->out_username : $this->username;
    }

    public function getSmtpPassword(): ?string
    {
        $encrypted = $this->out_authentication ? $this->out_password : $this->password;
        return Crypt::decrypt($encrypted);
    }

    /**
     * Restituisce array pronto per Mail::build()
     */
    public function getSmtpMailerConfig(): array
    {
        $encryption = $this->getSmtpEncryption();

        return [
            'transport'  => $this->getSmtpTransportName(),
            'host'       => $this->out_mail_server,
            'port'       => (int) $this->out_mail_port,
            'username'   => $this->getSmtpUsername(),
            'password'   => $this->getSmtpPassword(),
            'encryption' => $encryption,
            'timeout'    => 10,           // opzionale
            'auth_mode'  => null,         // opzionale
            // se hai bisogno di opzioni extra (es. stream options per self-signed)
            // 'stream' => ['ssl' => ['allow_self_signed' => true, 'verify_peer' => false]],
        ];
    }

    public function getSmtpMailerConfigSarida(): array
    {
        $encryption = $this->getSmtpEncryption();

        return [
            'transport'  => $this->getSmtpTransportName(),
            'host'       => $this->out_mail_server,
            'port'       => (int) $this->out_mail_port,
            'username'   => $this->getSmtpUsername(),
            'password'   => $this->getSmtpPassword(),
            'encryption' => $encryption,
            'timeout'    => 10,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ];
    }

    public function getFromAddress(): string
    {
        return $this->address;
    }

    public function getFromName(): ?string
    {
        return $this->public_name ?: $this->address;
    }
}
