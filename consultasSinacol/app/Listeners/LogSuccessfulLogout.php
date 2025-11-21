<?php

namespace App\Listeners;

use Carbon\Carbon;
use OwenIt\Auditing\Models\Audit;

class LogSuccessfulLogout
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        if (auth()->user() != null) {
            $data = [
                'auditable_id' => auth()->user()->id,
                'auditable_type' => 'Logged Out',
                'event' => 'Logged Out',
                'url' => request()->fullUrl(),
                'ip_address' => request()->getClientIp(),
                'user_agent' => request()->userAgent(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'user_id' => auth()->user()->id,
            ];

            //create audit trail data
            $details = Audit::create($data);
        }

    }
}
