<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Sender;
use Exception;

class InvoiceEmailService
{
    protected Sender $account;

    /**
     * Imposta l'account email dalla Company
     */
    public function setAccountFromCompany(Company $company): self
    {
        // Cerca un Sender associato alla company tramite l'email
        $account = Sender::where('company_id', $company->id)->first();

        // if (!$account) {
        //     // Fallback: usa il primo sender disponibile
        //     $account = Sender::first();
        // }

        if (!$account) {
            throw new Exception("Nessun account email configurato per l'invio fatture");
        }

        $this->account = $account;
        return $this;
    }

    /**
     * Ottiene l'account corrente
     */
    public function getAccount(): Sender
    {
        if (!isset($this->account)) {
            throw new Exception("Account non impostato. Chiamare setAccountFromCompany() prima.");
        }

        return $this->account;
    }
}
