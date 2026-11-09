<?php

namespace App\Events;

use App\Models\MonthlyCharge;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ChargesGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  Collection<int, MonthlyCharge>  $charges
     */
    public function __construct(public $charges, public int $periodYear, public int $periodMonth) {}
}
