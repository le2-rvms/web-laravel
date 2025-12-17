<?php

namespace App\Observers;

use App\Models\One\OneAccount;

class Vehicle122AccountObserver
{
    public $afterCommit = true;

    /**
     * Handle the Vehicle122Account "created" event.
     */
    public function created(OneAccount $oneAccount): void {}

    /**
     * Handle the Vehicle122Account "updated" event.
     */
    public function updated(OneAccount $oneAccount): void
    {
        if ($oneAccount->wasChanged('oa_cookie_string')) {
            $oneAccount->deleteCookies();
        }
    }

    /**
     * Handle the Vehicle122Account "deleted" event.
     */
    public function deleted(OneAccount $oneAccount): void
    {
        $oneAccount->deleteCookies();
    }

    /**
     * Handle the Vehicle122Account "restored" event.
     */
    public function restored(OneAccount $oneAccount): void {}

    /**
     * Handle the Vehicle122Account "force deleted" event.
     */
    public function forceDeleted(OneAccount $oneAccount): void
    {
        $oneAccount->deleteCookies();
    }
}
