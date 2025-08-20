<?php

namespace App\Models;

use App\Enums\ExpenseType;
use App\Enums\Month;
use App\Enums\NotifyType;
use App\Enums\ShipmentDocType;
use App\Enums\TaxType;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PostalExpense extends Model
{
    protected $fillable = [
        // informazioni base
        // 'company_id',
        'notify_type',
        'new_contract_id',
        'client_id',
        'tax_type',

        // protocollo e invio
        'send_protocol_number',
        'send_protocol_date',
        'shipment_type_id',
        'supplier_id',
        'supplier_name',
        'recipient',

        // gestione anni
        'manage_year',
        'notify_year',
        'notify_month',

        // classificazione atto
        'act_type_id',
        'act_id',
        'act_year',
        'act_attachment_path',
        'act_attachment_date',

        // utente inserimento spedizione
        'shipment_insert_user_id',
        'shipment_insert_date',

        // lavorazione e notifica
        'notify_attachment_path',
        'notify_attachment_date',
        'order_rif',
        'list_rif',
        'receive_protocol_number',
        'receive_protocol_date',
        'notify_amount',
        'amount_registration_date',

        // utente inserimento notifica
        'notify_insert_user_id',
        'notify_insert_date',

        // gestione spese
        'expense_type',
        'passive_invoice_id',
        'notify_expense_amount',
        'mark_expense_amount',
        'reinvoice',
        'shipment_doc_type',
        'shipment_doc_number',
        'shipment_doc_date',
        'iban',

        // utente inserimento spese
        'expense_insert_user_id',
        'expense_insert_date',

        // pagamenti
        'payed',
        'payment_date',
        'payment_total',

        // utente inserimento pagamento
        'payment_insert_user_id',
        'payment_insert_date',

        // rifatturazione
        'reinvoice_id',
        'reinvoice_number',
        'reinvoice_date',
        'reinvoice_amount',

        // utente inserimento rifatturazione
        'reinvoice_insert_user_id',
        'reinvoice_insert_date',

        // allegati e registrazione
        'reinvoice_attachment_path',
        'reinvoice_attachment_date',
        'notify_date_registration_date',

        // utente registrazione
        'reinvoice_registration_user_id',
        'reinvoice_registration_date',

        // note
        'note',
    ];

    protected $casts = [
        // enum
        'notify_type' => NotifyType::class,
        'tax_type' => TaxType::class,
        'expense_type' => ExpenseType::class,
        'shipment_doc_type' => ShipmentDocType::class,
        'notify_month' => Month::class,

        // date
        'send_protocol_date' => 'date',
        'act_attachment_date' => 'date',
        'shipment_insert_date' => 'date',
        'notify_attachment_date' => 'date',
        'receive_protocol_date' => 'date',
        'amount_registration_date' => 'date',
        'notify_insert_date' => 'date',
        'expense_insert_date' => 'date',
        'payment_date' => 'date',
        'payment_insert_date' => 'date',
        'reinvoice_date' => 'date',
        'reinvoice_insert_date' => 'date',
        'reinvoice_attachment_date' => 'date',
        'notify_date_registration_date' => 'date',
        'reinvoice_registration_date' => 'date',

        // decimali
        'notify_amount' => 'decimal:2',
        'notify_expense_amount' => 'decimal:2',
        'mark_expense_amount' => 'decimal:2',
        'payment_total' => 'decimal:2',
        'reinvoice_amount' => 'decimal:2',

        // bool
        'reinvoice' => 'boolean',
        'payed' => 'boolean',

        // interi (chiavi esterne)
        // 'company_id' => 'integer',
        'new_contract_id' => 'integer',
        'shipment_type_id' => 'integer',
        'client_id' => 'integer',
        'supplier_id' => 'integer',
        'act_type_id' => 'integer',
        'shipment_insert_user_id' => 'integer',
        'notify_insert_user_id' => 'integer',
        'passive_invoice_id' => 'integer',
        'expense_insert_user_id' => 'integer',
        'payment_insert_user_id' => 'integer',
        'reinvoice_id' => 'integer',
        'reinvoice_insert_user_id' => 'integer',
        'reinvoice_registration_user_id' => 'integer',

        // interi (anni)
        'manage_year' => 'integer',
        'notify_year' => 'integer',
        'act_year' => 'integer',
    ];

    // relazioni
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function shipmentType()
    {
        return $this->belongsTo(ShipmentType::class);
    }

    public function actType()
    {
        return $this->belongsTo(ActType::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function contract()
    {
        return $this->belongsTo(NewContract::class, 'new_contract_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function passiveInvoice()
    {
        return $this->belongsTo(PassiveInvoice::class, 'passive_invoice_id');
    }

    public function reInvoice()
    {
        return $this->belongsTo(Invoice::class, 'reinvoice_id');
    }

    public function shipmentInsertUser()
    {
        return $this->belongsTo(User::class, 'shipment_insert_user_id');
    }

    public function notifyInsertUser()
    {
        return $this->belongsTo(User::class, 'notify_insert_user_id');
    }

    public function expenseInsertUser()
    {
        return $this->belongsTo(User::class, 'expense_insert_user_id');
    }

    public function paymentInsertUser()
    {
        return $this->belongsTo(User::class, 'payment_insert_user_id');
    }

    public function reinvoiceInsertUser()
    {
        return $this->belongsTo(User::class, 'reinvoice_insert_user_id');
    }

    public function reinvoiceRegistrationUser()
    {
        return $this->belongsTo(User::class, 'reinvoice_registration_user_id');
    }

    public function shipmentInserted()                                                  // funzione che controlla la presenza dell'inserimento dell'invio
    {
        return !is_null($this->shipment_insert_user_id) && !is_null($this->shipment_insert_date);
    }

    public function notificationInserted()                                              // funzione che controlla la presenza dell'inserimento della notifica
    {
        return $this->shipmentInserted() &&
               (!is_null($this->notify_insert_user_id) && !is_null($this->notify_insert_date));
    }

    public function expenseInserted()                                                   // funzione che controlla la presenza dell'inserimento delle spese
    {
        return $this->notificationInserted() &&
               (!is_null($this->expense_insert_user_id) && !is_null($this->expense_insert_date));
    }

    public function paymentInserted()                                                   // funzione che controlla la presenza dell'inserimento dei pagamenti
    {
        return $this->expenseInserted() &&
               (!is_null($this->payment_insert_user_id) && !is_null($this->payment_insert_date));
    }

    public function reinvoiceInserted()                                                 // funzione che controlla la presenza dell'inserimento della rifatturazione
    {
        return $this->paymentInserted() &&
               (!is_null($this->reinvoice_insert_user_id) && !is_null($this->reinvoice_insert_date));
    }

    public function reinvoiceRegistered()                                               // funzione che controlla la presenza della registrazione della rifatturazione
    {
        return $this->reinvoiceInserted() &&
               (!is_null($this->reinvoice_registration_user_id) && !is_null($this->reinvoice_registration_date));
    }

    protected static function booted()
    {
        static::creating(function ($expense) {
            $expense->company_id = Filament::getTenant()?->id;
            $expense->shipment_insert_user_id = Auth::id();
            $expense->shipment_insert_date = today();
            $contract = NewContract::find($expense->new_contract_id);
            $expense->reinvoice = $contract->reinvoice ?? false;
            if ($expense->notify_type === NotifyType::MESSO) {
                $expense->shipment_doc_type = ShipmentDocType::MESSO;
            } elseif ($expense->notify_type === NotifyType::SPEDIZIONE) {
                $expense->shipment_doc_type = ShipmentDocType::SPEDIZIONE;
            }
        });

        static::created(function ($expense) {
            //
        });

        static::updating(function ($expense) {
            $stages = [
                'reinvoiceInserted' => ['reinvoice_registration_user_id', 'reinvoice_registration_date'],
                'paymentInserted' => ['reinvoice_insert_user_id', 'reinvoice_insert_date'],
                'expenseInserted' => ['payment_insert_user_id', 'payment_insert_date'],
                'notificationInserted' => ['expense_insert_user_id', 'expense_insert_date'],
                'shipmentInserted' => ['notify_insert_user_id', 'notify_insert_date'],
            ];
            foreach ($stages as $method => [$userField, $dateField]) {
                if ($expense->$method()) {
                    $expense->$userField = Auth::id();
                    $expense->$dateField = today();
                    break;
                }
            }
        });

        static::deleting(function ($expense) {
            //
        });

    }
}
