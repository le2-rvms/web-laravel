<?php

namespace App\Observers;

use App\Models\_\Configuration;

class ConfigObserver
{
    public function created(Configuration $configurations)
    {
        Configuration::forget();
    }

    public function updated(Configuration $configurations)
    {
        Configuration::forget();
    }

    public function deleted(Configuration $configurations)
    {
        Configuration::forget();
    }

    public function restored(Configuration $configurations)
    {
        Configuration::forget();
    }

    public function forceDeleted(Configuration $configurations)
    {
        Configuration::forget();
    }
}
