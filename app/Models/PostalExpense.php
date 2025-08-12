<?php

namespace App\Models;

use App\Enums\Month;
use App\Enums\NotifyType;
use App\Enums\PostalDocType;
use App\Enums\ProductType;
use App\Enums\TaxType;
use Illuminate\Database\Eloquent\Model;

class PostalExpense extends Model
{
    protected $fillable = [
        'company_id',
        'client_id',
        'notify_type',
        'new_contract_id',
        'tax_type',
        'reinvoice',
        'order_rif',
        'list_rif',
        'reinvoice_insert_user_id',
        'reinvoice_id',
        'note',
        's_shipment_date',
        's_month',
        's_shipment_type_id',
        's_supplier_id',
        's_year',
        's_postal_doc_type',
        's_product_type',
        's_amount',
        's_passive_invoice_id',
        's_passive_invoice_expenses',
        's_passive_invoice_settle_date',
        's_passive_invoice_amount',
        'm_notify_registration_date',
        'm_notify_registration_user_id',
        'm_scan_import_date',
        'm_scan_import_user_id',
        'm_send_protocol_number',
        'm_send_protocol_date',
        'm_receive_protocol_number',
        'm_receive_protocol_date',
        'm_supplier',
        'm_act_type_id',
        'm_act_id',
        'm_act_year',
        'm_recipient',
        'm_amount',
        'm_iban',
        'attachment',
        'm_payed',
        'm_payment_date',
        'm_payment_insert_date',
        'm_payment_insert_user_id',
    ];

    protected $casts = [
        'reinvoice' => 'boolean',
        'm_payed' => 'boolean',
        's_shipment_date' => 'date',
        's_passive_invoice_settle_date' => 'date',
        'm_notify_registration_date' => 'date',
        'm_scan_import_date' => 'date',
        'm_send_protocol_date' => 'date',
        'm_receive_protocol_date' => 'date',
        'm_payment_date' => 'date',
        'm_payment_insert_date' => 'date',
        's_amount' => 'decimal:2',
        's_passive_invoice_expenses' => 'decimal:2',
        's_passive_invoice_amount' => 'decimal:2',
        'm_amount' => 'decimal:2',
        's_year' => 'integer',
        'm_act_year' => 'integer',
        'notify_type' => NotifyType::class,
        'tax_type' => TaxType::class,
        's_month' => Month::class,
        's_postal_doc_type' => PostalDocType::class,
        's_product_type' => ProductType::class,
    ];

    // public function shipmentType(){
    //     return $this->belongsTo(ShipmentType::class);                            // in sospeso
    // }

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function newContract(){
        return $this->belongsTo(NewContract::class);
    }

    public function client(){
        return $this->belongsTo(Client::class);
    }

    public function contract(){
        return $this->belongsTo(NewContract::class);
    }

    public function supplier(){
        return $this->belongsTo(Supplier::class);
    }

    public function passiveInvoice()
    {
        return $this->belongsTo(PassiveInvoice::class, 's_passive_invoice_id');
    }

    public function reInvoice()
    {
        return $this->belongsTo(Invoice::class, 's_passive_invoice_id');
    }

    public function shipmentInsertUser(){
        return $this->belongsTo(User::class, 'shipment_insert_user_id');
    }

    public function notifyInsertUser(){
        return $this->belongsTo(User::class, 'notify_insert_user_id');
    }

    public function paymentInsertUser(){
        return $this->belongsTo(User::class, 'payment_insert_user_id');
    }

    public function reinvoiceInsertUser(){
        return $this->belongsTo(User::class, 'reinvoice_insert_user_id');
    }

    public function notifyRegistrationUser(){
        return $this->belongsTo(User::class, 'm_notify_registration_user_id');
    }
}
