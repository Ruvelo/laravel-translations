<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;

class Controller
{
    public function show()
    {
        $a = __('billing.invoice.title');
        $b = trans('billing.invoice.paid');
        $c = trans_choice('billing.plans', 2);
        $d = Lang::get('billing.refund');
        $e = __('Download receipt');
        $f = __('status.'.$this->status);
        $g = $this->trans('not.a.key');
        $h = __($dynamic);
        $i = __('It\'s overdue');
        $j = Lang::choice('billing.seats', 3);

        return __('courier::messages.sent');
    }
}
