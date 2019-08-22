<?php

namespace App\Mail;

use App\Product;
use App\Services\AWS\AwsS3Service;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;
use Laravel\Cashier\Invoice;

class CustomerInvoice extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var \Laravel\Cashier\invoice
     */
    protected $invoice;

    /**
     * @var \App\User
     */
    protected $owner;

    /**
     * @var \App\Product
     */
    protected $product;

    /**
     * @param Invoice $invoice
     * @param User $owner
     * @param Product $product
     */
    public function __construct(Invoice $invoice, User $owner, Product $product)
    {
        $this->invoice = $invoice;
        $this->owner = $owner;
        $this->product = $product;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(AwsS3Service $awsS3Service)
    {
        $awsS3Service->setUser($this->owner)->setBucketName();

        return $this->view('kickstart.invoice')
            ->from($this->owner->email)
            ->subject('Invoice for customer')
            ->with([
                'invoice' => $this->invoice,
                'owner' => $this->owner,
                'primary_color' => array_get(
                    $this->owner->settings,
                    'primary_color',
                    '#3383A8'
                ),
                'icon_path' =>
                    array_has($this->owner->settings, 'icon_path')
                        ? Storage::cloud()->url($this->owner->settings['icon_path'])
                        : '',
                'logo_path' =>
                    array_has($this->owner->settings, 'logo_path')
                        ? Storage::cloud()->url($this->owner->settings['logo_path'])
                        : '',
                'id' => $this->invoice->number,
                'card_type' => $this->invoice->charge->payment_method_details->card->brand,
                'product' => $this->product->name,
                'vendor' => array_get(
                    $this->owner->settings,
                    'brand_name',
                    $this->owner->email
                ),
            ]);
    }
}
